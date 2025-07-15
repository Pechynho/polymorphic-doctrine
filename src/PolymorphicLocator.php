<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Attributes\EntityWithPolymorphicRelations;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicLocatorInterface;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\Exceptions\NoCacheConfigured;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final readonly class PolymorphicLocator implements PolymorphicLocatorInterface
{
    public function __construct(
        #[Autowire(param: 'pechynho.polymorphic_doctrine.discover.cache_directory')]
        private string $discoverCacheDir,
        #[Autowire(param: 'pechynho.polymorphic_doctrine.discover.directories')]
        private array $discoverDirectories,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
        private Filesystem $fs,
    ) {}

    /**
     * @inheritDoc
     * @throws NoCacheConfigured
     */
    public function getEntities(): array
    {
        $discover = Discover::in(...$this->discoverDirectories);
        $discover = $discover->withAttribute(EntityWithPolymorphicRelations::class);
        if ($this->environment !== 'dev') {
            if (!$this->fs->exists($this->discoverCacheDir)) {
                $this->fs->mkdir($this->discoverCacheDir);
            }
            $discover = $discover->withCache(
                id: 'orm_polymorphic.discover',
                cache: new FileDiscoverCacheDriver($this->discoverCacheDir),
            );
        }
        return $discover->get();
    }

    public function clearCache(): void
    {
        if ($this->fs->exists($this->discoverCacheDir)) {
            $this->fs->remove($this->discoverCacheDir);
            $this->fs->mkdir($this->discoverCacheDir);
        }
    }
}
