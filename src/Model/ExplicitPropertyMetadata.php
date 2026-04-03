<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;

final readonly class ExplicitPropertyMetadata implements PropertyMetadataInterface
{
    /**
     * @param array<string, ExplicitRelationMetadata> $mapping
     * @param class-string                            $referenceFqcn
     */
    public function __construct(
        public string $property,
        public array $mapping,
        public string $referenceFqcn,
        public string $referencePath,
        public bool $enableDiscriminatorIndex,
    ) {
    }
}
