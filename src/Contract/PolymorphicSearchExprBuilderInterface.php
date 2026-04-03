<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

use Doctrine\ORM\Query\Expr;
use Pechynho\PolymorphicDoctrine\Model\SearchExprResult;

interface PolymorphicSearchExprBuilderInterface
{
    public function eq(object $entity): SearchExprResult;

    public function neq(object $entity): SearchExprResult;

    public function isNull(): SearchExprResult;

    public function isNotNull(): SearchExprResult;

    /**
     * @param class-string ...$fqcn
     */
    public function isInstanceOf(string ...$fqcn): SearchExprResult;

    public function isNotInstanceOf(string ...$fqcn): SearchExprResult;

    public function in(object ...$entities): SearchExprResult;

    public function notIn(object ...$entities): SearchExprResult;

    public function expr(): Expr;
}
