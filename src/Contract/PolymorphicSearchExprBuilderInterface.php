<?php

namespace Pechynho\PolymorphicDoctrine\Contract;

use Doctrine\ORM\Query\Expr;

interface PolymorphicSearchExprBuilderInterface
{
    /**
     * @return object{expr: Expr\AndX, params: array<string, mixed>}
     */
    public function eq(object $entity): object;

    /**
     * @return object{expr: Expr\AndX, params: array<string, mixed>}
     */
    public function neq(object $entity): object;

    public function isNull(): Expr\Andx;

    public function isNotNull(): Expr\Composite;

    /**
     * @param class-string ...$fqcn
     * @return object{expr: Expr\Andx, params: array<string, mixed>}
     */
    public function isInstanceOf(string ...$fqcn): object;

    /**
     * @return object{expr: Expr\Andx, params: array<string, mixed>}
     */
    public function isNotInstanceOf(string ...$fqcn): object;

    /**
     * @return object{expr: Expr\OrX, params: array<string, mixed>}
     */
    public function in(object ...$entities): object;

    /**
     * @return object{expr: Expr\AndX, params: array<string, mixed>}
     */
    public function notIn(object ...$entities): object;

    public function expr(): Expr;
}
