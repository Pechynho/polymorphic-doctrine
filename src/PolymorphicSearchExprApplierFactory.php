<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprApplierFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprApplierInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderFactoryInterface;

final readonly class PolymorphicSearchExprApplierFactory implements PolymorphicSearchExprApplierFactoryInterface
{
    public function __construct(
        private PolymorphicSearchExprBuilderFactoryInterface $searchExprBuilderFactory,
    ) {}

    public function create(string $fqcn, string $property, string $alias): PolymorphicSearchExprApplierInterface
    {
        return new PolymorphicSearchExprApplier($this->searchExprBuilderFactory->create($fqcn, $property, $alias));
    }
}
