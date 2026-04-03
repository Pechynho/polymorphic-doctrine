<?php

namespace Pechynho\PolymorphicDoctrine\Exception;

class InvalidSearchArgumentException extends PolymorphicException
{
    public static function emptyClassList(): self
    {
        return new self('At least one class name must be provided for polymorphic search.');
    }

    public static function emptyEntityList(): self
    {
        return new self('At least one entity must be provided for polymorphic search.');
    }

    public static function metadataNotFound(string $property, string $class): self
    {
        return new self(\sprintf('No polymorphic metadata found for property "%s" in class "%s".', $property, $class));
    }

    public static function discriminatorNotFound(string $class, string $property, string $entityClass): self
    {
        return new self(\sprintf('No discriminator value found for class "%s" in property "%s" of "%s".', $class, $property, $entityClass));
    }

    public static function idNotFound(string $class, string $property, string $entityClass): self
    {
        return new self(\sprintf('No ID found for class "%s" in property "%s" of "%s".', $class, $property, $entityClass));
    }

    public static function mappingNotFound(string $class, string $property, string $entityClass): self
    {
        return new self(\sprintf('No mapping found for class "%s" in property "%s" of "%s".', $class, $property, $entityClass));
    }
}
