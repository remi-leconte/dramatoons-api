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
        $user->setVerified(0);
        $user->setResetToken(bin2hex(random_bytes(32)));
        $user->setResetTokenExpiration((new \DateTimeImmutable())->modify('+1 hour'));
        $this->em->flush();

        $email = (new Email())
            ->from(new Address('noreply@dramatoons.ovh', 'Dramatoons'))
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
        $user->setResetToken(bin2hex(random_bytes(32)));
        $user->setResetTokenExpiration((new \DateTimeImmutable())->modify('+1 hour'));
        $this->em->flush();

        $email = (new Email())
            ->from(new Address('noreply@dramatoons.ovh', 'Dramatoons'))
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe')
            ->html(sprintf('Cliquez ici pour changer votre mot de passe : %s/reset-password?token=%s', $this->frontendUrl, $user->getResetToken()));

        $this->mailer->send($email);
    }
}