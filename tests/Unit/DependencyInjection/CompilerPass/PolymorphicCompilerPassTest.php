<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\DependencyInjection\CompilerPass;

use Pechynho\PolymorphicDoctrine\DependencyInjection\CompilerPass\PolymorphicCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Filesystem\Filesystem;

final class PolymorphicCompilerPassTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/polymorphic-compiler-test-'.uniqid();
    }

    protected function tearDown(): void
    {
        $fs = new Filesystem();
        if ($fs->exists($this->tmpDir)) {
            $fs->remove($this->tmpDir);
        }
    }

    public function testProcessCreatesReferencesDirIfMissing(): void
    {
        $container = $this->createContainerBuilder();
        $pass = new PolymorphicCompilerPass();

        try {
            $pass->process($container);
        } catch (\Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException) {
            // DoctrineOrmMappingsPass may fail without full Doctrine setup
            // but the directory should still be created before that
        }

        self::assertDirectoryExists($this->tmpDir);
    }

    private function createContainerBuilder(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('pechynho.polymorphic_doctrine.references_directory', $this->tmpDir);
        $container->setParameter('pechynho.polymorphic_doctrine.references_namespace', 'Test\\AutoRef');
        $container->setParameter('kernel.name', 'test');
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('doctrine.default_entity_manager', 'default');
        // Provide a minimal metadata driver definition
        $container->setDefinition('doctrine.orm.default_metadata_driver', new Definition());

        return $container;
    }
}
