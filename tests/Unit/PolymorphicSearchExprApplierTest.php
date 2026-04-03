<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\PolymorphicSearchExprApplier;
use PHPUnit\Framework\TestCase;

final class PolymorphicSearchExprApplierTest extends TestCase
{
    private PolymorphicSearchExprBuilderInterface $builder;
    private PolymorphicSearchExprApplier $applier;

    protected function setUp(): void
    {
        $this->builder = $this->createMock(PolymorphicSearchExprBuilderInterface::class);
        $this->applier = new PolymorphicSearchExprApplier($this->builder);
    }

    public function testEqCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $expr = new Expr\Andx();
        $result = new readonly class($expr, ['p1' => 'v1']) {
            public function __construct(public Expr\Andx $expr, public array $params) {}
        };

        $this->builder->method('eq')->with($entity)->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->with($expr)->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('p1', 'v1')->willReturnSelf();

        $this->applier->eq($qb, $entity);
    }

    public function testNeqCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $expr = new Expr\Andx();
        $result = new readonly class($expr, ['p1' => 'v1']) {
            public function __construct(public Expr\Andx $expr, public array $params) {}
        };

        $this->builder->method('neq')->with($entity)->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->neq($qb, $entity);
    }

    public function testIsNullCallsBuilderAndApplies(): void
    {
        $expr = new Expr\Andx();
        $this->builder->method('isNull')->willReturn($expr);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->with($expr)->willReturnSelf();

        $this->applier->isNull($qb);
    }

    public function testIsNotNullCallsBuilderAndApplies(): void
    {
        $expr = new Expr\Andx();
        $this->builder->method('isNotNull')->willReturn($expr);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->with($expr)->willReturnSelf();

        $this->applier->isNotNull($qb);
    }

    public function testIsInstanceOfCallsBuilderAndApplies(): void
    {
        $expr = new Expr\Andx();
        $result = new readonly class($expr, ['p1' => 'v1']) {
            public function __construct(public Expr\Andx $expr, public array $params) {}
        };
        $this->builder->method('isInstanceOf')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->isInstanceOf($qb, 'App\\Entity\\Post');
    }

    public function testIsNotInstanceOfCallsBuilderAndApplies(): void
    {
        $expr = new Expr\Andx();
        $result = new readonly class($expr, ['p1' => 'v1']) {
            public function __construct(public Expr\Andx $expr, public array $params) {}
        };
        $this->builder->method('isNotInstanceOf')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->isNotInstanceOf($qb, 'App\\Entity\\Post');
    }

    public function testInCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $expr = new Expr\Orx();
        $result = new readonly class($expr, ['p1' => 'v1']) {
            public function __construct(public Expr\Orx $expr, public array $params) {}
        };
        $this->builder->method('in')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->in($qb, $entity);
    }

    public function testNotInCallsBuilderAndApplies(): void
    {
        $entity = new \stdClass();
        $expr = new Expr\Andx();
        $result = new readonly class($expr, ['p1' => 'v1']) {
            public function __construct(public Expr\Andx $expr, public array $params) {}
        };
        $this->builder->method('notIn')->willReturn($result);

        $qb = $this->createQueryBuilderMock();
        $qb->expects(self::once())->method('andWhere')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->willReturnSelf();

        $this->applier->notIn($qb, $entity);
    }

    private function createQueryBuilderMock(): QueryBuilder
    {
        $em = $this->createMock(EntityManagerInterface::class);

        return $this->getMockBuilder(QueryBuilder::class)
            ->setConstructorArgs([$em])
            ->onlyMethods(['andWhere', 'setParameter'])
            ->getMock();
    }
}
