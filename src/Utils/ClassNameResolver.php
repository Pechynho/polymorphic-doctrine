<?php

namespace Pechynho\PolymorphicDoctrine\Utils;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Service\ResetInterface;

final class ClassNameResolver implements ResetInterface
{
    /** @var array<class-string, class-string> */
    private array $resolvedClasses = [];

    public function __construct(
        private readonly ManagerRegistry $registry,
    ) {
    }

    public function reset(): void
    {
        $this->resolvedClasses = [];
    }

    /**
     * @return class-string
     */
    public function resolve(object $entity): string
    {
        $entityClass = $entity::class;
        if (isset($this->resolvedClasses[$entityClass])) {
            return $this->resolvedClasses[$entityClass];
        }
        $manager = $this->registry->getManagerForClass($entityClass);
        if (!$manager instanceof \Doctrine\Persistence\ObjectManager) {
            throw new \InvalidArgumentException(\sprintf('No manager found for class "%s".', $entityClass));
        }

        return $this->resolvedClasses[$entityClass] = $manager->getClassMetadata($entityClass)->getName();
    }
}
