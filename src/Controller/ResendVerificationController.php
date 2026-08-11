<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class ResendVerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserMailer $userMailer
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // 1. Récupérer l'email envoyé par le Front-end
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return new JsonResponse(['message' => 'L\'adresse email est requise.'], Response::HTTP_BAD_REQUEST);
        }

        // 2. Chercher l'utilisateur correspondant en BDD
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Sécurité : On répond un succès même si l'email n'existe pas pour éviter le "User Enumeration"
            return new JsonResponse(['message' => 'Si le compte existe, un nouveau lien a été envoyé.'], Response::HTTP_OK);
        }

        // 3. Vérifier si l'utilisateur n'est pas déjà validé
        if ($user->isVerified()) {
            return new JsonResponse(['message' => 'Cette adresse email est déjà validée.'], Response::HTTP_BAD_REQUEST);
        }

        // 4. Déclencher le renvoi via notre service
        $this->userMailer->sendVerificationEmail($user);

        return new JsonResponse(['message' => 'Un nouveau lien de validation a été envoyé.'], Response::HTTP_OK);
    }
}