<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Attributes;

use Pechynho\PolymorphicDoctrine\Attributes\EntityWithPolymorphicRelations;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use PHPUnit\Framework\TestCase;

final class EntityWithPolymorphicRelationsTest extends TestCase
{
    public function testAttributeTargetIsClass(): void
    {
        $ref = new \ReflectionClass(EntityWithPolymorphicRelations::class);
        $attrs = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attrs);
        $attr = $attrs[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attr->flags);
    }

    public function testCanBeInstantiated(): void
    {
        $attr = new EntityWithPolymorphicRelations();

        self::assertSame(EntityWithPolymorphicRelations::class, $attr::class);
    }

    public function testReflectionOnAnnotatedClass(): void
    {
        $ref = new \ReflectionClass(Comment::class);
        $attrs = $ref->getAttributes(EntityWithPolymorphicRelations::class);

        self::assertCount(1, $attrs);
        self::assertSame(EntityWithPolymorphicRelations::class, $attrs[0]->getName());
    }
}
