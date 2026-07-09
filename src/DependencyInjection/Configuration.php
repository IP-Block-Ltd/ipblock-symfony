<?php

declare(strict_types=1);

namespace IpBlock\SymfonyBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines and validates the "ip_block" configuration tree.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ip_block');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->booleanNode('enabled')->defaultTrue()->end()
                ->scalarNode('site_id')->defaultValue('')->end()
                ->scalarNode('api_key')->defaultValue('')->end()
                ->scalarNode('api_url')->defaultValue('https://api.ip-block.com/v1/check')->end()
                ->booleanNode('fail_open')->defaultTrue()->end()
                ->integerNode('cache_ttl')->defaultValue(300)->end()
                ->floatNode('timeout')->defaultValue(1.0)->end()
                ->booleanNode('behind_proxy')->defaultFalse()->end()
                ->enumNode('block_action')
                    ->values(['403', 'redirect'])
                    ->defaultValue('403')
                ->end()
                ->scalarNode('redirect_url')->defaultValue('https://www.ip-block.com/blocked.php')->end()
                ->scalarNode('block_message')->defaultValue('Access denied.')->end()
                ->arrayNode('whitelist')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
