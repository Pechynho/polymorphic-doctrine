<?php

namespace Pechynho\PolymorphicDoctrine\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ExplicitPolymorphicProperty
{
    public function __construct(
        /**
         * @var array<string, class-string|array{
         *   fqcn: string,
         *   idProperty: string,
         *   idPropertyType: string,
         *   propertyName: string,
         *   columnName: string,
         *   onDelete: string,
         *   onUpdate: string,
         *   enablePairIndex?: bool,
         * }>
         */
        public array $mapping = [],
        public ?string $idProperty = null,
        public ?string $idPropertyType = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public ?bool $enableDiscriminatorIndex = null,
        public ?bool $enablePairIndex = null,
    ) {}
}
