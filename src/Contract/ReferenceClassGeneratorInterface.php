<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;

/**
 * @internal
 */
interface ReferenceClassGeneratorInterface
{
    public function generate(): void;

    public function clear(): void;

    public function generateClass(ExplicitPropertyMetadata $property): void;
}
