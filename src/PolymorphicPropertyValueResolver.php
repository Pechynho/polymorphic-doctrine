<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\Persistence\ManagerRegistry;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * @internal
 */
final readonly class PolymorphicPropertyValueResolver
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClassNameResolver $classNameResolver,
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
        throw new \RuntimeException(\sprintf('Combination of reference class "%s" and metadata class "%s" is not supported.', get_debug_type($reference), get_debug_type($metadata)));
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
        throw new \RuntimeException(\sprintf('Combination of reference class "%s" and metadata class "%s" is not supported.', get_debug_type($reference), get_debug_type($metadata)));
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
            throw new \RuntimeException(\sprintf('No relation mapping found for discriminator "%s" in property "%s".', $discriminator, $metadata->property));
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
                throw new \RuntimeException(\sprintf('ID property "%s" must be int or string for class "%s", got %s.', $relationMetadata->idProperty, $className, get_debug_type($id)));
            }
            $reference->discriminator = $discriminator;
            $reference->id = (string) $id;

            return;
        }
        throw new \RuntimeException(\sprintf('No matching discriminator found for class "%s" in property "%s".', $className, $metadata->property));
    }

    private function loadExplicitProperty(object $reference, ExplicitPropertyMetadata $metadata): ?object
    {
        $discriminator = $this->propertyAccessor->getValue($reference, 'discriminator');
        if (null === $discriminator) {
            return null;
        }
        if (!\is_string($discriminator)) {
            throw new \RuntimeException(\sprintf('Discriminator must be a string, got %s.', get_debug_type($discriminator)));
        }
        $relationMetadata = $metadata->mapping[$discriminator] ?? null;
        if (null === $relationMetadata) {
            throw new \RuntimeException(\sprintf('No relation mapping found for discriminator "%s" in property "%s".', $discriminator, $metadata->property));
        }
        $id = $this->propertyAccessor->getValue($reference, $relationMetadata->propertyName);
        if (null === $id) {
            return null;
        }
        if (!\is_int($id) && !\is_string($id)) {
            throw new \RuntimeException(\sprintf('ID must be int or string, got %s.', get_debug_type($id)));
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
                throw new \RuntimeException(\sprintf('ID property "%s" is null for class "%s".', $relationMetadata->idProperty, $className));
            }
            $found = true;
            $this->propertyAccessor->setValue($entity, 'discriminator', $discriminator);
            $this->propertyAccessor->setValue($entity, $relationMetadata->propertyName, $id);
        }
        if (!$found) {
            throw new \RuntimeException(\sprintf('No matching mapping found for class "%s" in property "%s".', $className, $metadata->property));
        }
    }

    /**
     * @param class-string $fqcn
     */
    private function find(string $fqcn, string|int $id): ?object
    {
        $manager = $this->managerRegistry->getManagerForClass($fqcn);
        if (!$manager instanceof \Doctrine\Persistence\ObjectManager) {
            throw new \RuntimeException(\sprintf('No manager found for class "%s".', $fqcn));
        }

        return $manager->find($fqcn, $id);
    }
}
