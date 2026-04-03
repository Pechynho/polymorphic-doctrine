<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicPropertyValueResolverInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Exception\MappingException;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Webmozart\Assert\Assert;

final readonly class PolymorphicValueFactory implements PolymorphicValueFactoryInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private PolymorphicPropertyValueResolverInterface $propertyValueResolver,
    ) {
    }

    /**
     * @param class-string $fqcn
     */
    public function create(string $fqcn, string $property, ?object $value = null): PolymorphicValueInterface
    {
        $metadata = $this->metadataProvider->getPropertyMetadata($fqcn, $property);
        if ($metadata instanceof DynamicPropertyMetadata) {
            $reference = new DynamicPolymorphicReference();
            $reference->setResolver($this->propertyValueResolver);
            $reference->setMetadata($metadata);
            null === $value ? $reference->setNull() : $reference->update($value);

            return $reference;
        }
        if ($metadata instanceof ExplicitPropertyMetadata) {
            $reference = new $metadata->referenceFqcn();
            Assert::isInstanceOf($reference, PolymorphicReferenceInterface::class);
            $reference->setResolver($this->propertyValueResolver);
            $reference->setMetadata($metadata);
            null === $value ? $reference->setNull() : $reference->update($value);

            return $reference;
        }
        throw MappingException::unsupportedPropertyMetadataType($property, get_debug_type($metadata));
    }
}
