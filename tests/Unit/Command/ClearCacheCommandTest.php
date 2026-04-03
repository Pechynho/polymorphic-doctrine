<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Command;

use Pechynho\PolymorphicDoctrine\Command\ClearCacheCommand;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicLocatorInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearCacheCommandTest extends TestCase
{
    public function testExecuteCallsClearAndClearCache(): void
    {
        $locator = $this->createMock(PolymorphicLocatorInterface::class);
        $locator->expects(self::once())->method('clearCache');

        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $generator->expects(self::once())->method('clear');

        $command = new ClearCacheCommand($locator, $generator);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('successfully', $tester->getDisplay());
    }

    public function testExecuteHandlesExceptionGracefully(): void
    {
        $locator = $this->createMock(PolymorphicLocatorInterface::class);
        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $generator->method('clear')->willThrowException(new \RuntimeException('Clear failed'));

        $command = new ClearCacheCommand($locator, $generator);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Clear failed', $tester->getDisplay());
    }

    public function testCommandName(): void
    {
        $locator = $this->createMock(PolymorphicLocatorInterface::class);
        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $command = new ClearCacheCommand($locator, $generator);

        self::assertSame('pechynho:polymorphic-doctrine:cache-clear', $command->getName());
    }
}
