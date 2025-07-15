<?php

namespace Pechynho\PolymorphicDoctrine\Model;

final readonly class ExplicitRelationMetadata
{
    /**
     * @param class-string $fqcn
     */
    public function __construct(
        public string $fqcn,
        public string $idProperty,
        public string $idPropertyType,
        public string $propertyName,
        public string $columnName,
        public string $onDelete,
        public string $onUpdate,
        public bool $enablePairIndex,
    ) {}
}
