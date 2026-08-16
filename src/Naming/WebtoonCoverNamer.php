<?php

namespace App\Naming;

use App\Entity\Webtoon;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

class WebtoonCoverNamer implements NamerInterface
{
    public function name(object $object, PropertyMapping $mapping): string
    {
        /** @var Webtoon $object */
        $extension = $object->getImageFile()?->guessExtension() ?? 'jpg';
        
        return sprintf('webtoon_presentation_%s.%s', $object->getSlug(), $extension);
    }
}