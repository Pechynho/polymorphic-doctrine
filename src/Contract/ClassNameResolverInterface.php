<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface ClassNameResolverInterface
{
    /**
     * @return class-string
     */
    public function resolve(object $entity): string;
}
