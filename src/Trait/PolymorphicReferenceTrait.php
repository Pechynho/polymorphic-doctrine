<?php

namespace Pechynho\PolymorphicDoctrine\Trait;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\PolymorphicPropertyValueResolver;

/**
 * @internal
 *
 * @property string|null $discriminator
 */
trait PolymorphicReferenceTrait
{
    private bool $__loaded = false;
    private ?object $__value = null;
    private ?PolymorphicPropertyValueResolver $__resolver = null;
    private ?PropertyMetadataInterface $__metadata = null;

    public function setResolver(PolymorphicPropertyValueResolver $resolver): void
    {
        $this->__resolver = $resolver;
    }

    public function setMetadata(PropertyMetadataInterface $metadata): void
    {
        $this->__metadata = $metadata;
    }

    private function resetCachedProps(): void
    {
        $this->__loaded = false;
        $this->__value = null;
    }

    private function loadData(): void
    {
        if ($this->__loaded) {
            return;
        }
        if (null === $this->discriminator) {
            $this->__loaded = true;
            $this->__value = null;

            return;
        }
        if (null === $this->__resolver || null === $this->__metadata) {
            throw new \LogicException('Cannot load data: missing resolver or metadata.');
        }
        $this->__value = $this->__resolver->loadProperty($this, $this->__metadata);
        $this->__loaded = true;
    }

    public function isNull(): bool
    {
        if ($this->__loaded && null === $this->__value) {
            return true;
        }
        $this->loadData();

        return null === $this->__value;
    }

    public function isResolvable(): bool
    {
        if ($this->__loaded) {
            return true;
        }
        if (null === $this->discriminator) {
            return true;
        }
        if (null === $this->__resolver || null === $this->__metadata) {
            return false;
        }
        try {
            $this->loadData();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isLoaded(): bool
    {
        return $this->__loaded;
    }

    public function setNull(): void
    {
        $this->resetCachedProps();
        $this->__loaded = true;
        $this->__value = null;
        if (null !== $this->__resolver && null !== $this->__metadata) {
            $this->__resolver->setProperty($this, $this->__metadata, null);
        }
    }

    public function update(?object $value): void
    {
        if (null === $value) {
            $this->setNull();

            return;
        }
        $resolver = $this->__resolver;
        $metadata = $this->__metadata;
        if (null === $resolver || null === $metadata) {
            throw new \LogicException('Cannot update value: missing resolver or metadata.');
        }
        $this->resetCachedProps();
        $this->__loaded = true;
        $this->__value = $value;
        $resolver->setProperty($this, $metadata, $value);
    }

    public function getValue(): ?object
    {
        if (!$this->isResolvable()) {
            throw new \RuntimeException('Cannot get value: the reference is not resolvable.');
        }
        $this->loadData();

        return $this->__value;
    }
}
