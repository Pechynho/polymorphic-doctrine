<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Trait\PolymorphicReferenceTrait;
use PHPUnit\Framework\TestCase;

final class DynamicPolymorphicReferenceTest extends TestCase
{
    public function testConstructorDefaultsToNull(): void
    {
        $ref = new DynamicPolymorphicReference();

        self::assertNull($ref->discriminator);
        self::assertNull($ref->id);
    }

    public function testConstructorAcceptsValues(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '42');

        self::assertSame('post', $ref->discriminator);
        self::assertSame('42', $ref->id);
    }

    public function testIsDoctrineEmbeddable(): void
    {
        $refClass = new \ReflectionClass(DynamicPolymorphicReference::class);
        $attrs = $refClass->getAttributes(ORM\Embeddable::class);

        self::assertCount(1, $attrs);
    }

    public function testImplementsPolymorphicReferenceInterface(): void
    {
        $ref = new DynamicPolymorphicReference();

        self::assertInstanceOf(PolymorphicReferenceInterface::class, $ref);
        self::assertInstanceOf(PolymorphicValueInterface::class, $ref);
    }

    public function testUsesPolymorphicReferenceTrait(): void
    {
        $traits = class_uses(DynamicPolymorphicReference::class);

        self::assertArrayHasKey(PolymorphicReferenceTrait::class, $traits ?: []);
    }

    public function testIsFinal(): void
    {
        $refClass = new \ReflectionClass(DynamicPolymorphicReference::class);

        self::assertTrue($refClass->isFinal());
    }
}
