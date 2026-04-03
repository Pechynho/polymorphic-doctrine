<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use Pechynho\PolymorphicDoctrine\PolymorphicSearchExprBuilder;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class PolymorphicSearchExprBuilderTest extends TestCase
{
    private ClassNameResolver $classNameResolver;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->classNameResolver = $this->createMock(ClassNameResolver::class);
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

        self::assertInstanceOf(Expr\Andx::class, $result->expr);
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

        self::assertInstanceOf(Expr\Andx::class, $result->expr);
        self::assertCount(2, $result->params);
    }

    public function testIsNullDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $result = $builder->isNull();

        self::assertInstanceOf(Expr\Andx::class, $result);
        $parts = $result->getParts();
        self::assertCount(2, $parts);
    }

    public function testIsNotNullDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $result = $builder->isNotNull();

        self::assertInstanceOf(Expr\Composite::class, $result);
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

    public function testInDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $post = new SearchTestPost();
        $post->id = 1;
        $user = new SearchTestUser();
        $user->id = 2;

        $this->classNameResolver->method('resolve')
            ->willReturnCallback(fn (object $e) => $e instanceof SearchTestPost ? SearchTestPost::class : SearchTestUser::class);

        $result = $builder->in($post, $user);

        self::assertInstanceOf(Expr\Orx::class, $result->expr);
        self::assertCount(4, $result->params);
    }

    public function testNotInDynamic(): void
    {
        $builder = $this->createDynamicBuilder();
        $post = new SearchTestPost();
        $post->id = 1;

        $this->classNameResolver->method('resolve')->willReturn(SearchTestPost::class);

        $result = $builder->notIn($post);

        self::assertInstanceOf(Expr\Andx::class, $result->expr);
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

        self::assertInstanceOf(Expr\Andx::class, $result->expr);
        self::assertCount(2, $result->params);
        self::assertContains('post', $result->params);
        self::assertContains(5, $result->params);
    }

    public function testIsNullExplicit(): void
    {
        $builder = $this->createExplicitBuilder();
        $result = $builder->isNull();

        self::assertInstanceOf(Expr\Andx::class, $result);
        // discriminator IS NULL + postId IS NULL + userId IS NULL = 3
        self::assertCount(3, $result->getParts());
    }

    public function testIsNotNullExplicit(): void
    {
        $builder = $this->createExplicitBuilder();
        $result = $builder->isNotNull();

        self::assertInstanceOf(Expr\Composite::class, $result);
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

        $builder->notIn();
    }

    public function testGetDiscrThrowsForUnmappedClass(): void
    {
        $builder = $this->createDynamicBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No discriminator value found');

        $builder->isInstanceOf('App\\Unknown\\Class');
    }

    public function testExprReturnsExprInstance(): void
    {
        $builder = $this->createDynamicBuilder();

        self::assertInstanceOf(Expr::class, $builder->expr());
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
            fqcn: 'App\\Entity\\Comment',
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
            referenceFqcn: 'App\\Ref\\Ref',
            referencePath: '/tmp/ref.php',
            enableDiscriminatorIndex: true,
        );

        return new PolymorphicSearchExprBuilder(
            fqcn: 'App\\Entity\\Activity',
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
