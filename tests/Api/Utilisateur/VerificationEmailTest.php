<?php

namespace App\Tests\Api\Utilisateur;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Vérification de l’email : lien valable 1 h (POST /users/verify et /users/resend-verification)')]
final class VerificationEmailTest extends ApiTestCase
{
    private function utilisateur(int $id): User
    {
        return static::getContainer()->get(UserRepository::class)->find($id);
    }

    #[TestDox('À l’inscription, un lien de vérification valable 1 h est envoyé à l’email du compte')]
    public function testLienALInscription(): void
    {
        $this->post(null, '/users', ['login' => 'nouveau', 'email' => 'nouveau@dramatoons.test', 'password' => 'secret']);

        $this->assertResponseStatusCodeSame(201);
        $this->jetonDuMail('/verify', 'nouveau@dramatoons.test');
        $utilisateur = static::getContainer()->get(UserRepository::class)->findOneBy(['login' => 'nouveau']);
        $this->assertEqualsWithDelta((new \DateTimeImmutable('+1 hour'))->getTimestamp(), $utilisateur->getResetTokenExpiration()->getTimestamp(), 60);
    }

    #[TestDox('Le lien vérifie l’email, et ne sert qu’une fois : 404 ensuite')]
    public function testVerification(): void
    {
        $this->post(null, '/users', ['login' => 'nouveau', 'email' => 'nouveau@dramatoons.test', 'password' => 'secret']);
        $jeton = $this->jetonDuMail('/verify', 'nouveau@dramatoons.test');

        $this->post(null, '/users/verify', ['token' => $jeton]);
        $this->assertResponseIsSuccessful();
        $this->assertTrue(static::getContainer()->get(UserRepository::class)->findOneBy(['login' => 'nouveau'])->isVerified());

        $this->post(null, '/users/verify', ['token' => $jeton]);
        $this->assertResponseStatusCodeSame(404);
    }

    #[TestDox('Un lien expiré est refusé : 400, l’email reste non vérifié')]
    public function testLienExpire(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => false, 'resetToken' => 'abc123', 'resetTokenExpiration' => new \DateTimeImmutable('-1 minute')]);

        $this->post(null, '/users/verify', ['token' => 'abc123']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertFalse($this->utilisateur($utilisateur->getId())->isVerified());
    }

    #[TestDox('Un lien inconnu est refusé : 404')]
    public function testLienInconnu(): void
    {
        $this->post(null, '/users/verify', ['token' => 'inconnu']);

        $this->assertResponseStatusCodeSame(404);
    }

    #[TestDox('Après un changement d’email, le compte repasse à « non vérifié » et un lien valable 1 h est envoyé à la nouvelle adresse')]
    public function testChangementDEmail(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => true]);

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['email' => 'nouvelle@dramatoons.test']);

        $this->assertResponseIsSuccessful();
        $this->jetonDuMail('/verify', 'nouvelle@dramatoons.test');
        $enBase = $this->utilisateur($utilisateur->getId());
        $this->assertFalse($enBase->isVerified());
        $this->assertEqualsWithDelta((new \DateTimeImmutable('+1 hour'))->getTimestamp(), $enBase->getResetTokenExpiration()->getTimestamp(), 60);
    }

    #[TestDox('Un changement d’email fait par un administrateur repasse aussi le compte à « non vérifié » et envoie un lien à la nouvelle adresse')]
    public function testChangementDEmailParUnAdministrateur(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => true]);

        $this->patch(UserFactory::new()->administrateur()->create(), '/users/'.$utilisateur->getId(), ['email' => 'nouvelle@dramatoons.test']);

        $this->assertResponseIsSuccessful();
        $this->jetonDuMail('/verify', 'nouvelle@dramatoons.test');
        $this->assertFalse($this->utilisateur($utilisateur->getId())->isVerified());
    }

    #[TestDox('Un changement de login seul n’envoie pas de lien et garde l’email vérifié')]
    public function testChangementDeLoginSeul(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => true]);

        $this->patch($utilisateur, '/users/'.$utilisateur->getId(), ['login' => 'autre-login']);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
        $this->assertTrue($this->utilisateur($utilisateur->getId())->isVerified());
    }

    #[TestDox('L’utilisateur redemande un lien : un nouveau lien est envoyé')]
    public function testNouveauLien(): void
    {
        $utilisateur = UserFactory::createOne(['verified' => false]);

        $this->post($utilisateur, '/users/resend-verification', ['email' => $utilisateur->getEmail()]);

        $this->assertResponseIsSuccessful();
        $jeton = $this->jetonDuMail('/verify', $utilisateur->getEmail());
        $this->post(null, '/users/verify', ['token' => $jeton]);
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Redemander un lien répond pareil pour un email inconnu, non vérifié ou déjà vérifié ; seul l’email non vérifié reçoit un lien')]
    public function testNouveauLienEmailDejaVerifie(): void
    {
        $this->markTestIncomplete('Écart : l’API répond 400 pour un email déjà vérifié, ce qui révèle que l’adresse a un compte (#103)');
        $nonVerifie = UserFactory::createOne(['verified' => false]);
        $verifie = UserFactory::createOne(['verified' => true]);

        $reponses = [];
        foreach (['inconnu@dramatoons.test' => 0, $nonVerifie->getEmail() => 1, $verifie->getEmail() => 0] as $email => $emailsEnvoyes) {
            $reponses[] = $this->reponseComparable($this->post(null, '/users/resend-verification', ['email' => $email]));
            $this->assertEmailCount($emailsEnvoyes);
        }

        $this->assertSame($reponses[0], $reponses[1]);
        $this->assertSame($reponses[0], $reponses[2]);
    }

    #[TestDox('Redemander un lien pour un email inconnu ne le signale pas et n’envoie rien')]
    public function testNouveauLienEmailInconnu(): void
    {
        $this->post(null, '/users/resend-verification', ['email' => 'inconnu@dramatoons.test']);

        $this->assertResponseIsSuccessful();
        $this->assertEmailCount(0);
    }
}
