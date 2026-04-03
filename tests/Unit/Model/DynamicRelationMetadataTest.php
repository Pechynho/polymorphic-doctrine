<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use PHPUnit\Framework\TestCase;

final class DynamicRelationMetadataTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $metadata = new DynamicRelationMetadata(
            fqcn: Post::class,
            idProperty: 'uuid',
        );

        self::assertSame(Post::class, $metadata->fqcn);
        self::assertSame('uuid', $metadata->idProperty);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(DynamicRelationMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
