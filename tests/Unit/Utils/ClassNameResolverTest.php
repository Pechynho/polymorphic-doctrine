<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Utils;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use PHPUnit\Framework\TestCase;

final class ClassNameResolverTest extends TestCase
{
    public function testResolveReturnsClassName(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(\stdClass::class);

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $resolver = new ClassNameResolver($registry);

        self::assertSame(\stdClass::class, $resolver->resolve(new \stdClass()));
    }

    public function testResolveThrowsForUnmanagedClass(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $resolver = new ClassNameResolver($registry);

        $this->expectException(\InvalidArgumentException::class);

        $resolver->resolve(new \stdClass());
    }

    public function testResolveCachesResults(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(\stdClass::class);

        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $resolver = new ClassNameResolver($registry);
        $entity = new \stdClass();

        $result1 = $resolver->resolve($entity);
        $result2 = $resolver->resolve($entity);

        self::assertSame(\stdClass::class, $result1);
        self::assertSame($result1, $result2);
    }

    public function testResetClearsCache(): void
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(\stdClass::class);

        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::exactly(2))->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $resolver = new ClassNameResolver($registry);
        $entity = new \stdClass();

        $result1 = $resolver->resolve($entity);
        $resolver->reset();
        $result2 = $resolver->resolve($entity);

        self::assertSame(\stdClass::class, $result1);
        self::assertSame(\stdClass::class, $result2);
    }
}
