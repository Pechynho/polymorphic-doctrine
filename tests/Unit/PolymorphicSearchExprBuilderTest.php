<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Pechynho\PolymorphicDoctrine\Contract\ClassNameResolverInterface;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use Pechynho\PolymorphicDoctrine\PolymorphicSearchExprBuilder;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Activity;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Comment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class PolymorphicSearchExprBuilderTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject&ClassNameResolverInterface $classNameResolver;
    private \PHPUnit\Framework\MockObject\MockObject&EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->classNameResolver = $this->createMock(ClassNameResolverInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->method('getExpressionBuilder')->willReturn(new Expr());
    }

    // === DYNAMIC ===

    public function testEqDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $entity = new SearchTestPost();
        $entity->id = 42;

        $this->classNameResolver->method('resolve')->willReturn(SearchTestPost::class);

        $result = $builder->eq($entity);

        self::assertCount(2, $result->params);
        self::assertContains('post', $result->params);
        self::assertContains(42, $result->params);
    }

    public function testNeqDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $entity = new SearchTestPost();
        $entity->id = 1;

        $this->classNameResolver->method('resolve')->willReturn(SearchTestPost::class);

        $result = $builder->neq($entity);

        self::assertCount(2, $result->params);
        self::assertContains('post', $result->params);
        self::assertContains(1, $result->params);
    }

    public function testIsNullDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $result = $builder->isNull();

        self::assertEmpty($result->params);
    }

    public function testIsNotNullDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $result = $builder->isNotNull();

        self::assertEmpty($result->params);
    }

    public function testIsInstanceOfDynamic(): void
    {
        $builder = $this->createDynamicBuilder();

        $result = $builder->isInstanceOf(SearchTestPost::class);

        self::assertCount(1, $result->params);
        self::assertContains('post', $result->params);
    }

    public function testIsNotInstanceOfDynamic(): void
    {
        $builder = $this->createDynamicBuilder();

        $result = $builder->isNotInstanceOf(SearchTestPost::class);

        self::assertCount(1, $result->params);
        self::assertContains('post', $result->params);
    }

    public function testIsInstanceOfMultipleClasses(): void
    {
        $builder = $this->createDynamicBuilder();

        $result = $builder->isInstanceOf(SearchTestPost::class, SearchTestUser::class);

        self::assertCount(2, $result->params);
        self::assertContains('post', $result->params);
        self::assertContains('user', $result->params);
    }

    public function testInDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $post = new SearchTestPost();
        $post->id = 1;
        $user = new SearchTestUser();
        $user->id = 2;

        $this->classNameResolver->method('resolve')
            ->willReturnCallback(static fn (object $e): string => $e instanceof SearchTestPost ? SearchTestPost::class : SearchTestUser::class);

        $result = $builder->in($post, $user);

        self::assertCount(4, $result->params);
    }

    public function testNotInDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $post = new SearchTestPost();
        $post->id = 1;

        $this->classNameResolver->method('resolve')->willReturn(SearchTestPost::class);

        $result = $builder->notIn($post);

        self::assertCount(2, $result->params);
    }

    // === EXPLICIT ===

    public function testEqExplicit(): void
    {
        $builder = $this->createExplicitBuilder();
        $post = new SearchTestPost();
        $post->id = 5;

        $this->classNameResolver->method('resolve')->willReturn(SearchTestPost::class);

        $result = $builder->eq($post);

        self::assertCount(2, $result->params);
        self::assertContains('post', $result->params);
        self::assertContains(5, $result->params);
    }

    public function testIsNullExplicit(): void
    {
        $builder = $this->createExplicitBuilder();
        $result = $builder->isNull();

        self::assertEmpty($result->params);
    }

    public function testIsNotNullExplicit(): void
    {
        $builder = $this->createExplicitBuilder();
        $result = $builder->isNotNull();

        self::assertEmpty($result->params);
    }

    // === VALIDATION ===

    public function testIsInstanceOfThrowsForEmptyArgs(): void
    {
        $builder = $this->createDynamicBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('At least one class name must be provided');

        $builder->isInstanceOf();
    }

    public function testIsNotInstanceOfThrowsForEmptyArgs(): void
    {
        $builder = $this->createDynamicBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('At least one class name must be provided');

        $builder->isNotInstanceOf();
    }

    public function testInThrowsForEmptyArgs(): void
    {
        $builder = $this->createDynamicBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('At least one entity must be provided');

        $builder->in();
    }

    public function testNotInThrowsForEmptyArgs(): void
    {
        $builder = $this->createDynamicBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('At least one entity must be provided');

        $builder->notIn();
    }

    public function testGetDiscrThrowsForUnmappedClass(): void
    {
        $builder = $this->createDynamicBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No discriminator value found');

        $builder->isInstanceOf(\stdClass::class);
    }

    public function testExprReturnsExprInstance(): void
    {
        $this->createDynamicBuilder();

        $this->expectNotToPerformAssertions();
    }

    // === HELPERS ===

    private function createDynamicBuilder(): PolymorphicSearchExprBuilder
    {
        $metadata = new DynamicPropertyMetadata(
            property: 'subject',
            mapping: [
                'post' => new DynamicRelationMetadata(fqcn: SearchTestPost::class, idProperty: 'id'),
                'user' => new DynamicRelationMetadata(fqcn: SearchTestUser::class, idProperty: 'id'),
            ],
            enableDiscriminatorIndex: true,
            enablePairIndex: true,
        );

        return new PolymorphicSearchExprBuilder(
            fqcn: Comment::class,
            property: 'subject',
            alias: 'c',
            propertyMetadata: $metadata,
            em: $this->em,
            classNameResolver: $this->classNameResolver,
            propertyAccessor: new PropertyAccessor(),
        );
    }

    private function createExplicitBuilder(): PolymorphicSearchExprBuilder
    {
        $metadata = new ExplicitPropertyMetadata(
            property: 'subject',
            mapping: [
                'post' => new ExplicitRelationMetadata(
                    fqcn: SearchTestPost::class, idProperty: 'id', idPropertyType: 'int',
                    propertyName: 'postId', columnName: 'post_id', onDelete: 'RESTRICT', onUpdate: 'RESTRICT', enablePairIndex: true,
                ),
                'user' => new ExplicitRelationMetadata(
                    fqcn: SearchTestUser::class, idProperty: 'id', idPropertyType: 'int',
                    propertyName: 'userId', columnName: 'user_id', onDelete: 'RESTRICT', onUpdate: 'RESTRICT', enablePairIndex: true,
                ),
            ],
            referenceFqcn: Activity::class,
            referencePath: '/tmp/ref.php',
            enableDiscriminatorIndex: true,
        );

        return new PolymorphicSearchExprBuilder(
            fqcn: Activity::class,
            property: 'subject',
            alias: 'a',
            propertyMetadata: $metadata,
            em: $this->em,
            classNameResolver: $this->classNameResolver,
            propertyAccessor: new PropertyAccessor(),
        );
    }
}

class SearchTestPost
{
    public ?int $id = null;
}

class SearchTestUser
{
    public ?int $id = null;
}
