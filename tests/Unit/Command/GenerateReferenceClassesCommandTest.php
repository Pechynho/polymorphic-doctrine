<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit\Command;

use Pechynho\PolymorphicDoctrine\Command\GenerateReferenceClassesCommand;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class GenerateReferenceClassesCommandTest extends TestCase
{
    public function testExecuteCallsGenerateAndReturnsSuccess(): void
    {
        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $generator->expects(self::once())->method('generate');

        $command = new GenerateReferenceClassesCommand($generator);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('successfully', $tester->getDisplay());
    }

    public function testExecuteHandlesExceptionGracefully(): void
    {
        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $generator->method('generate')->willThrowException(new \RuntimeException('Test error'));

        $command = new GenerateReferenceClassesCommand($generator);
        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Test error', $tester->getDisplay());
    }

    public function testCommandName(): void
    {
        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $command = new GenerateReferenceClassesCommand($generator);

        self::assertSame('pechynho:polymorphic-doctrine:generate-reference-classes', $command->getName());
    }
}
