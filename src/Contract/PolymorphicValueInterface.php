<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

interface PolymorphicValueInterface
{
    /**
     * @phpstan-assert-if-true null $this->getValue()
     *
     * @phpstan-assert-if-false !null $this->getValue()
     */
    public function isNull(): bool;

    public function isResolvable(): bool;

    public function isLoaded(): bool;

    public function setNull(): void;

    public function update(?object $value): void;

    public function getValue(): ?object;

    /**
     * @template U of object
     *
     * @param class-string<U> $fqcn
     *
     * @return U
     */
    public function getValueAs(string $fqcn): object;
}
