<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Attributes;

use Pechynho\PolymorphicDoctrine\Attributes\DynamicPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use PHPUnit\Framework\TestCase;

final class DynamicPolymorphicPropertyTest extends TestCase
{
    public function testAttributeTargetIsProperty(): void
    {
        $ref = new \ReflectionClass(DynamicPolymorphicProperty::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attrs);
        $attr = $attrs[0]->newInstance();
        self::assertSame(\Attribute::TARGET_PROPERTY, $attr->flags);
    }

    public function testDefaultValues(): void
    {
        $attr = new DynamicPolymorphicProperty();

        self::assertSame([], $attr->mapping);
        self::assertNull($attr->idProperty);
        self::assertNull($attr->enableDiscriminatorIndex);
        self::assertNull($attr->enablePairIndex);
    }

    public function testCustomValues(): void
    {
        $attr = new DynamicPolymorphicProperty(
            mapping: ['post' => 'App\\Entity\\Post'],
            idProperty: 'uuid',
            enableDiscriminatorIndex: false,
            enablePairIndex: true,
        );

        self::assertSame(['post' => 'App\\Entity\\Post'], $attr->mapping);
        self::assertSame('uuid', $attr->idProperty);
        self::assertFalse($attr->enableDiscriminatorIndex);
        self::assertTrue($attr->enablePairIndex);
    }

    public function testMappingWithArrayValues(): void
    {
        $attr = new DynamicPolymorphicProperty(
            mapping: ['post' => ['fqcn' => 'App\\Entity\\Post', 'idProperty' => 'uuid']],
        );

        self::assertSame(['fqcn' => 'App\\Entity\\Post', 'idProperty' => 'uuid'], $attr->mapping['post']);
    }

    public function testReflectionOnFixtureEntity(): void
    {
        $ref = new \ReflectionProperty(Comment::class, 'subject');
        $attrs = $ref->getAttributes(DynamicPolymorphicProperty::class);

        self::assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        self::assertCount(2, $instance->mapping);
        self::assertArrayHasKey('post', $instance->mapping);
        self::assertArrayHasKey('user', $instance->mapping);
    }
}
