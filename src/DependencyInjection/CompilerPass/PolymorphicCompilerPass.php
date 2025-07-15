<?php

declare(strict_types=1);

namespace Pechynho\PolymorphicDoctrine\DependencyInjection\CompilerPass;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;

final readonly class PolymorphicCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $referencesDir = $container->getParameter('pechynho.polymorphic_doctrine.references_directory');
        $fs = new Filesystem();
        if (!$fs->exists($referencesDir)) {
            $fs->mkdir($referencesDir);
        }
        /**
         * DoctrineOrmMappingsPass::createAttributeMappingDriver did not work because of some parameter issues,
         * so we create everything manually.
         */
        $directories = [
            __DIR__ . '/../../Entity',
            $referencesDir,
        ];
        $namespaces = [
            'Pechynho\PolymorphicDoctrine\Entity',
            $container->getParameter('pechynho.polymorphic_doctrine.references_namespace'),
        ];
        $driver = new Definition(AttributeDriver::class, [$directories]);
        $mappingPass = new DoctrineOrmMappingsPass($driver, $namespaces, [], false, []);
        $mappingPass->process($container);
    }
}
