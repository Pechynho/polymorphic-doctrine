<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;

final readonly class ExplicitPropertyMetadata implements PropertyMetadataInterface
{
    public function __construct(
        public string $property,
        /** @var array<string, ExplicitRelationMetadata> $mapping */
        public array $mapping,
        public string $referenceFqcn,
        public string $referencePath,
        public bool $enableDiscriminatorIndex,
    ) {}
}
