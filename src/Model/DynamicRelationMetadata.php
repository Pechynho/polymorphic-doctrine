<?php

namespace Pechynho\PolymorphicDoctrine\Model;

final readonly class DynamicRelationMetadata
{
    public function __construct(
        /** @var class-string $fqcn */
        public string $fqcn,
        public string $idProperty,
    ) {}
}
