<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * @internal
 */
final readonly class PolymorphicCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private ReferenceClassGenerator $referenceClassGenerator,
        private string $environment,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ('dev' === $this->environment) {
            return [];
        }
        $this->metadataProvider->getAllMetadata();
        $this->referenceClassGenerator->generate();

        return [];
    }
}
