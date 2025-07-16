<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

/**
 * @internal
 */
interface PolymorphicLocatorInterface
{
    /**
     * @return list<class-string>
     */
    public function getEntities(): array;

    public function clearCache(): void;
}
