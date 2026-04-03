<?php

namespace Pechynho\PolymorphicDoctrine\Exception;

class ReferenceResolutionException extends PolymorphicException
{
    public static function notResolvable(): self
    {
        return new self('Cannot get value: the reference is not resolvable.');
    }

    public static function nullValue(string $expectedClass): self
    {
        return new self(\sprintf('Cannot cast value to "%s": the value is null.', $expectedClass));
    }

    public static function typeMismatch(string $expectedClass, string $actualClass): self
    {
        return new self(\sprintf('Cannot cast value to "%s": the value is an instance of "%s".', $expectedClass, $actualClass));
    }

    public static function unexpectedPropertyValue(string $property, string $expectedClass, string $actualType): self
    {
        return new self(\sprintf('Expected "%s" to be an instance of "%s" or null, got "%s".', $property, $expectedClass, $actualType));
    }

    public static function discriminatorNotFound(string $discriminator, string $property): self
    {
        return new self(\sprintf('No relation mapping found for discriminator "%s" in property "%s".', $discriminator, $property));
    }

    public static function classNotMapped(string $class, string $property): self
    {
        return new self(\sprintf('No matching mapping found for class "%s" in property "%s".', $class, $property));
    }

    public static function managerNotFound(string $class): self
    {
        return new self(\sprintf('No Doctrine manager found for class "%s".', $class));
    }

    public static function unsupportedCombination(string $referenceType, string $metadataType): self
    {
        return new self(\sprintf('Combination of reference class "%s" and metadata class "%s" is not supported.', $referenceType, $metadataType));
    }

    public static function invalidIdType(string $property, string $class, string $actualType): self
    {
        return new self(\sprintf('ID property "%s" of class "%s" must be int or string, got %s.', $property, $class, $actualType));
    }

    public static function nullId(string $property, string $class): self
    {
        return new self(\sprintf('ID property "%s" is null for class "%s".', $property, $class));
    }
}
