<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use InvalidArgumentException;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;

final readonly class ClassMetadata
{
    public function __construct(
        /** @var array<string, PropertyMetadataInterface> */
        public array $properties,
    ) {}

    public function hasProperty(string $propertyName): bool
    {
        return isset($this->properties[$propertyName]);
    }

    public function getProperty(string $propertyName): PropertyMetadataInterface
    {
        if (!$this->hasProperty($propertyName)) {
            throw new InvalidArgumentException(sprintf('Property %s does not exist.', $propertyName));
        }
        return $this->properties[$propertyName];
    }
}
