<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use RuntimeException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Throwable;

final readonly class PolymorphicSearchExprBuilder implements PolymorphicSearchExprBuilderInterface
{
    /**
     * @param class-string $fqcn
     */
    public function __construct(
        private string $fqcn,
        private string $property,
        private string $alias,
        private PropertyMetadataInterface $propertyMetadata,
        private EntityManagerInterface $em,
        private ClassNameResolver $classNameResolver,
        private PropertyAccessorInterface $propertyAccessor,
    ) {}

    /**
     * @return object{expr: Expr\AndX, params: array<string, mixed>}
     */
    public function eq(object $entity): object
    {
        $discrParam = $this->generateParamName();
        $idParam = $this->generateParamName();
        return new readonly class (
            $this->expr()->andX(
                $this->expr()->eq($this->getDiscrProperty(), ':' . $discrParam),
                $this->expr()->eq($this->getIdProperty($entity), ':' . $idParam),
            ),
            [
                $discrParam => $this->getDiscr($entity),
                $idParam => $this->getId($entity),
            ],
        ) {
            public function __construct(
                public Expr\AndX $expr,
                public array $params,
            ) {}
        };
    }

    /**
     * @return object{expr: Expr\AndX, params: array<string, mixed>}
     */
    public function neq(object $entity): object
    {
        $discrParam = $this->generateParamName();
        $idParam = $this->generateParamName();
        return new readonly class (
            $this->expr()->andX(
                $this->expr()->neq($this->getDiscrProperty(), ':' . $discrParam),
                $this->expr()->neq($this->getIdProperty($entity), ':' . $idParam),
            ),
            [
                $discrParam => $this->getDiscr($entity),
                $idParam => $this->getId($entity),
            ],
        ) {
            public function __construct(
                public Expr\AndX $expr,
                public array $params,
            ) {}
        };
    }

    public function isNull(): Expr\Andx
    {
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            return $this->expr()->andX(
                $this->expr()->isNull($this->getDiscrProperty()),
                $this->expr()->isNull($this->getIdProperty()),
            );
        }
        if ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            $andX = $this->expr()->andX($this->expr()->isNull($this->getDiscrProperty()));
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                $andX->add(
                    $this->expr()->isNull(
                        sprintf('%s.%s.%s', $this->alias, $this->property, $relationMetadata->propertyName),
                    ),
                );
            }
            return $andX;
        }
        throw new RuntimeException(
            sprintf(
                'Property metadata for "%s" is not supported. Got: %s',
                $this->property,
                get_debug_type($this->propertyMetadata),
            ),
        );
    }

    public function isNotNull(): Expr\Composite
    {
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            return $this->expr()->andX(
                $this->expr()->isNotNull($this->getDiscrProperty()),
                $this->expr()->isNotNull($this->getIdProperty()),
            );
        }
        if ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            $orX = $this->expr()->orX();
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                $this->expr()->andX(
                    $this->expr()->andX(
                        $this->expr()->isNotNull($this->getDiscrProperty()),
                        $this->expr()->isNotNull(
                            sprintf('%s.%s.%s', $this->alias, $this->property, $relationMetadata->propertyName),
                        ),
                    ),
                );
            }
            return $orX;
        }
        throw new RuntimeException(
            sprintf(
                'Property metadata for "%s" is not supported. Got: %s',
                $this->property,
                get_debug_type($this->propertyMetadata),
            ),
        );
    }

    /**
     * @return object{expr: Expr\Andx, params: array<string, mixed>}
     */
    public function isInstanceOf(string ...$fqcn): object
    {
        if (empty($fqcn)) {
            throw new RuntimeException('At least one class name must be provided for polymorphic search.');
        }
        $andX = $this->expr()->andX();
        $discrProperty = $this->getDiscrProperty();
        $params = [];
        foreach ($fqcn as $className) {
            $discrParam = $this->generateParamName();
            $andX->add($this->expr()->eq($discrProperty, ':' . $discrParam));
            $params[$discrParam] = $this->getDiscr($className);
        }
        return new readonly class ($andX, $params) {
            public function __construct(
                public Expr\AndX $expr,
                public array $params,
            ) {}
        };
    }

    /**
     * @return object{expr: Expr\Andx, params: array<string, mixed>}
     */
    public function isNotInstanceOf(string ...$fqcn): object
    {
        if (empty($fqcn)) {
            throw new RuntimeException('At least one class name must be provided for polymorphic search.');
        }
        $andX = $this->expr()->andX();
        $discrProperty = $this->getDiscrProperty();
        $params = [];
        foreach ($fqcn as $className) {
            $discrParam = $this->generateParamName();
            $andX->add($this->expr()->neq($discrProperty, ':' . $discrParam));
            $params[$discrParam] = $this->getDiscr($className);
        }
        return new readonly class ($andX, $params) {
            public function __construct(
                public Expr\AndX $expr,
                public array $params,
            ) {}
        };
    }

    /**
     * @return object{expr: Expr\OrX, params: array<string, mixed>}
     */
    public function in(object ...$entities): object
    {
        if (empty($entities)) {
            throw new RuntimeException('At least one entity must be provided for polymorphic search.');
        }
        $orX = $this->expr()->orX();
        $params = [];
        foreach ($entities as $entity) {
            $eq = $this->eq($entity);
            $orX->add($eq->expr);
            foreach ($eq->params as $paramName => $paramValue) {
                $params[$paramName] = $paramValue;
            }
        }
        return new readonly class ($orX, $params) {
            public function __construct(
                public Expr\OrX $expr,
                public array $params,
            ) {}
        };
    }

    /**
     * @return object{expr: Expr\AndX, params: array<string, mixed>}
     */
    public function notIn(object ...$entities): object
    {
        if (empty($entities)) {
            throw new RuntimeException('At least one entity must be provided for polymorphic search.');
        }
        $andX = $this->expr()->andX();
        $params = [];
        foreach ($entities as $entity) {
            $neq = $this->neq($entity);
            $andX->add($neq->expr);
            foreach ($neq->params as $paramName => $paramValue) {
                $params[$paramName] = $paramValue;
            }
        }
        return new readonly class ($andX, $params) {
            public function __construct(
                public Expr\Andx $expr,
                public array $params,
            ) {}
        };
    }

    private function getDiscrProperty(): string
    {
        return sprintf('%s.%s.%s', $this->alias, $this->property, 'discriminator');
    }

    private function getId(object $entity): int | string
    {
        $className = $this->classNameResolver->resolve($entity);
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                if ($relationMetadata->fqcn === $className) {
                    return $this->propertyAccessor->getValue($entity, $relationMetadata->idProperty);
                }
            }
        } elseif ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                if ($relationMetadata->fqcn === $className) {
                    return $this->propertyAccessor->getValue($entity, $relationMetadata->idProperty);
                }
            }
        }
        throw new RuntimeException(
            sprintf(
                'No ID found for class "%s" in property "%s" of "%s".',
                $className,
                $this->property,
                $this->fqcn,
            ),
        );
    }

    private function getDiscr(object | string $subject): string
    {
        $className = is_string($subject) ? $subject : $this->classNameResolver->resolve($subject);
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            foreach ($this->propertyMetadata->mapping as $discriminator => $relationMetadata) {
                if ($relationMetadata->fqcn === $className) {
                    return $discriminator;
                }
            }
        } elseif ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            foreach ($this->propertyMetadata->mapping as $discriminator => $relationMetadata) {
                if ($relationMetadata->fqcn === $className) {
                    return $discriminator;
                }
            }
        }
        throw new RuntimeException(
            sprintf(
                'No discriminator value found for class "%s" in property "%s" of "%s".',
                $className,
                $this->property,
                $this->fqcn,
            ),
        );
    }

    private function getIdProperty(?object $entity = null): string
    {
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            return sprintf('%s.%s.id', $this->alias, $this->property);
        }
        if ($this->propertyMetadata instanceof ExplicitPropertyMetadata && $entity !== null) {
            $className = $this->classNameResolver->resolve($entity);
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                if ($relationMetadata->fqcn !== $className) {
                    continue;
                }
                return sprintf('%s.%s.%s', $this->alias, $this->property, $relationMetadata->propertyName);
            }
            throw new RuntimeException(
                sprintf(
                    'No mapping found for class "%s" in property "%s" of "%s".',
                    $className,
                    $this->property,
                    $this->fqcn,
                ),
            );
        }
        throw new RuntimeException(
            sprintf(
                'Property metadata for "%s" is not supported. Got: %s',
                $this->property,
                get_debug_type($this->propertyMetadata),
            ),
        );
    }

    private function generateParamName(): string
    {
        try {
            return 'polymorphic_search_' . random_int(0, 999999);
        } catch (Throwable) {
            throw new RuntimeException('Failed to generate a unique parameter name for polymorphic search.');
        }
    }

    public function expr(): Expr
    {
        return $this->em->getExpressionBuilder();
    }
}
