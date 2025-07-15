<?php

namespace Pechynho\PolymorphicDoctrine\Trait;

use LogicException;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\PolymorphicPropertyValueResolver;
use RuntimeException;
use Throwable;

/**
 * @internal
 * @property string|null $discriminator
 */
trait PolymorphicReferenceTrait
{
    private bool $__isResolvable;
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
        unset($this->__isResolvable, $this->__value);
    }

    private function loadData(): void
    {
        if (isset($this->__value)) {
            return;
        }
        if ($this->discriminator === null) {
            $this->__value = null;
            return;
        }
        if ($this->__resolver === null || $this->__metadata === null) {
            throw new LogicException('Cannot load data: missing resolver or metadata.');
        }
        $this->__value = $this->__resolver->loadProperty($this, $this->__metadata);
    }

    public function isNull(): bool
    {
        if (isset($this->__isResolvable) && !$this->__isResolvable) {
            throw new RuntimeException('Cannot get value: the reference is not resolvable.');
        }
        $this->loadData();
        return isset($this->__value) && $this->__value === null;
    }

    public function isResolvable(): bool
    {
        if (isset($this->__isResolvable)) {
            return $this->__isResolvable;
        }
        if (isset($this->__value)) {
            return $this->__isResolvable = true;
        }
        if ($this->discriminator === null) {
            return $this->__isResolvable = true;
        }
        if ($this->__resolver === null || $this->__metadata === null) {
            return $this->__isResolvable = false;
        }
        try {
            $this->loadData();
            return $this->__isResolvable = true;
        } catch (Throwable) {
            return $this->__isResolvable = false;
        }
    }

    public function isLoaded(): bool
    {
        return isset($this->__value);
    }

    public function setNull(): void
    {
        $this->resetCachedProps();
        $this->__value = null;
        $this->__resolver->setProperty($this, $this->__metadata, null);
    }

    public function update(?object $value): void
    {
        if ($value === null) {
            $this->setNull();
            return;
        }
        $this->resetCachedProps();
        $this->__resolver->setProperty($this, $this->__metadata, $value);
    }

    public function getValue(): ?object
    {
        if (isset($this->__isResolvable) && !$this->__isResolvable) {
            throw new RuntimeException('Cannot get value: the reference is not resolvable.');
        }
        $this->loadData();
        return $this->__value;
    }
}
