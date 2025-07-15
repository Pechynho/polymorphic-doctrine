<?php

namespace Pechynho\PolymorphicDoctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nette\PhpGenerator\PhpFile;
use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Pechynho\PolymorphicDoctrine\Model\ExplicitPropertyMetadata;
use Pechynho\PolymorphicDoctrine\Trait\PolymorphicReferenceTrait;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class ReferenceClassGenerator implements ReferenceClassGeneratorInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        #[Autowire(param: 'pechynho.polymorphic_doctrine.references_directory')]
        private string $referencesDir,
        private Filesystem $fs,
    ) {}

    public function generate(): void
    {
        if (!$this->fs->exists($this->referencesDir)) {
            $this->fs->mkdir($this->referencesDir);
        }
        $allMetadata = $this->metadataProvider->getAllMetadata();
        foreach ($allMetadata as $metadata) {
            foreach ($metadata->properties as $property) {
                if ($property instanceof ExplicitPropertyMetadata) {
                    $this->generateClass($property);
                }
            }
        }
    }

    public function clear(): void
    {
        if ($this->fs->exists($this->referencesDir)) {
            $this->fs->remove($this->referencesDir);
            $this->fs->mkdir($this->referencesDir);
        }
    }

    public function generateClass(ExplicitPropertyMetadata $property): void
    {
        if ($this->fs->exists($property->referencePath)) {
            return;
        }
        $phpFile = new PhpFile();
        $phpNamespace = $phpFile->addNamespace(
            mb_substr($property->referenceFqcn, 0, mb_strrpos($property->referenceFqcn, '\\')),
        );
        $phpFile->addNamespace($phpNamespace);
        $phpNamespace->addUse(ORM\Embeddable::class);
        $phpNamespace->addUse(ORM\Column::class);
        $phpNamespace->addUse(PolymorphicValueInterface::class);
        $phpNamespace->addUse(PolymorphicReferenceInterface::class);
        $phpNamespace->addUse(PolymorphicReferenceTrait::class);
        $phpClass = $phpNamespace->addClass(
            mb_substr($property->referenceFqcn, mb_strrpos($property->referenceFqcn, '\\') + 1),
        );
        $phpClass->setFinal();
        $phpClass->addImplement(PolymorphicValueInterface::class);
        $phpClass->addImplement(PolymorphicReferenceInterface::class);
        $phpClass->addTrait(PolymorphicReferenceTrait::class);
        $phpClass->addAttribute(ORM\Embeddable::class);
        $phpProperty = $phpClass->addProperty('discriminator');
        $phpProperty->setNullable();
        $phpProperty->setType('string');
        $phpProperty->addAttribute(ORM\Column::class, [
            'type' => Types::STRING,
            'nullable' => true,
            'length' => 128,
        ]);
        foreach ($property->mapping as $relationMetadata) {
            $phpProperty = $phpClass->addProperty($relationMetadata->propertyName);
            $phpProperty->setValue(null);
            $phpProperty->setPublic();
            $phpProperty->setType($relationMetadata->idPropertyType);
            $columnOptions = [];
            if ($relationMetadata->idPropertyType === 'int') {
                $columnType = Types::INTEGER;
            } elseif ($relationMetadata->idPropertyType === 'string') {
                $columnType = Types::STRING;
                $columnOptions['length'] = 64;
            } else {
                throw new RuntimeException(
                    sprintf('Unsupported id property type: %s', $relationMetadata->idPropertyType),
                );
            }
            $phpProperty->addAttribute(ORM\Column::class, [
                'type' => $columnType,
                'nullable' => true,
                'name' => $relationMetadata->columnName,
                ...$columnOptions,
            ]);
        }
        $this->fs->mkdir(dirname($property->referencePath));
        $this->fs->dumpFile($property->referencePath, (string) $phpFile);
    }
}
