<?php

namespace App\Tests\Api\Utilisateur;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Api\ApiTestCase;
use App\Tests\Factory\UserFactory;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[TestDox('Commandes d’installation : premier administrateur et compte « Utilisateur supprimé »')]
final class CommandesTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    private array $variablesInitiales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->variablesInitiales = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->variablesInitiales;
        parent::tearDown();
    }

    private function lancer(string $commande): int
    {
        $testeur = new CommandTester((new Application(static::bootKernel()))->find($commande));

        return $testeur->execute([]);
    }

    #[TestDox('app:create-admin crée le premier administrateur, qui peut se connecter')]
    public function testCreationDuPremierAdministrateur(): void
    {
        $_SERVER['INITIAL_ADMIN_EMAIL'] = 'admin@dramatoons.test';
        $_SERVER['INITIAL_ADMIN_PASSWORD'] = 'secret';

        $this->assertSame(Command::SUCCESS, $this->lancer('app:create-admin'));

        $administrateur = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@dramatoons.test']);
        $this->assertSame('ROLE_ADMIN', $administrateur->getRole());
        $this->connexion($administrateur->getLogin(), 'secret');
        $this->assertResponseIsSuccessful();
    }

    #[TestDox('app:create-deleted-user crée le compte « Utilisateur supprimé », désactivé, une seule fois')]
    public function testCreationDuCompteUtilisateurSupprime(): void
    {
        $_SERVER['DELETED_USER_PASSWORD'] = 'secret';

        $this->assertSame(Command::SUCCESS, $this->lancer('app:create-deleted-user'));
        $this->assertSame(Command::SUCCESS, $this->lancer('app:create-deleted-user'));

        $comptes = static::getContainer()->get(UserRepository::class)->findBy(['login' => User::DELETED_USER_LOGIN]);
        $this->assertCount(1, $comptes);
        $this->assertFalse($comptes[0]->isPublish());
        $this->connexion(User::DELETED_USER_LOGIN, 'secret');
        $this->assertResponseStatusCodeSame(401);
    }

    #[TestDox('Sans les variables d’environnement, les commandes échouent sans rien créer')]
    public function testVariablesManquantes(): void
    {
        unset($_SERVER['INITIAL_ADMIN_EMAIL'], $_SERVER['INITIAL_ADMIN_PASSWORD'], $_SERVER['DELETED_USER_PASSWORD']);
        $avant = UserFactory::count();

        $this->assertSame(Command::FAILURE, $this->lancer('app:create-admin'));
        $this->assertSame(Command::FAILURE, $this->lancer('app:create-deleted-user'));
        $this->assertSame($avant, UserFactory::count());
    }
}
