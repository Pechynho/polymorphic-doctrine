<?php

namespace Pechynho\PolymorphicDoctrine\Command;

use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle as IO;
use Throwable;

#[AsCommand('pechynho:polymorphic-doctrine:generate-reference-classes', 'Generate reference classes for polymorphic relations')]
final class GenerateReferenceClassesCommand extends Command
{
    public function __construct(
        private readonly ReferenceClassGeneratorInterface $referenceClassGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new IO($input, $output);
        $io->title('Generating reference classes for polymorphic relations');
        try {
            $this->referenceClassGenerator->generate();
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            $io->error($e->getTraceAsString());
            return self::FAILURE;
        }
        $io->success('Reference classes generated successfully.');
        return self::SUCCESS;
    }
}
