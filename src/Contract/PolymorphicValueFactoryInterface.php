<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface PolymorphicValueFactoryInterface
{
    /**
     * @param class-string $fqcn
     */
    public function create(string $fqcn, string $property, ?object $value = null): PolymorphicValueInterface;
}
