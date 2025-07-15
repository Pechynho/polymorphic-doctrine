<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use Doctrine\Persistence\ManagerRegistry;
use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Entity\DynamicPolymorphicReference;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Utils\ClassNameResolver;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Webmozart\Assert\Assert;

#[AutoconfigureTag('doctrine.event_listener', ['event' => Events::loadClassMetadata, 'lazy' => true])]
#[AutoconfigureTag('doctrine.event_listener', ['event' => Events::postLoad, 'lazy' => true])]
#[AutoconfigureTag('doctrine.event_listener', ['event' => ToolEvents::postGenerateSchemaTable, 'lazy' => true])]
final readonly class PolymorphicEventListener
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private PropertyAccessorInterface $propertyAccessor,
        private ClassNameResolver $classNameResolver,
        private Filesystem $fs,
        private ManagerRegistry $managerRegistry,
        private PolymorphicPropertyValueResolver $propertyValueResolver,
    ) {}

    /**
     * @throws MappingException
     * @throws SchemaException
     */
    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $args): void
    {
        $this->setIndexes($args);
        $this->setForeignKeyIndexes($args);
    }

    /**
     * @throws SchemaException
     */
    private function setIndexes(GenerateSchemaTableEventArgs $args): void
    {
        $doctrineClassMetadata = $args->getClassMetadata();
        $polymorphicClassMetadata = $this->metadataProvider->getClassMetadata($doctrineClassMetadata->getName());
        if ($polymorphicClassMetadata === null) {
            return;
        }
        $table = $args->getClassTable();
        foreach ($polymorphicClassMetadata->properties as $propertyName => $propertyMetadata) {
            if ($propertyMetadata instanceof DynamicPropertyMetadata) {
                $discriminatorColumnName = $doctrineClassMetadata->getColumnName($propertyName . '.discriminator');
                $idColumnName = $doctrineClassMetadata->getColumnName($propertyName . '.id');
                if ($propertyMetadata->enableDiscriminatorIndex) {
                    $table->addIndex([$discriminatorColumnName]);
                }
                if ($propertyMetadata->enablePairIndex) {
                    $table->addIndex([$discriminatorColumnName, $idColumnName]);
                }
            } elseif ($propertyMetadata instanceof ExplicitPropertyMetadata) {
                $discriminatorColumnName = $doctrineClassMetadata->getColumnName($propertyName . '.discriminator');
                if ($propertyMetadata->enableDiscriminatorIndex) {
                    $table->addIndex([$discriminatorColumnName]);
                }
                foreach ($propertyMetadata->mapping as $relationMetadata) {
                    if (!$relationMetadata->enablePairIndex) {
                        continue;
                    }
                    $idColumnName = $doctrineClassMetadata->getColumnName(
                        $propertyName . '.' . $relationMetadata->propertyName,
                    );
                    $table->addIndex([$discriminatorColumnName, $idColumnName]);
                }
            } else {
                throw new RuntimeException(
                    sprintf('Unsupported property metadata type: %s', get_debug_type($propertyMetadata)),
                );
            }
        }
    }

    /**
     * @throws MappingException
     * @throws SchemaException
     */
    private function setForeignKeyIndexes(GenerateSchemaTableEventArgs $args): void
    {
        $doctrineClassMetadata = $args->getClassMetadata();
        $polymorphicClassMetadata = $this->metadataProvider->getClassMetadata($doctrineClassMetadata->getName());
        if ($polymorphicClassMetadata === null) {
            return;
        }
        $table = $args->getClassTable();
        foreach ($polymorphicClassMetadata->properties as $propertyName => $propertyMetadata) {
            if (!$propertyMetadata instanceof ExplicitPropertyMetadata) {
                continue;
            }
            foreach ($propertyMetadata->mapping as $relationMetadata) {
                $relationDoctrineClassMetadata = $this->managerRegistry
                    ->getManagerForClass($relationMetadata->fqcn)
                    ?->getClassMetadata($relationMetadata->fqcn);
                if ($relationDoctrineClassMetadata === null) {
                    throw new RuntimeException(
                        sprintf('Class metadata for "%s" not found.', $relationMetadata->fqcn),
                    );
                }
                if (!$relationDoctrineClassMetadata instanceof ClassMetadata) {
                    continue;
                }
                $idColumnName = $doctrineClassMetadata->getColumnName(
                    $propertyName . '.' . $relationMetadata->propertyName,
                );
                $table->addForeignKeyConstraint(
                    foreignTable: $relationDoctrineClassMetadata->getTableName(),
                    localColumnNames: [$idColumnName],
                    foreignColumnNames: [$relationDoctrineClassMetadata->getSingleIdentifierFieldName()],
                    options: [
                        'onDelete' => $relationMetadata->onDelete,
                        'onUpdate' => $relationMetadata->onUpdate,
                    ],
                );
            }
        }
    }

    /**
     * @throws MappingException
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $args): void
    {
        $doctrineClassMetadata = $args->getClassMetadata();
        $polymorphicClassMetadata = $this->metadataProvider->getClassMetadata($doctrineClassMetadata->getName());
        if ($polymorphicClassMetadata === null) {
            return;
        }
        foreach ($polymorphicClassMetadata->properties as $propertyMetadata) {
            if ($propertyMetadata instanceof DynamicPropertyMetadata) {
                $doctrineClassMetadata->mapEmbedded([
                    'fieldName' => $propertyMetadata->property,
                    'class' => DynamicPolymorphicReference::class,
                ]);
                $doctrineClassMetadata->inlineEmbeddable(
                    property: $propertyMetadata->property,
                    embeddable: $args->getObjectManager()->getClassMetadata(DynamicPolymorphicReference::class),
                );
            } elseif ($propertyMetadata instanceof ExplicitPropertyMetadata) {
                if (!$this->fs->exists($propertyMetadata->referencePath)) {
                    throw new RuntimeException(
                        sprintf(
                            'Please run command "pechynho:polymorphic-doctrine:generate-reference-classes". Reference class path "%s" does not exist.',
                            $propertyMetadata->referencePath,
                        ),
                    );
                }
                $doctrineClassMetadata->mapEmbedded([
                    'fieldName' => $propertyMetadata->property,
                    'class' => $propertyMetadata->referenceFqcn,
                ]);
                $doctrineClassMetadata->inlineEmbeddable(
                    property: $propertyMetadata->property,
                    embeddable: $args->getObjectManager()->getClassMetadata($propertyMetadata->referenceFqcn),
                );
            } else {
                throw new RuntimeException(
                    sprintf('Unsupported property metadata type: %s', get_debug_type($propertyMetadata)),
                );
            }
        }
    }

    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();
        $classMetadata = $this->metadataProvider->getClassMetadata($this->classNameResolver->resolve($entity));
        if ($classMetadata === null) {
            return;
        }
        foreach ($classMetadata->properties as $propertyMetadata) {
            if ($propertyMetadata instanceof DynamicPropertyMetadata) {
                $loadedValue = $this->propertyAccessor->getValue($entity, $propertyMetadata->property);
                if ($loadedValue === null) {
                    $updatedValue = new DynamicPolymorphicReference();
                    $updatedValue->setNull();
                } elseif ($loadedValue instanceof DynamicPolymorphicReference) {
                    $updatedValue = $loadedValue;
                } else {
                    throw new RuntimeException(
                        sprintf(
                            'Expected "%s" to be an instance of "%s" or NULL, got "%s".',
                            $propertyMetadata->property,
                            DynamicPolymorphicReference::class,
                            get_debug_type($loadedValue),
                        ),
                    );
                }
                $updatedValue->setResolver($this->propertyValueResolver);
                $updatedValue->setMetadata($propertyMetadata);
                $this->propertyAccessor->setValue($entity, $propertyMetadata->property, $updatedValue);
            } elseif ($propertyMetadata instanceof ExplicitPropertyMetadata) {
                $loadedValue = $this->propertyAccessor->getValue($entity, $propertyMetadata->property);
                if ($loadedValue === null) {
                    $updatedValue = new $propertyMetadata->referenceFqcn();
                    Assert::isInstanceOf($updatedValue, PolymorphicReferenceInterface::class);
                    $updatedValue->setNull();
                } elseif ($loadedValue instanceof $propertyMetadata->referenceFqcn) {
                    $updatedValue = $loadedValue;
                } else {
                    throw new RuntimeException(
                        sprintf(
                            'Expected "%s" to be an instance of "%s" or NULL, got "%s".',
                            $propertyMetadata->property,
                            $propertyMetadata->referenceFqcn,
                            get_debug_type($loadedValue),
                        ),
                    );
                }
                Assert::isInstanceOf($updatedValue, PolymorphicReferenceInterface::class);
                $updatedValue->setResolver($this->propertyValueResolver);
                $updatedValue->setMetadata($propertyMetadata);
                $this->propertyAccessor->setValue($entity, $propertyMetadata->property, $updatedValue);
            } else {
                throw new RuntimeException(
                    sprintf('Unsupported property metadata type: %s', get_debug_type($propertyMetadata)),
                );
            }
        }
    }
}
