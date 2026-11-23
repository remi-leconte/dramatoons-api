<?php

namespace App\Serializer;

use App\Entity\User;
use App\Entity\Webtoon;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Ajoute canEdit à chaque webtoon lu : le front n'a pas à recalculer ce droit, qui dépend de suivis qu'il ne voit pas
 */
final class WebtoonNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const DEJA_APPELE = 'webtoon_normalizer_deja_appele';

    public function __construct(private Security $security) {}

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::DEJA_APPELE] = true;
        $normalise = $this->normalizer->normalize($data, $format, $context);

        if (\is_array($normalise) && \in_array('webtoon:read', (array) ($context['groups'] ?? []), true)) {
            $user = $this->security->getUser();
            $normalise['canEdit'] = $user instanceof User && $data->isEditableByCreator($user);
        }

        return $normalise;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Webtoon && !isset($context[self::DEJA_APPELE]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Webtoon::class => false];
    }
}
