<?php

namespace Pechynho\PolymorphicDoctrine\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class DynamicPolymorphicProperty
{
    public function __construct(
        /**
         * @var array<string, class-string|array{
         *      fqcn: string,
         *      idProperty: string,
         *  }>
         */
        public array $mapping = [],
        public ?string $idProperty = null,
        public ?bool $enableDiscriminatorIndex = null,
        public ?bool $enablePairIndex = null,
    ) {
    }
}
