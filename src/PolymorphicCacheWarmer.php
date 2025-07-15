<?php

namespace Pechynho\PolymorphicDoctrine;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final readonly class PolymorphicCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private MetadataProviderInterface $metadataProvider,
        private ReferenceClassGenerator $referenceClassGenerator,
        #[Autowire(param: 'kernel.environment')]
        private string $environment,
    ) {}

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if ($this->environment === 'dev') {
            return [];
        }
        $this->metadataProvider->getAllMetadata();
        $this->referenceClassGenerator->generate();
        return [];
    }
}
