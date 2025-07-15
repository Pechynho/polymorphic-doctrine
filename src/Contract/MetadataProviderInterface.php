<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

use Pechynho\PolymorphicDoctrine\Model\ClassMetadata;

interface MetadataProviderInterface
{
    /**
     * @return array<class-string, ClassMetadata>
     */
    public function getAllMetadata(): array;

    /**
     * @param class-string $fqcn
     */
    public function getClassMetadata(string $fqcn): ?ClassMetadata;

    /**
     * @param class-string $fqcn
     */
    public function getPropertyMetadata(string $fqcn, string $property): ?PropertyMetadataInterface;
}
