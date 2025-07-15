<?php

namespace Pechynho\PolymorphicDoctrine\Command;

use Pechynho\PolymorphicDoctrine\Contract\PolymorphicLocatorInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle as IO;
use Throwable;

#[AsCommand('pechynho:polymorphic-doctrine:cache-clear', 'Clear the cache for polymorphic relations')]
final class ClearCacheCommand extends Command
{
    public function __construct(
        private readonly PolymorphicLocatorInterface $polymorphicLocator,
        private readonly ReferenceClassGeneratorInterface $referenceClassGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new IO($input, $output);
        $io->title('Clearing cache for polymorphic relations');
        try {
            $this->referenceClassGenerator->clear();
            $this->polymorphicLocator->clearCache();
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            $io->error($e->getTraceAsString());
            return self::FAILURE;
        }
        $io->success('Cache cleared successfully for polymorphic relations.');
        return self::SUCCESS;
    }
}
