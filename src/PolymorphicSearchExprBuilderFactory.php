<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\ORM\EntityManagerInterface;
use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final readonly class PolymorphicSearchExprBuilderFactory implements PolymorphicSearchExprBuilderFactoryInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private EntityManagerInterface $em,
        private ClassNameResolver $classNameResolver,
        private PropertyAccessorInterface $propertyAccessor,
    ) {}

    public function create(string $fqcn, string $property, string $alias): PolymorphicSearchExprBuilderInterface
    {
        return new PolymorphicSearchExprBuilder(
            fqcn: $fqcn,
            property: $property,
            alias: $alias,
            propertyMetadata: $this->metadataProvider->getPropertyMetadata($fqcn, $property),
            em: $this->em,
            classNameResolver: $this->classNameResolver,
            propertyAccessor: $this->propertyAccessor,
        );
    }
}
