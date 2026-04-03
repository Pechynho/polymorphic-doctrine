<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Attributes\EntityWithPolymorphicRelations;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicLocatorInterface;
use Spatie\StructureDiscoverer\Cache\FileDiscoverCacheDriver;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\Exceptions\NoCacheConfigured;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
final readonly class PolymorphicLocator implements PolymorphicLocatorInterface
{
    /**
     * @param list<string> $discoverDirectories
     */
    public function __construct(
        private string $discoverCacheDir,
        private array $discoverDirectories,
        private string $environment,
        private Filesystem $fs,
    ) {
    }

    /**
     * @throws NoCacheConfigured
     */
    public function getEntities(): array
    {
        $discover = Discover::in(...$this->discoverDirectories);
        $discover = $discover->withAttribute(EntityWithPolymorphicRelations::class);
        if ('dev' !== $this->environment) {
            if (!$this->fs->exists($this->discoverCacheDir)) {
                $this->fs->mkdir($this->discoverCacheDir);
            }
            $discover = $discover->withCache(
                'orm_polymorphic.discover',
                new FileDiscoverCacheDriver($this->discoverCacheDir),
            );
        }

        /** @var list<class-string> */
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
