<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserMailer
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        #[Autowire(env: 'FRONTEND_URL')]
        private string $frontendUrl
    ) {}

    /**
     * Génère le token de validation et envoie l'e-mail à l'utilisateur.
     */
    public function sendVerificationEmail(User $user): void
    {
        // 1. Génération et affectation des jetons de validation
        $user->setVerified(0);
        $user->setResetToken(bin2hex(random_bytes(32)));
        $user->setResetTokenExpiration((new \DateTimeImmutable())->modify('+1 hour'));

        // 2. Sauvegarde des modifications du token en BDD
        $this->em->flush();

        // 3. Préparation et envoi de l'e-mail
        $email = (new Email())
            ->from(new Address('no-reply@rick5016.net', 'Dramatoons'))
            ->to($user->getEmail())
            ->subject('Validez votre adresse email')
            ->html(sprintf('Cliquez ici : %s/verify?token=%s', $this->frontendUrl, $user->getResetToken()));

        $this->mailer->send($email);
    }

    /**
     * Génère le token de réinitialisation et envoie l'e-mail de mot de passe oublié.
     */
    public function sendForgotPasswordEmail(User $user): void
    {
        // 1. Génération du jeton (valable 1 heure)
        $user->setResetToken(bin2hex(random_bytes(32)));
        $user->setResetTokenExpiration((new \DateTimeImmutable())->modify('+1 hour'));

        // 2. Sauvegarde en BDD
        $this->em->flush();

        // 3. Préparation et envoi de l'e-mail avec le lien vers ton futur formulaire Front
        $email = (new Email())
            ->from(new Address('no-reply@rick5016.net', 'Dramatoons'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->html(sprintf('Cliquez ici pour changer votre mot de passe : %s/reset-password?token=%s', $this->frontendUrl, $user->getResetToken()));

        $this->mailer->send($email);
    }
}