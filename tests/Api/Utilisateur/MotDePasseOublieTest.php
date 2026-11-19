<?php

namespace App\Tests\Api\Utilisateur;

use App\Repository\UserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;

#[TestDox('Mot de passe oublié : lien valable 1 h (POST /users/forgot-password et /users/reset-password)')]
final class MotDePasseOublieTest extends ApiTestCase
{
    private function demanderLien(string $email): string
    {
        $this->post(null, '/users/forgot-password', ['email' => $email]);
        $this->assertResponseIsSuccessful();

        return $this->jetonDuMail('/reset-password', $email);
    }

    #[TestDox('L’utilisateur saisit son email et reçoit un lien valable 1 h')]
    public function testDemandeDeLien(): void
    {
        $utilisateur = UserFactory::createOne();

        $this->demanderLien($utilisateur->getEmail());

        $expiration = static::getContainer()->get(UserRepository::class)->find($utilisateur->getId())->getResetTokenExpiration();
        $this->assertEqualsWithDelta((new \DateTimeImmutable('+1 hour'))->getTimestamp(), $expiration->getTimestamp(), 60);
    }

    #[TestDox('Un email inconnu n’est pas signalé : même réponse que pour un email connu, et aucun email envoyé')]
    public function testEmailInconnu(): void
    {
        $connu = $this->reponseComparable($this->post(null, '/users/forgot-password', ['email' => UserFactory::createOne()->getEmail()]));
        $this->assertEmailCount(1);

        $inconnu = $this->reponseComparable($this->post(null, '/users/forgot-password', ['email' => 'inconnu@dramatoons.test']));
        $this->assertEmailCount(0);

        $this->assertResponseIsSuccessful();
        $this->assertSame($connu, $inconnu);
    }

    #[TestDox('Le lien permet de choisir un nouveau mot de passe, qui sert ensuite à se connecter ; le lien ne sert qu’une fois')]
    public function testNouveauMotDePasse(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('ancien')->create();
        $jeton = $this->demanderLien($utilisateur->getEmail());

        $this->post(null, '/users/reset-password', ['token' => $jeton, 'password' => 'nouveau']);
        $this->assertResponseIsSuccessful();

        $this->connexion($utilisateur->getLogin(), 'nouveau');
        $this->assertResponseIsSuccessful();
        $this->connexion($utilisateur->getLogin(), 'ancien');
        $this->assertResponseStatusCodeSame(401);

        $this->post(null, '/users/reset-password', ['token' => $jeton, 'password' => 'encore1']);
        $this->assertResponseStatusCodeSame(404);
    }

    #[TestDox('Le nouveau mot de passe doit faire au moins 6 caractères : 422 pour 5')]
    public function testMotDePasseTropCourt(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('ancien')->create();
        $jeton = $this->demanderLien($utilisateur->getEmail());

        $this->post(null, '/users/reset-password', ['token' => $jeton, 'password' => '12345']);
        $this->assertResponseStatusCodeSame(422);

        $this->post(null, '/users/reset-password', ['token' => $jeton, 'password' => '123456']);
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Un lien expiré est refusé : 400, le mot de passe ne change pas')]
    public function testLienExpire(): void
    {
        $utilisateur = UserFactory::new()->avecMotDePasse('ancien')->create(['resetToken' => 'abc123', 'resetTokenExpiration' => new \DateTimeImmutable('-1 minute')]);

        $this->post(null, '/users/reset-password', ['token' => 'abc123', 'password' => 'nouveau']);
        $this->assertResponseStatusCodeSame(400);

        $this->connexion($utilisateur->getLogin(), 'ancien');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('Le lien de mot de passe oublié et celui de vérification de l’email sont indépendants')]
    public function testLiensIndependants(): void
    {
        $this->markTestIncomplete('Écart : les deux liens partagent le champ resetToken, chacun marche sur l’autre page et demander l’un invalide l’autre (#104)');
        // Lien de vérification puis lien de mot de passe oublié
        $premier = UserFactory::createOne(['verified' => false]);
        $this->post(null, '/users/resend-verification', ['email' => $premier->getEmail()]);
        $verification = $this->jetonDuMail('/verify', $premier->getEmail());
        $reinitialisation = $this->demanderLien($premier->getEmail());

        $this->post(null, '/users/reset-password', ['token' => $verification, 'password' => 'pirate']);
        $this->assertResponseStatusCodeSame(404, 'Un lien de vérification ne change pas le mot de passe');
        $this->post(null, '/users/verify', ['token' => $reinitialisation]);
        $this->assertResponseStatusCodeSame(404, 'Un lien de mot de passe oublié ne vérifie pas l’email');
        $this->post(null, '/users/verify', ['token' => $verification]);
        $this->assertResponseIsSuccessful('Demander un lien de mot de passe oublié n’invalide pas le lien de vérification');

        // Lien de mot de passe oublié puis lien de vérification
        $second = UserFactory::createOne(['verified' => false]);
        $reinitialisation = $this->demanderLien($second->getEmail());
        $this->post(null, '/users/resend-verification', ['email' => $second->getEmail()]);
        $this->jetonDuMail('/verify', $second->getEmail());

        $this->post(null, '/users/reset-password', ['token' => $reinitialisation, 'password' => 'nouveau']);
        $this->assertResponseIsSuccessful('Redemander un lien de vérification n’invalide pas le lien de mot de passe oublié');
    }

    #[TestDox('Un lien inconnu est refusé : 404')]
    public function testLienInconnu(): void
    {
        $this->post(null, '/users/reset-password', ['token' => 'inconnu', 'password' => 'nouveau']);

        $this->assertResponseStatusCodeSame(404);
    }
}
