<?php

namespace Pechynho\PolymorphicDoctrine\Tests\Integration;

use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprApplierFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicSearchExprBuilderFactoryInterface;
use Pechynho\PolymorphicDoctrine\Contract\PolymorphicValueFactoryInterface;
use Pechynho\PolymorphicDoctrine\Tests\Fixtures\AbstractKernelTestCase;

final class BundleIntegrationTest extends AbstractKernelTestCase
{
    public function testBundleBoots(): void
    {
        $container = self::getContainer();
        self::assertTrue($container->hasParameter('pechynho.polymorphic_doctrine.references_directory'));
    }

    public function testPublicServicesAreRegistered(): void
    {
        $container = self::getContainer();
        self::assertTrue($container->has(PolymorphicValueFactoryInterface::class));
        self::assertTrue($container->has(PolymorphicSearchExprBuilderFactoryInterface::class));
        self::assertTrue($container->has(PolymorphicSearchExprApplierFactoryInterface::class));
    }

    public function testContainerParameters(): void
    {
        $container = self::getContainer();

        self::assertTrue($container->hasParameter('pechynho.polymorphic_doctrine.references_directory'));
        self::assertTrue($container->hasParameter('pechynho.polymorphic_doctrine.references_namespace'));
    }
}
