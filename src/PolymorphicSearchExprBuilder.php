<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\SearchExprResult;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class PolymorphicSearchExprBuilder implements PolymorphicSearchExprBuilderInterface
{
    private int $paramCounter = 0;

    /**
     * @param class-string $fqcn
     */
    public function __construct(
        private readonly string $fqcn,
        private readonly string $property,
        private readonly string $alias,
        private readonly PropertyMetadataInterface $propertyMetadata,
        private readonly EntityManagerInterface $em,
        private readonly ClassNameResolver $classNameResolver,
        private readonly PropertyAccessorInterface $propertyAccessor,
    ) {
    }

    public function eq(object $entity): SearchExprResult
    {
        $discrParam = $this->generateParamName();
        $idParam = $this->generateParamName();

        return new SearchExprResult(
            $this->expr()->andX(
                $this->expr()->eq($this->getDiscrProperty(), ':'.$discrParam),
                $this->expr()->eq($this->getIdProperty($entity), ':'.$idParam),
            ),
            [
                $discrParam => $this->getDiscr($entity),
                $idParam => $this->getId($entity),
            ],
        );
    }

    public function neq(object $entity): SearchExprResult
    {
        $discrParam = $this->generateParamName();
        $idParam = $this->generateParamName();

        return new SearchExprResult(
            $this->expr()->orX(
                $this->expr()->neq($this->getDiscrProperty(), ':'.$discrParam),
                $this->expr()->neq($this->getIdProperty($entity), ':'.$idParam),
            ),
            [
                $discrParam => $this->getDiscr($entity),
                $idParam => $this->getId($entity),
            ],
        );
    }

    public function isNull(): SearchExprResult
    {
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            return new SearchExprResult(
                $this->expr()->andX(
                    $this->expr()->isNull($this->getDiscrProperty()),
                    $this->expr()->isNull($this->getIdProperty()),
                ),
            );
        }
        if ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            $andX = $this->expr()->andX($this->expr()->isNull($this->getDiscrProperty()));
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                $andX->add(
                    $this->expr()->isNull(
                        \sprintf('%s.%s.%s', $this->alias, $this->property, $relationMetadata->propertyName),
                    ),
                );
            }

            return new SearchExprResult($andX);
        }
        throw new \RuntimeException(\sprintf('Property metadata for "%s" is not supported. Got: %s', $this->property, get_debug_type($this->propertyMetadata)));
    }

    public function isNotNull(): SearchExprResult
    {
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            return new SearchExprResult(
                $this->expr()->andX(
                    $this->expr()->isNotNull($this->getDiscrProperty()),
                    $this->expr()->isNotNull($this->getIdProperty()),
                ),
            );
        }
        if ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            $orX = $this->expr()->orX();
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                $orX->add(
                    $this->expr()->andX(
                        $this->expr()->isNotNull($this->getDiscrProperty()),
                        $this->expr()->isNotNull(
                            \sprintf('%s.%s.%s', $this->alias, $this->property, $relationMetadata->propertyName),
                        ),
                    ),
                );
            }

            return new SearchExprResult($orX);
        }
        throw new \RuntimeException(\sprintf('Property metadata for "%s" is not supported. Got: %s', $this->property, get_debug_type($this->propertyMetadata)));
    }

    /**
     * @param class-string ...$fqcn
     */
    public function isInstanceOf(string ...$fqcn): SearchExprResult
    {
        if ([] === $fqcn) {
            throw new \RuntimeException('At least one class name must be provided for polymorphic search.');
        }
        $orX = $this->expr()->orX();
        $params = [];
        foreach ($fqcn as $className) {
            $discrParam = $this->generateParamName();
            $orX->add($this->expr()->eq($this->getDiscrProperty(), ':'.$discrParam));
            $params[$discrParam] = $this->getDiscr($className);
        }

        return new SearchExprResult($orX, $params);
    }

    public function isNotInstanceOf(string ...$fqcn): SearchExprResult
    {
        if ([] === $fqcn) {
            throw new \RuntimeException('At least one class name must be provided for polymorphic search.');
        }
        $andX = $this->expr()->andX();
        $params = [];
        foreach ($fqcn as $className) {
            $discrParam = $this->generateParamName();
            $andX->add($this->expr()->neq($this->getDiscrProperty(), ':'.$discrParam));
            $params[$discrParam] = $this->getDiscr($className);
        }

        return new SearchExprResult($andX, $params);
    }

    public function in(object ...$entities): SearchExprResult
    {
        if ([] === $entities) {
            throw new \RuntimeException('At least one entity must be provided for polymorphic search.');
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

        return new SearchExprResult($orX, $params);
    }

    public function notIn(object ...$entities): SearchExprResult
    {
        if ([] === $entities) {
            throw new \RuntimeException('At least one entity must be provided for polymorphic search.');
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

        return new SearchExprResult($andX, $params);
    }

    private function getDiscrProperty(): string
    {
        return \sprintf('%s.%s.%s', $this->alias, $this->property, 'discriminator');
    }

    private function getId(object $entity): int|string
    {
        $className = $this->classNameResolver->resolve($entity);
        $mapping = null;
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            $mapping = $this->propertyMetadata->mapping;
        } elseif ($this->propertyMetadata instanceof ExplicitPropertyMetadata) {
            $mapping = $this->propertyMetadata->mapping;
        }
        if (null !== $mapping) {
            foreach ($mapping as $relationMetadata) {
                if ($relationMetadata->fqcn === $className) {
                    $id = $this->propertyAccessor->getValue($entity, $relationMetadata->idProperty);
                    if (!\is_int($id) && !\is_string($id)) {
                        throw new \RuntimeException(\sprintf('ID property "%s" of class "%s" must be int or string, got %s.', $relationMetadata->idProperty, $className, get_debug_type($id)));
                    }

                    return $id;
                }
            }
        }
        throw new \RuntimeException(\sprintf('No ID found for class "%s" in property "%s" of "%s".', $className, $this->property, $this->fqcn));
    }

    private function getDiscr(object|string $subject): string
    {
        $className = \is_string($subject) ? $subject : $this->classNameResolver->resolve($subject);
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
        throw new \RuntimeException(\sprintf('No discriminator value found for class "%s" in property "%s" of "%s".', $className, $this->property, $this->fqcn));
    }

    private function getIdProperty(?object $entity = null): string
    {
        if ($this->propertyMetadata instanceof DynamicPropertyMetadata) {
            return \sprintf('%s.%s.id', $this->alias, $this->property);
        }
        if ($this->propertyMetadata instanceof ExplicitPropertyMetadata && null !== $entity) {
            $className = $this->classNameResolver->resolve($entity);
            foreach ($this->propertyMetadata->mapping as $relationMetadata) {
                if ($relationMetadata->fqcn !== $className) {
                    continue;
                }

                return \sprintf('%s.%s.%s', $this->alias, $this->property, $relationMetadata->propertyName);
            }
            throw new \RuntimeException(\sprintf('No mapping found for class "%s" in property "%s" of "%s".', $className, $this->property, $this->fqcn));
        }
        throw new \RuntimeException(\sprintf('Property metadata for "%s" is not supported. Got: %s', $this->property, get_debug_type($this->propertyMetadata)));
    }

    private function generateParamName(): string
    {
        return 'polymorphic_search_'.$this->paramCounter++;
    }

    public function expr(): Expr
    {
        return $this->em->getExpressionBuilder();
    }
}
