<?php

namespace Pechynho\PolymorphicDoctrine\Model;

final readonly class DynamicRelationMetadata
{
    /**
     * @param class-string $fqcn
     */
    public function __construct(
        public string $fqcn,
        public string $idProperty,
    ) {
    }
}
