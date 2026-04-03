<?php

namespace Pechynho\PolymorphicDoctrine\Model;

use Doctrine\ORM\Query\Expr;

final readonly class SearchExprResult
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public Expr\Composite $expr,
        public array $params = [],
    ) {
    }
}
