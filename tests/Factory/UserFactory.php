<?php

namespace App\Tests\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        $login = 'utilisateur-'.self::faker()->unique()->numberBetween(1, 1_000_000);

        return [
            'login' => $login,
            'email' => $login.'@dramatoons.test',
            'roles' => ['ROLE_USER'],
            'password' => 'inutilise',
            'publish' => true,
            'verified' => true,
        ];
    }

    public function moderateur(): self
    {
        return $this->with(['roles' => ['ROLE_MODO']]);
    }

    public function administrateur(): self
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }

    public function avecMotDePasse(string $motDePasse): self
    {
        // Coût minimal : le hasheur « auto » de Symfony vérifie n'importe quel hash bcrypt
        return $this->with(['password' => password_hash($motDePasse, \PASSWORD_BCRYPT, ['cost' => 4])]);
    }

    public function compteUtilisateurSupprime(): self
    {
        return $this->with(['login' => User::DELETED_USER_LOGIN, 'publish' => false]);
    }
}
