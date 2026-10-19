<?php

namespace Wexample\SymfonyMedia\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyMediaExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_media.storage_dir', $config['storage_dir']);
        $container->setParameter('wexample_symfony_media.thumbnail_sizes', $config['thumbnail_sizes']);
        $container->setParameter('wexample_symfony_media.upload_max_size', $config['upload_max_size']);
        $container->setParameter('wexample_symfony_media.view_role', $config['view_role']);
        $container->setParameter('wexample_symfony_media.manage_role', $config['manage_role']);
    }
}
