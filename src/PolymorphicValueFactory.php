<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Webmozart\Assert\Assert;

final readonly class PolymorphicValueFactory implements PolymorphicValueFactoryInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private PolymorphicPropertyValueResolver $propertyValueResolver,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $fqcn
     * @param T|null          $value
     *
     * @return PolymorphicValueInterface<T>
     */
    public function create(string $fqcn, string $property, ?object $value = null): PolymorphicValueInterface
    {
        $metadata = $this->metadataProvider->getPropertyMetadata($fqcn, $property);
        if ($metadata instanceof DynamicPropertyMetadata) {
            $reference = new DynamicPolymorphicReference();
            $reference->setResolver($this->propertyValueResolver);
            $reference->setMetadata($metadata);
            null === $value ? $reference->setNull() : $reference->update($value);

            /** @var PolymorphicValueInterface<T> */ // @phpstan-ignore varTag.nativeType
            return $reference;
        }
        if ($metadata instanceof ExplicitPropertyMetadata) {
            $reference = new $metadata->referenceFqcn();
            Assert::isInstanceOf($reference, PolymorphicReferenceInterface::class);
            $reference->setResolver($this->propertyValueResolver);
            $reference->setMetadata($metadata);
            null === $value ? $reference->setNull() : $reference->update($value);

            /** @var PolymorphicValueInterface<T> */ // @phpstan-ignore varTag.nativeType
            return $reference;
        }
        throw new \RuntimeException(\sprintf('Metadata for property "%s" in class "%s" is not supported. Got: %s', $property, $fqcn, get_debug_type($metadata)));
    }
}
