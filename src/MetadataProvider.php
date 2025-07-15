<?php

namespace Pechynho\PolymorphicDoctrine;

use LogicException;
use Pechynho\PolymorphicDoctrine\Attributes\DynamicPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Attributes\ExplicitPolymorphicProperty;
use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicLocatorInterface;
use Pechynho\PolymorphicDoctrine\Contract\PropertyMetadataInterface;
use Pechynho\PolymorphicDoctrine\Model\ClassMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\DynamicRelationMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Model\ExplicitRelationMetadata;
use Psr\Cache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

use function Symfony\Component\String\u;

final class MetadataProvider implements MetadataProviderInterface, ResetInterface
{
    /** @var array<class-string, ClassMetadata> */
    private array $resolvedMetadata = [];
    /** @var array<class-string, ClassMetadata> */
    private array $allMetadata;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly PolymorphicLocatorInterface $polymorphicLocator,
        #[Autowire(param: 'pechynho.polymorphic_doctrine.references_directory')]
        private readonly string $referencesDir,
        #[Autowire(param: 'pechynho.polymorphic_doctrine.references_namespace')]
        private readonly string $referencesNamespace,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {}

    public function reset(): void
    {
        $this->resolvedMetadata = [];
        unset($this->allMetadata);
    }

    /**
     * @return array<class-string, ClassMetadata>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getAllMetadata(): array
    {
        if (isset($this->allMetadata)) {
            return $this->allMetadata;
        }
        $entityClasses = $this->polymorphicLocator->getEntities();
        $allMetadata = [];
        foreach ($entityClasses as $fqcn) {
            if (null !== $polymorphicMetadata = $this->getClassMetadata($fqcn)) {
                $this->resolvedMetadata[$fqcn] = $polymorphicMetadata;
                $allMetadata[$fqcn] = $polymorphicMetadata;
            }
        }
        return $this->allMetadata = $allMetadata;
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getClassMetadata(string $fqcn): ?ClassMetadata
    {
        if (isset($this->allMetadata) && array_key_exists($fqcn, $this->allMetadata)) {
            return $this->allMetadata[$fqcn];
        }
        if (array_key_exists($fqcn, $this->resolvedMetadata)) {
            return $this->resolvedMetadata[$fqcn];
        }
        if ($this->environment === 'dev') {
            return $this->resolvedMetadata[$fqcn] = $this->getClassMetadataFromReflection($fqcn);
        }
        return $this->resolvedMetadata[$fqcn] = $this->getClassMetadataFromCache($fqcn);
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function getPropertyMetadata(string $fqcn, string $property): ?PropertyMetadataInterface
    {
        return $this->getClassMetadata($fqcn)?->getProperty($property);
    }

    /**
     * @param class-string $fqcn
     * @throws ReflectionException
     */
    private function getClassMetadataFromReflection(string $fqcn): ?ClassMetadata
    {
        $refClass = new ReflectionClass($fqcn);
        $refProps = $refClass->getProperties();
        $pointer = $refClass->getParentClass();
        while ($pointer !== false) {
            $refProps = [...$refProps, ...$pointer->getProperties()];
            $pointer = $pointer->getParentClass();
        }
        $propertiesMetadata = [];
        foreach ($refProps as $refProp) {
            $propertyName = $refProp->getName();
            $dynamicAttr = $refProp->getAttributes(DynamicPolymorphicProperty::class);
            $explicitAttr = $refProp->getAttributes(ExplicitPolymorphicProperty::class);
            if (empty($dynamicAttr) && empty($explicitAttr)) {
                continue;
            }
            if (!empty($dynamicAttr) && !empty($explicitAttr)) {
                throw new RuntimeException(
                    sprintf(
                        'Property "%s" in class "%s" cannot have both %s and %s attributes on property "%s". Use only one of them.',
                        $refProp->getName(),
                        $fqcn,
                        DynamicPolymorphicProperty::class,
                        ExplicitPolymorphicProperty::class,
                        $propertyName,
                    ),
                );
            }
            $metadata = null;
            if (!empty($dynamicAttr)) {
                $metadata = $this->createDynamicPropertyMetadata(
                    attribute: $dynamicAttr[0]->newInstance(),
                    reflection: $refProp,
                );
            } elseif (!empty($explicitAttr)) {
                $metadata = $this->createExplicitPropertyMetadata(
                    fqcn: $fqcn,
                    attribute: $explicitAttr[0]->newInstance(),
                    reflection: $refProp,
                );
            }
            if ($metadata === null) {
                throw new LogicException('Unexpected state: metadata should not be null here.');
            }
            if (array_key_exists($propertyName, $propertiesMetadata)) {
                throw new RuntimeException(
                    sprintf(
                        'Property "%s" is already defined in class "%s".',
                        $propertyName,
                        $fqcn,
                    ),
                );
            }
            $propertiesMetadata[$propertyName] = $metadata;
        }
        return empty($propertiesMetadata) ? null : new ClassMetadata($propertiesMetadata);
    }

    /**
     * @param class-string $fqcn
     * @throws InvalidArgumentException
     */
    private function getClassMetadataFromCache(string $fqcn): ?ClassMetadata
    {
        return $this->cache->get(
            key: 'polymorphic_relation.metadata.' . str_replace('\\', '__', $fqcn),
            callback: function () use ($fqcn) {
                return $this->getClassMetadataFromReflection($fqcn);
            },
        );
    }

    private function createDynamicPropertyMetadata(
        DynamicPolymorphicProperty $attribute,
        ReflectionProperty $reflection,
    ): DynamicPropertyMetadata {
        $mapping = [];
        $resolver = new OptionsResolver()
            ->setRequired('fqcn')
            ->setAllowedTypes('fqcn', 'string')
            ->setDefault('idProperty', $attribute->iddProperty ?? 'id')
            ->setAllowedTypes('idProperty', 'string');
        foreach ($attribute->mapping as $discriminator => $value) {
            $options = $resolver->resolve(is_string($value) ? ['fqcn' => $value] : $value);
            $mapping[$discriminator] = new DynamicRelationMetadata(
                fqcn: $options['fqcn'],
                idProperty: $options['idProperty'],
            );
        }
        return new DynamicPropertyMetadata(
            property: $reflection->getName(),
            mapping: $mapping,
            enableDiscriminatorIndex: $attribute->enableDiscriminatorIndex ?? true,
            enablePairIndex: $attribute->enablePairIndex ?? true,
        );
    }

    private function createExplicitPropertyMetadata(
        string $fqcn,
        ExplicitPolymorphicProperty $attribute,
        ReflectionProperty $reflection,
    ): ExplicitPropertyMetadata {
        $propertyName = $reflection->getName();
        $mapping = [];
        $reservedKeyWords = ['discriminator'];
        foreach ($attribute->mapping as $discriminator => $value) {
            if (in_array($discriminator, $reservedKeyWords, true)) {
                throw new RuntimeException(
                    sprintf(
                        'Discriminator "%s" is reserved and cannot be used in class "%s" property "%s".',
                        $discriminator,
                        $fqcn,
                        $propertyName,
                    ),
                );
            }
            $resolver = new OptionsResolver()
                ->setRequired('fqcn')
                ->setAllowedTypes('fqcn', 'string')
                ->setDefault('idProperty', $attribute->iddProperty ?? 'id')
                ->setAllowedTypes('idProperty', 'string')
                ->setDefault('idPropertyType', $attribute->idPropertyType ?? 'int')
                ->setAllowedValues('idPropertyType', ['int', 'string'])
                ->setDefault('onDelete', $attribute->onDelete ?? 'RESTRICT')
                ->setAllowedValues('onDelete', ['RESTRICT', 'CASCADE', 'SET NULL'])
                ->setDefault('onUpdate', $attribute->onUpdate ?? 'RESTRICT')
                ->setAllowedValues('onUpdate', ['RESTRICT', 'CASCADE', 'SET NULL'])
                ->setDefault('propertyName', u($discriminator)->camel()->toString() . 'Id')
                ->setAllowedTypes('propertyName', 'string')
                ->setDefault('columnName', u($discriminator)->snake()->toString() . '_id')
                ->setAllowedTypes('columnName', 'string')
                ->setDefault('enablePairIndex', $attribute->enablePairIndex ?? true)
                ->setAllowedTypes('enablePairIndex', 'bool');
            $options = $resolver->resolve(is_string($value) ? ['fqcn' => $value] : $value);
            $mapping[$discriminator] = new ExplicitRelationMetadata(
                fqcn: $options['fqcn'],
                idProperty: $options['idProperty'],
                idPropertyType: $options['idPropertyType'],
                propertyName: $options['propertyName'],
                columnName: $options['columnName'],
                onDelete: $options['onDelete'],
                onUpdate: $options['onUpdate'],
                enablePairIndex: $options['enablePairIndex'],
            );
        }
        $hash = hash(
            algo: 'xxh3',
            data: $fqcn
                  . $propertyName
                  . serialize($mapping)
                  . $this->referencesNamespace
                  . $this->referencesDir,
        );
        return new ExplicitPropertyMetadata(
            property: $propertyName,
            mapping: $mapping,
            referenceFqcn: $this->referencesNamespace
                           . '\\'
                           . str_replace('\\', '__', $fqcn)
                           . ucfirst($propertyName)
                           . 'Reference'
                           . '__'
                           . $hash,
            referencePath: $this->referencesDir
                           . DIRECTORY_SEPARATOR
                           . str_replace('\\', '__', $fqcn)
                           . ucfirst($propertyName)
                           . 'Reference'
                           . '__'
                           . $hash
                           . '.php',
            enableDiscriminatorIndex: $attribute->enableDiscriminatorIndex ?? true,
        );
    }
}
