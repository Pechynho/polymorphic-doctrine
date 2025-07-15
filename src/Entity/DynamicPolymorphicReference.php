<?php

namespace Pechynho\PolymorphicDoctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Trait\PolymorphicReferenceTrait;

#[ORM\Embeddable]
final class DynamicPolymorphicReference implements PolymorphicReferenceInterface
{
    use PolymorphicReferenceTrait;

    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 128, nullable: true)]
        public ?string $discriminator = null,
        #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
        public ?string $id = null,
    ) {}
}
