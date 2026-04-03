<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\Attributes\DynamicPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Attributes\ExplicitPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicLocatorInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;
use Pechynho\PolymorphicDoctrine\MetadataProvider;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\MixedEntity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\PlainEntity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class MetadataProviderTest extends TestCase
{
    private MetadataProvider $provider;
    private \PHPUnit\Framework\MockObject\MockObject&CacheInterface $cache;
    private \PHPUnit\Framework\MockObject\MockObject&PolymorphicLocatorInterface $locator;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->locator = $this->createMock(PolymorphicLocatorInterface::class);

        $this->provider = new MetadataProvider(
            cache: $this->cache,
            polymorphicLocator: $this->locator,
            referencesDir: '/tmp/refs',
            referencesNamespace: 'Pechynho\\PolymorphicDoctrine\\Tests\\AutoRef',
            environment: 'dev',
        );
    }

    public function testGetClassMetadataReturnsNullForPlainEntity(): void
    {
        $result = $this->provider->getClassMetadata(PlainEntity::class);

        self::assertNull($result);
    }

    public function testGetClassMetadataReturnsDynamicMetadata(): void
    {
        $result = $this->provider->getClassMetadata(Comment::class);
        self::assertNotNull($result);

        self::assertTrue($result->hasProperty('subject'));
        self::assertInstanceOf(DynamicPropertyMetadata::class, $result->getProperty('subject'));
    }

    public function testGetClassMetadataReturnsExplicitMetadata(): void
    {
        $result = $this->provider->getClassMetadata(Activity::class);
        self::assertNotNull($result);

        self::assertTrue($result->hasProperty('subject'));
        self::assertInstanceOf(ExplicitPropertyMetadata::class, $result->getProperty('subject'));
    }

    public function testDynamicPropertyMetadataHasCorrectMapping(): void
    {
        $result = $this->provider->getClassMetadata(Comment::class);
        self::assertNotNull($result);
        /** @var DynamicPropertyMetadata $prop */
        $prop = $result->getProperty('subject');

        self::assertArrayHasKey('post', $prop->mapping);
        self::assertArrayHasKey('user', $prop->mapping);
        self::assertSame(Post::class, $prop->mapping['post']->fqcn);
        self::assertSame(User::class, $prop->mapping['user']->fqcn);
        self::assertSame('id', $prop->mapping['post']->idProperty);
    }

    public function testDynamicPropertyMetadataDefaultIndexes(): void
    {
        $result = $this->provider->getClassMetadata(Comment::class);
        self::assertNotNull($result);
        /** @var DynamicPropertyMetadata $prop */
        $prop = $result->getProperty('subject');

        self::assertTrue($prop->enableDiscriminatorIndex);
        self::assertTrue($prop->enablePairIndex);
    }

    public function testExplicitPropertyMetadataHasCorrectMapping(): void
    {
        $result = $this->provider->getClassMetadata(Activity::class);
        self::assertNotNull($result);
        /** @var ExplicitPropertyMetadata $prop */
        $prop = $result->getProperty('subject');

        self::assertArrayHasKey('post', $prop->mapping);
        self::assertArrayHasKey('user', $prop->mapping);
        self::assertSame(Post::class, $prop->mapping['post']->fqcn);
        self::assertSame(User::class, $prop->mapping['user']->fqcn);
    }

    public function testExplicitPropertyMetadataDefaults(): void
    {
        $result = $this->provider->getClassMetadata(Activity::class);
        self::assertNotNull($result);
        /** @var ExplicitPropertyMetadata $prop */
        $prop = $result->getProperty('subject');

        $postRelation = $prop->mapping['post'];
        self::assertSame('id', $postRelation->idProperty);
        self::assertSame('int', $postRelation->idPropertyType);
        self::assertSame('RESTRICT', $postRelation->onDelete);
        self::assertSame('RESTRICT', $postRelation->onUpdate);
        self::assertSame('postId', $postRelation->propertyName);
        self::assertSame('post_id', $postRelation->columnName);
        self::assertTrue($postRelation->enablePairIndex);
    }

    public function testExplicitReferenceFqcnContainsNamespace(): void
    {
        $result = $this->provider->getClassMetadata(Activity::class);
        self::assertNotNull($result);
        /** @var ExplicitPropertyMetadata $prop */
        $prop = $result->getProperty('subject');

        self::assertStringStartsWith('Pechynho\\PolymorphicDoctrine\\Tests\\AutoRef\\', $prop->referenceFqcn);
    }

    public function testExplicitReferencePathEndsWithPhp(): void
    {
        $result = $this->provider->getClassMetadata(Activity::class);
        self::assertNotNull($result);
        /** @var ExplicitPropertyMetadata $prop */
        $prop = $result->getProperty('subject');

        self::assertStringEndsWith('.php', $prop->referencePath);
        self::assertStringStartsWith('/tmp/refs', $prop->referencePath);
    }

    public function testGetAllMetadataReturnsAllAnnotatedEntities(): void
    {
        $this->locator->method('getEntities')->willReturn([
            Comment::class,
            Activity::class,
            MixedEntity::class,
            PlainEntity::class,
        ]);

        $result = $this->provider->getAllMetadata();

        self::assertArrayHasKey(Comment::class, $result);
        self::assertArrayHasKey(Activity::class, $result);
        self::assertArrayHasKey(MixedEntity::class, $result);
        self::assertArrayNotHasKey(PlainEntity::class, $result);
    }

    public function testGetAllMetadataCachesResult(): void
    {
        $this->locator->expects(self::once())->method('getEntities')->willReturn([Comment::class]);

        $result1 = $this->provider->getAllMetadata();
        $result2 = $this->provider->getAllMetadata();

        self::assertSame($result1, $result2);
    }

    public function testDualAttributeOnSamePropertyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot have both');

        $this->provider->getClassMetadata(DualAttributeEntity::class);
    }

    public function testReservedDiscriminatorKeyThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('reserved');

        $this->provider->getClassMetadata(ReservedDiscriminatorEntity::class);
    }

    public function testGetPropertyMetadataReturnsCorrectProperty(): void
    {
        $result = $this->provider->getPropertyMetadata(Comment::class, 'subject');

        self::assertInstanceOf(DynamicPropertyMetadata::class, $result);
    }

    public function testGetPropertyMetadataReturnsNullForPlainEntity(): void
    {
        $result = $this->provider->getPropertyMetadata(PlainEntity::class, 'id');

        self::assertNull($result);
    }

    public function testResetClearsInternalCaches(): void
    {
        $this->provider->getClassMetadata(Comment::class);
        $this->provider->reset();

        // After reset, it should re-read from reflection (no exception = works)
        $result = $this->provider->getClassMetadata(Comment::class);
        self::assertNotNull($result);
    }

    public function testGetClassMetadataCachesInProd(): void
    {
        $this->cache->expects(self::once())
            ->method('get')
            ->willReturnCallback(static fn (string $key, callable $callback) => $callback());

        $prodProvider = new MetadataProvider(
            cache: $this->cache,
            polymorphicLocator: $this->locator,
            referencesDir: '/tmp/refs',
            referencesNamespace: 'Pechynho\\PolymorphicDoctrine\\Tests\\AutoRef',
            environment: 'prod',
        );

        $prodProvider->getClassMetadata(Comment::class);
    }

    public function testGetClassMetadataUsesReflectionInDev(): void
    {
        $this->cache->expects(self::never())->method('get');

        $result = $this->provider->getClassMetadata(Comment::class);
        self::assertNotNull($result);
    }

    public function testMixedEntityHasBothPropertyTypes(): void
    {
        $result = $this->provider->getClassMetadata(MixedEntity::class);
        self::assertNotNull($result);

        self::assertInstanceOf(DynamicPropertyMetadata::class, $result->getProperty('dynamicRef'));
        self::assertInstanceOf(ExplicitPropertyMetadata::class, $result->getProperty('explicitRef'));
    }

    public function testExplicitMappingWithStringIdType(): void
    {
        $result = $this->provider->getClassMetadata(MixedEntity::class);
        self::assertNotNull($result);
        /** @var ExplicitPropertyMetadata $prop */
        $prop = $result->getProperty('explicitRef');
        $tagRelation = $prop->mapping['tag'];

        self::assertSame('string', $tagRelation->idPropertyType);
    }
}

// Fixture: entity with both attributes on same property
class DualAttributeEntity
{
    #[DynamicPolymorphicProperty(mapping: ['a' => Post::class])]
    #[ExplicitPolymorphicProperty(mapping: ['a' => Post::class])]
    public PolymorphicValueInterface $subject;
}

// Fixture: entity with reserved discriminator key
class ReservedDiscriminatorEntity
{
    #[ExplicitPolymorphicProperty(mapping: ['discriminator' => Post::class])]
    public PolymorphicValueInterface $subject;
}
