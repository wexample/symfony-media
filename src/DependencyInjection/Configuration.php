<?php

namespace Wexample\SymfonyMedia\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_media');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('storage_dir')
                    ->defaultValue('%kernel.project_dir%/var/media')
                    ->info('Where the files are kept, out of the public directory: every file goes through the access check.')
                ->end()
                ->arrayNode('thumbnail_sizes')
                    ->info('The widths a thumbnail is drawn at, in pixels. Only these are drawn, so that nobody fills the disk by asking for every size.')
                    ->integerPrototype()->min(16)->max(4096)->end()
                    ->defaultValue([160, 480])
                ->end()
                ->integerNode('upload_max_size')
                    ->info('The largest file the library takes, in bytes; null takes any size. Checked by the server, whatever the browser says.')
                    ->min(1)
                    ->defaultNull()
                ->end()
                ->scalarNode('view_role')
                    ->defaultValue('ROLE_USER')
                    ->info('Role needed to browse the library and read its files.')
                ->end()
                ->scalarNode('manage_role')
                    ->defaultValue('ROLE_ADMIN')
                    ->info('Role needed to add, rename, move and delete media and folders.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
