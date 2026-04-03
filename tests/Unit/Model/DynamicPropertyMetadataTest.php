<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use PHPUnit\Framework\TestCase;

final class DynamicPropertyMetadataTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $relation = new DynamicRelationMetadata(Post::class, 'id');
        $metadata = new DynamicPropertyMetadata(
            property: 'subject',
            mapping: ['post' => $relation],
            enableDiscriminatorIndex: true,
            enablePairIndex: false,
        );

        self::assertSame('subject', $metadata->property);
        self::assertSame(['post' => $relation], $metadata->mapping);
        self::assertTrue($metadata->enableDiscriminatorIndex);
        self::assertFalse($metadata->enablePairIndex);
    }

    public function testImplementsPropertyMetadataInterface(): void
    {
        $metadata = new DynamicPropertyMetadata('x', [], true, true);

        self::assertSame('x', $metadata->property);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(DynamicPropertyMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
