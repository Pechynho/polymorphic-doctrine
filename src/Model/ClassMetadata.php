<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;

final readonly class ClassMetadata
{
    /**
     * @param array<string, PropertyMetadataInterface> $properties
     */
    public function __construct(
        public array $properties,
    ) {
    }

    public function hasProperty(string $propertyName): bool
    {
        return isset($this->properties[$propertyName]);
    }

    public function getProperty(string $propertyName): PropertyMetadataInterface
    {
        if (!$this->hasProperty($propertyName)) {
            throw new \InvalidArgumentException(\sprintf('Property %s does not exist.', $propertyName));
        }

        return $this->properties[$propertyName];
    }
}
