<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use PHPUnit\Framework\TestCase;

final class ExplicitRelationMetadataTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $metadata = new ExplicitRelationMetadata(
            fqcn: 'App\\Entity\\User',
            idProperty: 'uuid',
            idPropertyType: 'string',
            propertyName: 'userId',
            columnName: 'user_id',
            onDelete: 'SET NULL',
            onUpdate: 'CASCADE',
            enablePairIndex: false,
        );

        self::assertSame('App\\Entity\\User', $metadata->fqcn);
        self::assertSame('uuid', $metadata->idProperty);
        self::assertSame('string', $metadata->idPropertyType);
        self::assertSame('userId', $metadata->propertyName);
        self::assertSame('user_id', $metadata->columnName);
        self::assertSame('SET NULL', $metadata->onDelete);
        self::assertSame('CASCADE', $metadata->onUpdate);
        self::assertFalse($metadata->enablePairIndex);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(ExplicitRelationMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
