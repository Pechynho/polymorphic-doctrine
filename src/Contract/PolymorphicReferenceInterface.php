<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

/**
 * @internal
 */
interface PolymorphicReferenceInterface extends PolymorphicValueInterface
{
    public function setResolver(PolymorphicPropertyValueResolverInterface $resolver): void;

    public function setMetadata(PropertyMetadataInterface $metadata): void;
}
