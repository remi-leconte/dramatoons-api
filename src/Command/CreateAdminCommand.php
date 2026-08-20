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
    name: 'app:create-admin',
    description: 'Crée un utilisateur administrateur initial si aucun n\'existe.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $_ENV['INITIAL_ADMIN_EMAIL'] ?? null;
        $password = $_ENV['INITIAL_ADMIN_PASSWORD'] ?? null;

        if (!$email || !$password) {
            $io->error('Variables INITIAL_ADMIN_EMAIL ou INITIAL_ADMIN_PASSWORD manquantes.');
            return Command::FAILURE;
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->warning(sprintf('L\'administrateur %s existe déjà.', $email));
            return Command::SUCCESS;
        }

        // Création de l'admin
        $admin = new User();
        $admin->setLogin('admintemp');
        $admin->setEmail($email);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, $password));

        $this->em->persist($admin);
        $this->em->flush();

        $io->success(sprintf('Administrateur %s créé avec succès !', $email));
        return Command::SUCCESS;
    }
}