<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface PolymorphicValueFactoryInterface
{
    /**
     * @template T of object
     *
     * @param class-string<T> $fqcn
     * @param T|null          $value
     *
     * @return PolymorphicValueInterface<T>
     */
    public function create(string $fqcn, string $property, ?object $value = null): PolymorphicValueInterface;
}
