<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Unit;

use Pechynho\PolymorphicDoctrine\Contract\MetadataProviderInterface;
use Pechynho\PolymorphicDoctrine\Contract\ReferenceClassGeneratorInterface;
use Pechynho\PolymorphicDoctrine\PolymorphicCacheWarmer;
use PHPUnit\Framework\TestCase;

final class PolymorphicCacheWarmerTest extends TestCase
{
    public function testIsOptionalReturnsTrue(): void
    {
        $warmer = new PolymorphicCacheWarmer(
            $this->createMock(MetadataProviderInterface::class),
            $this->createMock(ReferenceClassGeneratorInterface::class),
            'dev',
        );

        self::assertTrue($warmer->isOptional());
    }

    public function testWarmUpSkipsInDevEnvironment(): void
    {
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->expects(self::never())->method('getAllMetadata');

        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');

        $warmer = new PolymorphicCacheWarmer($metadataProvider, $generator, 'dev');
        $result = $warmer->warmUp('/tmp');

        self::assertSame([], $result);
    }

    public function testWarmUpCallsMetadataAndGeneratorInProd(): void
    {
        $metadataProvider = $this->createMock(MetadataProviderInterface::class);
        $metadataProvider->expects(self::once())->method('getAllMetadata')->willReturn([]);

        $generator = $this->createMock(ReferenceClassGeneratorInterface::class);
        $generator->expects(self::once())->method('generate');

        $warmer = new PolymorphicCacheWarmer($metadataProvider, $generator, 'prod');
        $result = $warmer->warmUp('/tmp');

        self::assertSame([], $result);
    }
}
