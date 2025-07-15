<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

use Pechynho\PolymorphicDoctrine\PolymorphicPropertyValueResolver;

/**
 * @internal
 */
interface PolymorphicReferenceInterface extends PolymorphicValueInterface
{
    public function setResolver(PolymorphicPropertyValueResolver $resolver): void;

    public function setMetadata(PropertyMetadataInterface $metadata): void;
}
