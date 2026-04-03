<?php

namespace Pechynho\PolymorphicDoctrine\Exception;

class MappingException extends PolymorphicException
{
    public static function duplicateAttributes(string $property, string $class): self
    {
        return new self(\sprintf('Property "%s" in class "%s" cannot have both DynamicPolymorphicProperty and ExplicitPolymorphicProperty attributes. Use only one of them.', $property, $class));
    }

    public static function duplicateProperty(string $property, string $class): self
    {
        return new self(\sprintf('Property "%s" is already defined in class "%s".', $property, $class));
    }

    public static function reservedDiscriminator(string $discriminator, string $class, string $property): self
    {
        return new self(\sprintf('Discriminator "%s" is reserved and cannot be used in class "%s" property "%s".', $discriminator, $class, $property));
    }

    public static function unsupportedPropertyMetadataType(string $typeOrProperty, ?string $type = null): self
    {
        if (null !== $type) {
            return new self(\sprintf('Unsupported property metadata type for "%s": %s.', $typeOrProperty, $type));
        }

        return new self(\sprintf('Unsupported property metadata type: %s.', $typeOrProperty));
    }

    public static function unsupportedIdPropertyType(string $type): self
    {
        return new self(\sprintf('Unsupported id property type: %s. Supported types: int, string.', $type));
    }

    public static function invalidReferenceFqcn(string $fqcn): self
    {
        return new self(\sprintf('Invalid FQCN "%s": must contain a namespace.', $fqcn));
    }

    public static function referenceClassNotGenerated(string $path): self
    {
        return new self(\sprintf('Reference class path "%s" does not exist. Run "pechynho:polymorphic-doctrine:generate-reference-classes" command to generate it.', $path));
    }

    public static function classMetadataNotFound(string $class): self
    {
        return new self(\sprintf('Class metadata for "%s" not found.', $class));
    }
}
