<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Integration;

use Pechynho\PolymorphicDoctrine\Contract\PolymorphicReferenceInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\AbstractKernelTestCase;
use Webmozart\Assert\Assert;

final class ReferenceClassGeneratorIntegrationTest extends AbstractKernelTestCase
{
    #[\Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    private function getGenerator(): ReferenceClassGeneratorInterface
    {
        $generator = self::getContainer()->get('test.'.ReferenceClassGeneratorInterface::class);
        Assert::isInstanceOf($generator, ReferenceClassGeneratorInterface::class);

        return $generator;
    }

    private function getRefsDir(): string
    {
        $refsDir = self::getContainer()->getParameter('pechynho.polymorphic_doctrine.references_directory');
        Assert::string($refsDir);

        return $refsDir;
    }

    private function getRefsNamespace(): string
    {
        $refsNamespace = self::getContainer()->getParameter('pechynho.polymorphic_doctrine.references_namespace');
        Assert::string($refsNamespace);

        return $refsNamespace;
    }

    public function testGenerateCreatesPhpFiles(): void
    {
        $this->getGenerator()->generate();

        $files = glob($this->getRefsDir().'/*.php');
        self::assertNotFalse($files);
        self::assertNotEmpty($files);
    }

    public function testGeneratedClassIsLoadableAndInstantiable(): void
    {
        $this->getGenerator()->generate();

        $files = glob($this->getRefsDir().'/*.php');
        self::assertNotFalse($files);

        foreach ($files as $file) {
            require_once $file;
        }

        $refsNamespace = $this->getRefsNamespace();
        $foundRefClass = false;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            self::assertNotFalse($content);
            if (preg_match('/^namespace\s+(.+);/m', $content, $nsMatch)
                && preg_match('/^(?:final\s+)?class\s+(\w+)/m', $content, $classMatch)) {
                $fqcn = $nsMatch[1].'\\'.$classMatch[1];
                if (class_exists($fqcn)) {
                    $instance = new $fqcn();
                    self::assertInstanceOf(PolymorphicReferenceInterface::class, $instance);
                    $foundRefClass = true;
                }
            }
        }

        self::assertTrue($foundRefClass, 'At least one reference class should be loadable');
    }

    public function testClearRemovesGeneratedFiles(): void
    {
        $generator = $this->getGenerator();
        $generator->generate();

        $refsDir = $this->getRefsDir();
        $files = glob($refsDir.'/*.php');
        self::assertNotFalse($files);
        self::assertNotEmpty($files);

        $generator->clear();

        $filesAfter = glob($refsDir.'/*.php');
        self::assertNotFalse($filesAfter);
        self::assertEmpty($filesAfter);
    }
}
