<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\Model\SearchExprResult;
use Pechynho\PolymorphicDoctrine\PolymorphicSearchExprApplier;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\Entity\Post;
use PHPUnit\Framework\TestCase;

final class PolymorphicSearchExprApplierTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\MockObject $builder;
    private PolymorphicSearchExprApplier $applier;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(PolymorphicSearchExprBuilderInterface::class);
        $this->applier = new PolymorphicSearchExprApplier($this->builder);
    }

    public function testEqCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $result = new SearchExprResult(new Expr\Andx(), ['p1' => 'v1']);

        $this->builder->method('eq')->with($entity)->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('p1', 'v1')->willReturnSelf();

        $this->applier->eq($qb, $entity);
    }

    public function testNeqCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $result = new SearchExprResult(new Expr\Orx(), ['p1' => 'v1']);

        $this->builder->method('neq')->with($entity)->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->neq($qb, $entity);
    }

    public function testIsNullCallsBuilderAndApplies(): void
    {
        $result = new SearchExprResult(new Expr\Andx());
        $this->builder->method('isNull')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();

        $this->applier->isNull($qb);
    }

    public function testIsNotNullCallsBuilderAndApplies(): void
    {
        $result = new SearchExprResult(new Expr\Andx());
        $this->builder->method('isNotNull')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();

        $this->applier->isNotNull($qb);
    }

    public function testIsInstanceOfCallsBuilderAndApplies(): void
    {
        $result = new SearchExprResult(new Expr\Orx(), ['p1' => 'v1']);
        $this->builder->expects(self::once())->method('isInstanceOf')->with(Post::class)->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->isInstanceOf($qb, Post::class);
    }

    public function testIsNotInstanceOfCallsBuilderAndApplies(): void
    {
        $result = new SearchExprResult(new Expr\Andx(), ['p1' => 'v1']);
        $this->builder->method('isNotInstanceOf')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->isNotInstanceOf($qb, Post::class);
    }

    public function testInCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $result = new SearchExprResult(new Expr\Orx(), ['p1' => 'v1']);
        $this->builder->method('in')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->in($qb, $entity);
    }

    public function testNotInCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $result = new SearchExprResult(new Expr\Andx(), ['p1' => 'v1']);
        $this->builder->method('notIn')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->notIn($qb, $entity);
    }

    /**
     * @return QueryBuilder&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createQueryBuilderMock(): QueryBuilder
    {
        $em = $this->createMock(EntityManagerInterface::class);

        return $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['andWhere', 'setParameter'])
            ->getMock();
    }
}
