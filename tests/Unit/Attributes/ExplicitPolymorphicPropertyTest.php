<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Attributes;

use Pechynho\PolymorphicDoctrine\Attributes\ExplicitPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\User;
use PHPUnit\Framework\TestCase;

final class ExplicitPolymorphicPropertyTest extends TestCase
{
    public function testAttributeTargetIsProperty(): void
    {
        $ref = new \ReflectionClass(ExplicitPolymorphicProperty::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attrs);
        $attr = $attrs[0]->newInstance();
        self::assertSame(\Attribute::TARGET_PROPERTY, $attr->flags);
    }

    public function testDefaultValues(): void
    {
        $attr = new ExplicitPolymorphicProperty();

        self::assertSame([], $attr->mapping);
        self::assertNull($attr->idProperty);
        self::assertNull($attr->idPropertyType);
        self::assertNull($attr->onDelete);
        self::assertNull($attr->onUpdate);
        self::assertNull($attr->enableDiscriminatorIndex);
        self::assertNull($attr->enablePairIndex);
    }

    public function testCustomValues(): void
    {
        $attr = new ExplicitPolymorphicProperty(
            mapping: ['user' => User::class],
            idProperty: 'uuid',
            idPropertyType: 'string',
            onDelete: 'CASCADE',
            onUpdate: 'SET NULL',
            enableDiscriminatorIndex: false,
            enablePairIndex: false,
        );

        self::assertSame(['user' => User::class], $attr->mapping);
        self::assertSame('uuid', $attr->idProperty);
        self::assertSame('string', $attr->idPropertyType);
        self::assertSame('CASCADE', $attr->onDelete);
        self::assertSame('SET NULL', $attr->onUpdate);
        self::assertFalse($attr->enableDiscriminatorIndex);
        self::assertFalse($attr->enablePairIndex);
    }

    public function testReflectionOnFixtureEntity(): void
    {
        $ref = new \ReflectionProperty(Activity::class, 'subject');
        $attrs = $ref->getAttributes(ExplicitPolymorphicProperty::class);

        self::assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        self::assertCount(2, $instance->mapping);
        self::assertArrayHasKey('post', $instance->mapping);
        self::assertArrayHasKey('user', $instance->mapping);
    }
}
