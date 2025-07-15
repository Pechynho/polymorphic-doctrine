<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface PolymorphicSearchExprApplierFactoryInterface
{
    /**
     * @param class-string $fqcn
     */
    public function create(string $fqcn, string $property, string $alias): PolymorphicSearchExprApplierInterface;
}
