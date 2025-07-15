<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

use Doctrine\ORM\QueryBuilder;

interface PolymorphicSearchExprApplierInterface
{
    public function eq(QueryBuilder $qb, object $entity): void;

    public function neq(QueryBuilder $qb, object $entity): void;

    public function isNull(QueryBuilder $qb): void;

    public function isNotNull(QueryBuilder $qb): void;

    /**
     * @param class-string ...$fqcn
     */
    public function isInstanceOf(QueryBuilder $qb, string ...$fqcn): void;

    /**
     * @param class-string ...$fqcn
     */
    public function isNotInstanceOf(QueryBuilder $qb, string ...$fqcn): void;

    public function in(QueryBuilder $qb, object ...$entities): void;

    public function notIn(QueryBuilder $qb, object ...$entities): void;
}
