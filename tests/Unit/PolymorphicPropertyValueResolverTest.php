<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use Pechynho\PolymorphicDoctrine\PolymorphicPropertyValueResolver;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class PolymorphicPropertyValueResolverTest extends TestCase
{
    private PolymorphicPropertyValueResolver $resolver;
    private ManagerRegistry $registry;
    private ClassNameResolver $classNameResolver;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->classNameResolver = $this->createMock(ClassNameResolver::class);

        $this->resolver = new PolymorphicPropertyValueResolver(
            $this->registry,
            $this->classNameResolver,
            new PropertyAccessor(),
        );
    }

    // === DYNAMIC LOAD ===

    public function testLoadDynamicPropertyReturnsNullWhenDiscriminatorIsNull(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: null, id: null);
        $metadata = $this->createDynamicMetadata();

        self::assertNull($this->resolver->loadProperty($ref, $metadata));
    }

    public function testLoadDynamicPropertyReturnsNullWhenIdIsNull(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: null);
        $metadata = $this->createDynamicMetadata();

        self::assertNull($this->resolver->loadProperty($ref, $metadata));
    }

    public function testLoadDynamicPropertyThrowsForUnknownDiscriminator(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'unknown', id: '1');
        $metadata = $this->createDynamicMetadata();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No relation mapping found for discriminator "unknown"');

        $this->resolver->loadProperty($ref, $metadata);
    }

    public function testLoadDynamicPropertyCallsFindOnManager(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '42');
        $metadata = $this->createDynamicMetadata();
        $entity = new \stdClass();

        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())
            ->method('find')
            ->with(FakePost::class, '42')
            ->willReturn($entity);

        $this->registry->method('getManagerForClass')
            ->with(FakePost::class)
            ->willReturn($manager);

        $result = $this->resolver->loadProperty($ref, $metadata);

        self::assertSame($entity, $result);
    }

    // === DYNAMIC SET ===

    public function testSetDynamicPropertySetsDiscriminatorAndId(): void
    {
        $ref = new DynamicPolymorphicReference();
        $metadata = $this->createDynamicMetadata();
        $entity = new FakePost();
        $entity->id = '99';

        $this->classNameResolver->method('resolve')->willReturn(FakePost::class);

        $this->resolver->setProperty($ref, $metadata, $entity);

        self::assertSame('post', $ref->discriminator);
        self::assertSame('99', $ref->id);
    }

    public function testSetDynamicPropertyNullClearsBothFields(): void
    {
        $ref = new DynamicPolymorphicReference(discriminator: 'post', id: '1');
        $metadata = $this->createDynamicMetadata();

        $this->resolver->setProperty($ref, $metadata, null);

        self::assertNull($ref->discriminator);
        self::assertNull($ref->id);
    }

    public function testSetDynamicPropertyThrowsForUnmappedClass(): void
    {
        $ref = new DynamicPolymorphicReference();
        $metadata = $this->createDynamicMetadata();
        $entity = new \stdClass();

        $this->classNameResolver->method('resolve')->willReturn(\stdClass::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No matching discriminator found for class');

        $this->resolver->setProperty($ref, $metadata, $entity);
    }

    // === EXPLICIT LOAD ===

    public function testLoadExplicitPropertyReturnsNullWhenDiscriminatorIsNull(): void
    {
        $refObj = new FakeExplicitReference();
        $refObj->discriminator = null;
        $refObj->postId = null;
        $metadata = $this->createExplicitMetadata();

        self::assertNull($this->resolver->loadProperty($refObj, $metadata));
    }

    public function testLoadExplicitPropertyCallsFindCorrectly(): void
    {
        $refObj = new FakeExplicitReference();
        $refObj->discriminator = 'post';
        $refObj->postId = 42;
        $metadata = $this->createExplicitMetadata();
        $entity = new \stdClass();

        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::once())
            ->method('find')
            ->with(FakePost::class, 42)
            ->willReturn($entity);

        $this->registry->method('getManagerForClass')
            ->willReturn($manager);

        $result = $this->resolver->loadProperty($refObj, $metadata);

        self::assertSame($entity, $result);
    }

    public function testLoadExplicitPropertyThrowsForUnknownDiscriminator(): void
    {
        $refObj = new FakeExplicitReference();
        $refObj->discriminator = 'unknown';
        $refObj->postId = 1;
        $metadata = $this->createExplicitMetadata();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No relation mapping found for discriminator "unknown"');

        $this->resolver->loadProperty($refObj, $metadata);
    }

    // === EXPLICIT SET ===

    public function testSetExplicitPropertySetsCorrectFields(): void
    {
        $refObj = new FakeExplicitReference();
        $metadata = $this->createExplicitMetadata();
        $entity = new FakePost();
        $entity->id = '5';

        $this->classNameResolver->method('resolve')->willReturn(FakePost::class);

        $this->resolver->setProperty($refObj, $metadata, $entity);

        self::assertSame('post', $refObj->discriminator);
        self::assertSame('5', $refObj->postId);
    }

    public function testSetExplicitPropertyNullClearsAllFields(): void
    {
        $refObj = new FakeExplicitReference();
        $refObj->discriminator = 'post';
        $refObj->postId = 42;
        $metadata = $this->createExplicitMetadata();

        $this->resolver->setProperty($refObj, $metadata, null);

        self::assertNull($refObj->discriminator);
        self::assertNull($refObj->postId);
    }

    public function testSetExplicitPropertyThrowsForUnmappedClass(): void
    {
        $refObj = new FakeExplicitReference();
        $metadata = $this->createExplicitMetadata();
        $entity = new \stdClass();

        $this->classNameResolver->method('resolve')->willReturn(\stdClass::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No matching mapping found for class');

        $this->resolver->setProperty($refObj, $metadata, $entity);
    }

    // === UNSUPPORTED COMBINATIONS ===

    public function testLoadPropertyThrowsForUnsupportedCombination(): void
    {
        $ref = new \stdClass();
        $metadata = $this->createDynamicMetadata();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not supported');

        $this->resolver->loadProperty($ref, $metadata);
    }

    public function testSetPropertyThrowsForUnsupportedCombination(): void
    {
        $ref = new \stdClass();
        $metadata = $this->createDynamicMetadata();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not supported');

        $this->resolver->setProperty($ref, $metadata, null);
    }

    // === HELPERS ===

    private function createDynamicMetadata(): DynamicPropertyMetadata
    {
        return new DynamicPropertyMetadata(
            property: 'subject',
            mapping: [
                'post' => new DynamicRelationMetadata(fqcn: FakePost::class, idProperty: 'id'),
            ],
            enableDiscriminatorIndex: true,
            enablePairIndex: true,
        );
    }

    private function createExplicitMetadata(): ExplicitPropertyMetadata
    {
        return new ExplicitPropertyMetadata(
            property: 'subject',
            mapping: [
                'post' => new ExplicitRelationMetadata(
                    fqcn: FakePost::class,
                    idProperty: 'id',
                    idPropertyType: 'int',
                    propertyName: 'postId',
                    columnName: 'post_id',
                    onDelete: 'RESTRICT',
                    onUpdate: 'RESTRICT',
                    enablePairIndex: true,
                ),
            ],
            referenceFqcn: FakeExplicitReference::class,
            referencePath: '/tmp/fake.php',
            enableDiscriminatorIndex: true,
        );
    }
}

class FakePost
{
    public ?string $id = null;
}

class FakeExplicitReference
{
    public ?string $discriminator = null;
    public int|string|null $postId = null;
}
