<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Model\ClassMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use PHPUnit\Framework\TestCase;

final class ClassMetadataTest extends TestCase
{
    public function testConstructorStoresProperties(): void
    {
        $prop = new DynamicPropertyMetadata('subject', [], true, true);
        $metadata = new ClassMetadata(['subject' => $prop]);

        self::assertSame(['subject' => $prop], $metadata->properties);
    }

    public function testHasPropertyReturnsTrueForExistingProperty(): void
    {
        $prop = new DynamicPropertyMetadata('subject', [], true, true);
        $metadata = new ClassMetadata(['subject' => $prop]);

        self::assertTrue($metadata->hasProperty('subject'));
    }

    public function testHasPropertyReturnsFalseForMissingProperty(): void
    {
        $metadata = new ClassMetadata([]);

        self::assertFalse($metadata->hasProperty('nonexistent'));
    }

    public function testGetPropertyReturnsCorrectMetadata(): void
    {
        $prop = new DynamicPropertyMetadata('subject', [], true, true);
        $metadata = new ClassMetadata(['subject' => $prop]);

        self::assertSame($prop, $metadata->getProperty('subject'));
    }

    public function testGetPropertyThrowsForMissingProperty(): void
    {
        $metadata = new ClassMetadata([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property nonexistent does not exist.');

        $metadata->getProperty('nonexistent');
    }

    public function testEmptyPropertiesArray(): void
    {
        $metadata = new ClassMetadata([]);

        self::assertSame([], $metadata->properties);
        self::assertFalse($metadata->hasProperty('anything'));
    }

    public function testMultipleProperties(): void
    {
        $prop1 = new DynamicPropertyMetadata('subject', [], true, true);
        $prop2 = new DynamicPropertyMetadata('target', [], false, false);
        $metadata = new ClassMetadata(['subject' => $prop1, 'target' => $prop2]);

        self::assertTrue($metadata->hasProperty('subject'));
        self::assertTrue($metadata->hasProperty('target'));
        self::assertSame($prop1, $metadata->getProperty('subject'));
        self::assertSame($prop2, $metadata->getProperty('target'));
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(ClassMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
