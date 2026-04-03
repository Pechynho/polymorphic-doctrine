<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\Persistence\ManagerRegistry;
use Pechynho\PolymorphicDoctrine\Contract\ClassNameResolverInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicPropertyValueResolverInterface;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Exception\ReferenceResolutionException;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @internal
 */
final readonly class PolymorphicPropertyValueResolver implements PolymorphicPropertyValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClassNameResolverInterface $classNameResolver,
        private PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function loadProperty(object $reference, PropertyMetadataInterface $metadata): ?object
    {
        if ($reference instanceof DynamicPolymorphicReference && $metadata instanceof DynamicPropertyMetadata) {
            return $this->loadDynamicProperty($reference, $metadata);
        }
        if ($metadata instanceof ExplicitPropertyMetadata && $reference instanceof $metadata->referenceFqcn) {
            return $this->loadExplicitProperty($reference, $metadata);
        }
        throw ReferenceResolutionException::unsupportedCombination(get_debug_type($reference), get_debug_type($metadata));
    }

    public function setProperty(object $reference, PropertyMetadataInterface $metadata, ?object $value): void
    {
        if ($reference instanceof DynamicPolymorphicReference && $metadata instanceof DynamicPropertyMetadata) {
            $this->setDynamicProperty($reference, $metadata, $value);

            return;
        }
        if ($metadata instanceof ExplicitPropertyMetadata && $reference instanceof $metadata->referenceFqcn) {
            $this->setExplicitProperty($reference, $metadata, $value);

            return;
        }
        throw ReferenceResolutionException::unsupportedCombination(get_debug_type($reference), get_debug_type($metadata));
    }

    private function loadDynamicProperty(
        DynamicPolymorphicReference $reference,
        DynamicPropertyMetadata $metadata,
    ): ?object {
        $discriminator = $reference->discriminator;
        $id = $reference->id;
        if (null === $discriminator || null === $id) {
            return null;
        }
        $relationMapping = $metadata->mapping[$discriminator] ?? null;
        if (null === $relationMapping) {
            throw ReferenceResolutionException::discriminatorNotFound($discriminator, $metadata->property);
        }

        return $this->find($relationMapping->fqcn, $id);
    }

    private function setDynamicProperty(
        DynamicPolymorphicReference $reference,
        DynamicPropertyMetadata $metadata,
        ?object $value,
    ): void {
        if (null === $value) {
            $reference->id = null;
            $reference->discriminator = null;

            return;
        }
        $className = $this->classNameResolver->resolve($value);
        foreach ($metadata->mapping as $discriminator => $relationMetadata) {
            if ($className !== $relationMetadata->fqcn) {
                continue;
            }
            $id = $this->propertyAccessor->getValue($value, $relationMetadata->idProperty);
            if (!\is_int($id) && !\is_string($id)) {
                throw ReferenceResolutionException::invalidIdType($relationMetadata->idProperty, $className, get_debug_type($id));
            }
            $reference->discriminator = $discriminator;
            $reference->id = (string) $id;

            return;
        }
        throw ReferenceResolutionException::classNotMapped($className, $metadata->property);
    }

    private function loadExplicitProperty(object $reference, ExplicitPropertyMetadata $metadata): ?object
    {
        $discriminator = $this->propertyAccessor->getValue($reference, 'discriminator');
        if (null === $discriminator) {
            return null;
        }
        if (!\is_string($discriminator)) {
            throw ReferenceResolutionException::invalidIdType('discriminator', $metadata->property, get_debug_type($discriminator));
        }
        $relationMetadata = $metadata->mapping[$discriminator] ?? null;
        if (null === $relationMetadata) {
            throw ReferenceResolutionException::discriminatorNotFound($discriminator, $metadata->property);
        }
        $id = $this->propertyAccessor->getValue($reference, $relationMetadata->propertyName);
        if (null === $id) {
            return null;
        }
        if (!\is_int($id) && !\is_string($id)) {
            throw ReferenceResolutionException::invalidIdType($relationMetadata->propertyName, $metadata->property, get_debug_type($id));
        }

        return $this->find($relationMetadata->fqcn, $id);
    }

    private function setExplicitProperty(object $entity, ExplicitPropertyMetadata $metadata, ?object $value): void
    {
        if (null === $value) {
            $this->propertyAccessor->setValue($entity, 'discriminator', null);
            foreach ($metadata->mapping as $relationMetadata) {
                $this->propertyAccessor->setValue($entity, $relationMetadata->propertyName, null);
            }

            return;
        }
        $className = $this->classNameResolver->resolve($value);
        $found = false;
        foreach ($metadata->mapping as $discriminator => $relationMetadata) {
            if ($className !== $relationMetadata->fqcn) {
                $this->propertyAccessor->setValue($entity, $relationMetadata->propertyName, null);
                continue;
            }
            $id = $this->propertyAccessor->getValue($value, $relationMetadata->idProperty);
            if (null === $id) {
                throw ReferenceResolutionException::nullId($relationMetadata->idProperty, $className);
            }
            $found = true;
            $this->propertyAccessor->setValue($entity, 'discriminator', $discriminator);
            $this->propertyAccessor->setValue($entity, $relationMetadata->propertyName, $id);
        }
        if (!$found) {
            throw ReferenceResolutionException::classNotMapped($className, $metadata->property);
        }
    }

    /**
     * @param class-string $fqcn
     */
    private function find(string $fqcn, string|int $id): ?object
    {
        $manager = $this->managerRegistry->getManagerForClass($fqcn);
        if (!$manager instanceof \Doctrine\Persistence\ObjectManager) {
            throw ReferenceResolutionException::managerNotFound($fqcn);
        }

        return $manager->find($fqcn, $id);
    }
}
