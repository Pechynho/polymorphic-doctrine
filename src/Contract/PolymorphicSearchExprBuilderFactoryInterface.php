<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface PolymorphicSearchExprBuilderFactoryInterface
{
    /**
     * @param class-string $fqcn
     */
    public function create(string $fqcn, string $property, string $alias): PolymorphicSearchExprBuilderInterface;
}
