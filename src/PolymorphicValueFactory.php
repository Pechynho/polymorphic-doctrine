<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use RuntimeException;
use Webmozart\Assert\Assert;

final readonly class PolymorphicValueFactory implements PolymorphicValueFactoryInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private PolymorphicPropertyValueResolver $propertyValueResolver,
    ) {}

    public function create(string $fqcn, string $property, ?object $value = null): PolymorphicValueInterface
    {
        $metadata = $this->metadataProvider->getPropertyMetadata($fqcn, $property);
        if ($metadata instanceof DynamicPropertyMetadata) {
            $reference = new DynamicPolymorphicReference();
            $reference->setResolver($this->propertyValueResolver);
            $reference->setMetadata($metadata);
            $value === null ? $reference->setNull() : $reference->update($value);
            return $reference;
        }
        if ($metadata instanceof ExplicitPropertyMetadata) {
            $reference = new $metadata->referenceFqcn();
            Assert::isInstanceOf($reference, PolymorphicReferenceInterface::class);
            $reference->setResolver($this->propertyValueResolver);
            $reference->setMetadata($metadata);
            $value === null ? $reference->setNull() : $reference->update($value);
            return $reference;
        }
        throw new RuntimeException(
            sprintf(
                'Metadata for property "%s" in class "%s" is not supported. Got: %s',
                $property,
                $fqcn,
                get_debug_type($metadata),
            ),
        );
    }
}
