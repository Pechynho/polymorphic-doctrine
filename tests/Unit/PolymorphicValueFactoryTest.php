<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\PolymorphicPropertyValueResolver;
use Pechynho\PolymorphicDoctrine\PolymorphicValueFactory;
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

        $resolver = $this->createMock(PolymorphicPropertyValueResolver::class);

        $factory = new PolymorphicValueFactory($metadataProvider, $resolver);
        $result = $factory->create('App\\Entity\\Comment', 'subject');

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

        $resolver = $this->createMock(PolymorphicPropertyValueResolver::class);
        $resolver->expects(self::once())->method('setProperty');

        $factory = new PolymorphicValueFactory($metadataProvider, $resolver);
        $result = $factory->create('App\\Entity\\Comment', 'subject', $entity);

        self::assertInstanceOf(DynamicPolymorphicReference::class, $result);
        self::assertTrue($result->isLoaded());
    }

    public function testCreateThrowsForUnsupportedMetadata(): void
    {
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->method('getPropertyMetadata')->willReturn(null);

        $resolver = $this->createMock(PolymorphicPropertyValueResolver::class);

        $factory = new PolymorphicValueFactory($metadataProvider, $resolver);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not supported');

        $factory->create('App\\Entity\\Comment', 'subject');
    }
}
