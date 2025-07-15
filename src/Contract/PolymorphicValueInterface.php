<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

/**
 * @template T of object
 */
interface PolymorphicValueInterface
{
    public function isNull(): bool;

    public function isResolvable(): bool;

    public function isLoaded(): bool;

    public function setNull(): void;

    /**
     * @param T | null $value
     * @return void
     */
    public function update(?object $value): void;

    /**
     * @return T | null
     */
    public function getValue(): ?object;
}
