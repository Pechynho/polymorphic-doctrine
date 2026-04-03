<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Integration;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\AbstractKernelTestCase;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\MixedEntity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\PlainEntity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\User;

final class MetadataProviderIntegrationTest extends AbstractKernelTestCase
{
    private MetadataProviderInterface $metadataProvider;

    protected function setUp(): void
    {
        $service = self::getContainer()->get('test.'.MetadataProviderInterface::class);
        self::assertInstanceOf(MetadataProviderInterface::class, $service);
        $this->metadataProvider = $service;
    }

    public function testGetAllMetadataFindsFixtureEntities(): void
    {
        $all = $this->metadataProvider->getAllMetadata();

        self::assertArrayHasKey(Comment::class, $all);
        self::assertArrayHasKey(Activity::class, $all);
        self::assertArrayHasKey(MixedEntity::class, $all);
        self::assertArrayNotHasKey(PlainEntity::class, $all);
    }

    public function testGetClassMetadataForComment(): void
    {
        $metadata = $this->metadataProvider->getClassMetadata(Comment::class);

        self::assertNotNull($metadata);
        self::assertTrue($metadata->hasProperty('subject'));
        self::assertInstanceOf(DynamicPropertyMetadata::class, $metadata->getProperty('subject'));
    }

    public function testGetClassMetadataForActivity(): void
    {
        $metadata = $this->metadataProvider->getClassMetadata(Activity::class);

        self::assertNotNull($metadata);
        self::assertTrue($metadata->hasProperty('subject'));
        self::assertInstanceOf(ExplicitPropertyMetadata::class, $metadata->getProperty('subject'));
    }

    public function testGetClassMetadataReturnsNullForPlainEntity(): void
    {
        $metadata = $this->metadataProvider->getClassMetadata(PlainEntity::class);

        self::assertNull($metadata);
    }

    public function testDynamicPropertyMetadataHasCorrectMappingKeys(): void
    {
        $prop = $this->metadataProvider->getPropertyMetadata(Comment::class, 'subject');
        self::assertInstanceOf(DynamicPropertyMetadata::class, $prop);

        self::assertArrayHasKey('post', $prop->mapping);
        self::assertArrayHasKey('user', $prop->mapping);
        self::assertSame(Post::class, $prop->mapping['post']->fqcn);
        self::assertSame(User::class, $prop->mapping['user']->fqcn);
    }

    public function testExplicitPropertyMetadataHasReferenceFqcnAndPath(): void
    {
        $prop = $this->metadataProvider->getPropertyMetadata(Activity::class, 'subject');
        self::assertInstanceOf(ExplicitPropertyMetadata::class, $prop);

        self::assertNotEmpty($prop->referenceFqcn);
        self::assertStringEndsWith('.php', $prop->referencePath);
    }

    public function testMixedEntityHasBothPropertyTypes(): void
    {
        $metadata = $this->metadataProvider->getClassMetadata(MixedEntity::class);
        self::assertNotNull($metadata);

        self::assertInstanceOf(DynamicPropertyMetadata::class, $metadata->getProperty('dynamicRef'));
        self::assertInstanceOf(ExplicitPropertyMetadata::class, $metadata->getProperty('explicitRef'));
    }
}
