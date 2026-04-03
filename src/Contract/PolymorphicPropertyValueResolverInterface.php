<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface PolymorphicPropertyValueResolverInterface
{
    public function loadProperty(object $reference, PropertyMetadataInterface $metadata): ?object;

    public function setProperty(object $reference, PropertyMetadataInterface $metadata, ?object $value): void;
}
