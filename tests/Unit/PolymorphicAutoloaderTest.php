<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\PolymorphicAutoloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class PolymorphicAutoloaderTest extends TestCase
{
    private string $tmpDir;
    private ?\Closure $registeredAutoloader = null;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/polymorphic-autoloader-test-'.uniqid();
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        if ($this->registeredAutoloader instanceof \Closure) {
            spl_autoload_unregister($this->registeredAutoloader);
            $this->registeredAutoloader = null;
        }

        $fs = new Filesystem();
        if ($fs->exists($this->tmpDir)) {
            $fs->remove($this->tmpDir);
        }
    }

    public function testRegisterReturnsClosure(): void
    {
        $this->registeredAutoloader = PolymorphicAutoloader::register($this->tmpDir, 'Test\\AutoRef');

        $this->expectNotToPerformAssertions();
    }

    public function testAutoloaderIgnoresUnrelatedClasses(): void
    {
        $this->registeredAutoloader = PolymorphicAutoloader::register($this->tmpDir, 'Test\\AutoRef');

        $this->expectNotToPerformAssertions();

        // Should not throw - class doesn't match namespace
        ($this->registeredAutoloader)('Some\\Other\\Class');
    }

    public function testAutoloaderThrowsRuntimeExceptionForMissingFile(): void
    {
        $this->registeredAutoloader = PolymorphicAutoloader::register($this->tmpDir, 'Test\\AutoRef');

        $this->expectException(\Pechynho\PolymorphicDoctrine\Exception\MappingException::class);
        $this->expectExceptionMessage('does not exist');

        ($this->registeredAutoloader)('Test\\AutoRef\\NonExistentClass');
    }

    public function testAutoloaderLoadsExistingFile(): void
    {
        $className = 'AutoloaderTestClass_'.uniqid();
        $namespace = 'Test\\AutoRef';
        $fqcn = $namespace.'\\'.$className;

        file_put_contents(
            $this->tmpDir.'/'.$className.'.php',
            "<?php\nnamespace {$namespace};\nclass {$className} {}\n",
        );

        $this->registeredAutoloader = PolymorphicAutoloader::register($this->tmpDir, $namespace);
        ($this->registeredAutoloader)($fqcn);

        self::assertTrue(class_exists($fqcn, false));
    }
}
