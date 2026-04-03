<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\PolymorphicSearchExprApplierFactory;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use PHPUnit\Framework\TestCase;

final class PolymorphicSearchExprApplierFactoryTest extends TestCase
{
    public function testCreateReturnsApplierInstance(): void
    {
        $builder = $this->createMock(PolymorphicSearchExprBuilderInterface::class);
        $builderFactory = $this->createMock(PolymorphicSearchExprBuilderFactoryInterface::class);
        $builderFactory->method('create')->willReturn($builder);

        $factory = new PolymorphicSearchExprApplierFactory($builderFactory);
        $factory->create(Comment::class, 'subject', 'c');

        $this->expectNotToPerformAssertions();
    }
}
