<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\ORM\QueryBuilder;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprApplierInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;

final readonly class PolymorphicSearchExprApplier implements PolymorphicSearchExprApplierInterface
{
    public function __construct(
        private PolymorphicSearchExprBuilderInterface $searchExprBuilder,
    ) {}

    public function eq(QueryBuilder $qb, object $entity): void
    {
        $val = $this->searchExprBuilder->eq($entity);
        $qb->andWhere($val->expr);
        foreach ($val->params as $paramName => $paramValue) {
            $qb->setParameter($paramName, $paramValue);
        }
    }

    public function neq(QueryBuilder $qb, object $entity): void
    {
        $val = $this->searchExprBuilder->neq($entity);
        $qb->andWhere($val->expr);
        foreach ($val->params as $paramName => $paramValue) {
            $qb->setParameter($paramName, $paramValue);
        }
    }

    public function isNull(QueryBuilder $qb): void
    {
        $qb->andWhere($this->searchExprBuilder->isNull());
    }

    public function isNotNull(QueryBuilder $qb): void
    {
        $qb->andWhere($this->searchExprBuilder->isNotNull());
    }

    public function isInstanceOf(QueryBuilder $qb, string ...$fqcn): void
    {
        $val = $this->searchExprBuilder->isInstanceOf(...$fqcn);
        $qb->andWhere($val->expr);
        foreach ($val->params as $paramName => $paramValue) {
            $qb->setParameter($paramName, $paramValue);
        }
    }

    public function isNotInstanceOf(QueryBuilder $qb, string ...$fqcn): void
    {
        $val = $this->searchExprBuilder->isNotInstanceOf(...$fqcn);
        $qb->andWhere($val->expr);
        foreach ($val->params as $paramName => $paramValue) {
            $qb->setParameter($paramName, $paramValue);
        }
    }

    public function in(QueryBuilder $qb, object ...$entities): void
    {
        $val = $this->searchExprBuilder->in(...$entities);
        $qb->andWhere($val->expr);
        foreach ($val->params as $paramName => $paramValue) {
            $qb->setParameter($paramName, $paramValue);
        }
    }

    public function notIn(QueryBuilder $qb, object ...$entities): void
    {
        $val = $this->searchExprBuilder->notIn(...$entities);
        $qb->andWhere($val->expr);
        foreach ($val->params as $paramName => $paramValue) {
            $qb->setParameter($paramName, $paramValue);
        }
    }
}
