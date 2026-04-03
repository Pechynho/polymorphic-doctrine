<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;

final readonly class DynamicPropertyMetadata implements PropertyMetadataInterface
{
    /**
     * @param array<string, DynamicRelationMetadata> $mapping
     */
    public function __construct(
        public string $property,
        public array $mapping,
        public bool $enableDiscriminatorIndex,
        public bool $enablePairIndex,
    ) {
    }
}
