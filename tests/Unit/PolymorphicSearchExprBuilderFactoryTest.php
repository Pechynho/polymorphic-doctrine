<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\PolymorphicSearchExprBuilderFactory;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class PolymorphicSearchExprBuilderFactoryTest extends TestCase
{
    public function testCreateReturnsBuilderInstance(): void
    {
        $metadata = new DynamicPropertyMetadata('subject', [], true, true);

        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getPropertyMetadata')->willReturn($metadata);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getExpressionBuilder')->willReturn(new Expr());

        $classNameResolver = $this->createMock(ClassNameResolver::class);

        $factory = new PolymorphicSearchExprBuilderFactory(
            $metadataProvider,
            $em,
            $classNameResolver,
            new PropertyAccessor(),
        );

        $result = $factory->create('App\\Entity\\Comment', 'subject', 'c');

        self::assertInstanceOf(PolymorphicSearchExprBuilderInterface::class, $result);
    }

    public function testCreateThrowsForMissingMetadata(): void
    {
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getPropertyMetadata')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $classNameResolver = $this->createMock(ClassNameResolver::class);

        $factory = new PolymorphicSearchExprBuilderFactory(
            $metadataProvider,
            $em,
            $classNameResolver,
            new PropertyAccessor(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No polymorphic metadata found');

        $factory->create('App\\Entity\\Unknown', 'subject', 'u');
    }
}
