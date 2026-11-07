<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-deleted-user',
    description: 'Crée le compte « Utilisateur supprimé », qui reçoit les webtoons des comptes supprimés.',
)]
class CreateDeletedUserCommand extends Command
{
    public const LOGIN = User::DELETED_USER_LOGIN;
    public const EMAIL = 'dramatoons@mailo.com';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $password = $_ENV['DELETED_USER_PASSWORD'] ?? $_SERVER['DELETED_USER_PASSWORD'] ?? null;

        if (!$password) {
            $io->error('Variable DELETED_USER_PASSWORD manquante.');
            return Command::FAILURE;
        }

        // Vérifier si le compte existe déjà
        $repository = $this->em->getRepository(User::class);
        if ($repository->findOneBy(['login' => self::LOGIN]) || $repository->findOneBy(['email' => self::EMAIL])) {
            $io->warning(sprintf('Le compte « %s » existe déjà.', self::LOGIN));
            return Command::SUCCESS;
        }

        // Création du compte
        $user = new User();
        $user->setLogin(self::LOGIN);
        $user->setEmail(self::EMAIL);
        $user->setPublish(false);
        $user->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Compte « %s » créé avec succès !', self::LOGIN));
        return Command::SUCCESS;
    }
}
