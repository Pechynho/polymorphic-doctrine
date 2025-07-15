<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;

final readonly class DynamicPropertyMetadata implements PropertyMetadataInterface
{
    public function __construct(
        public string $property,
        /** @var array<string, DynamicRelationMetadata> $mapping */
        public array $mapping,
        public bool $enableDiscriminatorIndex,
        public bool $enablePairIndex,
    ) {}
}
