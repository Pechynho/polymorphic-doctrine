<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicPropertyValueResolverInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\PolymorphicValueFactory;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use PHPUnit\Framework\TestCase;

final class PolymorphicValueFactoryTest extends TestCase
{
    public function testCreateDynamicWithNullValue(): void
    {
        $metadata = new DynamicPropertyMetadata(
            property: 'subject',
            mapping: ['post' => new DynamicRelationMetadata(fqcn: \stdClass::class, idProperty: 'id')],
            enableDiscriminatorIndex: true,
            enablePairIndex: true,
        );

        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getPropertyMetadata')->willReturn($metadata);

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);

        $factory = new PolymorphicValueFactory($metadataProvider, $resolver);
        $result = $factory->create(Comment::class, 'subject');

        self::assertInstanceOf(DynamicPolymorphicReference::class, $result);
        self::assertTrue($result->isNull());
    }

    public function testCreateDynamicWithEntity(): void
    {
        $entity = new \stdClass();
        $metadata = new DynamicPropertyMetadata(
            property: 'subject',
            mapping: ['post' => new DynamicRelationMetadata(fqcn: \stdClass::class, idProperty: 'id')],
            enableDiscriminatorIndex: true,
            enablePairIndex: true,
        );

        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getPropertyMetadata')->willReturn($metadata);

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);
        $resolver->expects(self::once())->method('setProperty');

        $factory = new PolymorphicValueFactory($metadataProvider, $resolver);
        $result = $factory->create(Comment::class, 'subject', $entity);

        self::assertInstanceOf(DynamicPolymorphicReference::class, $result);
        self::assertTrue($result->isLoaded());
    }

    public function testCreateThrowsForUnsupportedMetadata(): void
    {
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getPropertyMetadata')->willReturn(null);

        $resolver = $this->createMock(PolymorphicPropertyValueResolverInterface::class);

        $factory = new PolymorphicValueFactory($metadataProvider, $resolver);

        $this->expectException(\Pechynho\PolymorphicDoctrine\Exception\MappingException::class);
        $this->expectExceptionMessage('Unsupported property metadata type');

        $factory->create(Comment::class, 'subject');
    }
}
