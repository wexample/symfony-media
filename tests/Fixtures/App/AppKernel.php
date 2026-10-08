<?php

namespace Wexample\SymfonyMedia\Tests\Fixtures\App;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyMedia\Tests\Fixtures\App\DependencyInjection\PublicServicesPass;
use Wexample\SymfonyMedia\WexampleSymfonyMediaBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new WexampleSymfonyMediaBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__.'/config/config.yaml'];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/../../../src/Controller', 'attribute');
        $routes->import(__DIR__.'/../../../src/Api/Controller', 'attribute');
    }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PublicServicesPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }
}
