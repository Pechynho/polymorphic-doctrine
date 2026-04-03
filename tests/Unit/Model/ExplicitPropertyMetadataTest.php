<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use PHPUnit\Framework\TestCase;

final class ExplicitPropertyMetadataTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $relation = new ExplicitRelationMetadata(
            fqcn: Post::class,
            idProperty: 'id',
            idPropertyType: 'int',
            propertyName: 'postId',
            columnName: 'post_id',
            onDelete: 'CASCADE',
            onUpdate: 'RESTRICT',
            enablePairIndex: true,
        );
        $metadata = new ExplicitPropertyMetadata(
            property: 'subject',
            mapping: ['post' => $relation],
            referenceFqcn: Post::class,
            referencePath: '/tmp/SubjectReference__abc123.php',
            enableDiscriminatorIndex: false,
        );

        self::assertSame('subject', $metadata->property);
        self::assertSame(['post' => $relation], $metadata->mapping);
        self::assertSame(Post::class, $metadata->referenceFqcn);
        self::assertSame('/tmp/SubjectReference__abc123.php', $metadata->referencePath);
        self::assertFalse($metadata->enableDiscriminatorIndex);
    }

    public function testImplementsPropertyMetadataInterface(): void
    {
        $metadata = new ExplicitPropertyMetadata('x', [], Post::class, '/tmp/x', true);

        self::assertSame('x', $metadata->property);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(ExplicitPropertyMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
