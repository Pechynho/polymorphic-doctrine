<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use PHPUnit\Framework\TestCase;

final class DynamicRelationMetadataTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $metadata = new DynamicRelationMetadata(
            fqcn: 'App\\Entity\\Post',
            idProperty: 'uuid',
        );

        self::assertSame('App\\Entity\\Post', $metadata->fqcn);
        self::assertSame('uuid', $metadata->idProperty);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(DynamicRelationMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
