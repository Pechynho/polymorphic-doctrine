<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Model;

use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use PHPUnit\Framework\TestCase;

final class ExplicitPropertyMetadataTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $relation = new ExplicitRelationMetadata(
            fqcn: 'App\\Entity\\Post',
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
            referenceFqcn: 'App\\AutoRef\\SubjectReference__abc123',
            referencePath: '/tmp/SubjectReference__abc123.php',
            enableDiscriminatorIndex: false,
        );

        self::assertSame('subject', $metadata->property);
        self::assertSame(['post' => $relation], $metadata->mapping);
        self::assertSame('App\\AutoRef\\SubjectReference__abc123', $metadata->referenceFqcn);
        self::assertSame('/tmp/SubjectReference__abc123.php', $metadata->referencePath);
        self::assertFalse($metadata->enableDiscriminatorIndex);
    }

    public function testImplementsPropertyMetadataInterface(): void
    {
        $metadata = new ExplicitPropertyMetadata('x', [], 'Foo', '/tmp/x', true);

        self::assertInstanceOf(PropertyMetadataInterface::class, $metadata);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(ExplicitPropertyMetadata::class);

        self::assertTrue($ref->isReadOnly());
    }
}
