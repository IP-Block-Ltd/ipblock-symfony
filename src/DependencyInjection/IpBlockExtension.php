<?php

declare(strict_types=1);

namespace IpBlock\SymfonyBundle\DependencyInjection;

use IpBlock\SymfonyBundle\Client\IpBlockClient;
use IpBlock\SymfonyBundle\EventSubscriber\IpBlockSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires the bundle services from the resolved configuration.
 */
final class IpBlockExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $clientDef = $container->register(IpBlockClient::class, IpBlockClient::class)
            ->setAutowired(true)
            ->setArgument('$apiUrl', $config['api_url'])
            ->setArgument('$apiKey', $config['api_key'])
            ->setArgument('$siteId', $config['site_id'])
            ->setArgument('$failOpen', $config['fail_open'])
            ->setArgument('$timeout', $config['timeout'])
            ->setArgument('$cacheTtl', $config['cache_ttl']);

        // Optional cache pool (falls back to null => no caching if unavailable).
        if ($container->has('cache.app')) {
            $clientDef->setArgument('$cache', new Reference('cache.app'));
        }
        if ($container->has('http_client')) {
            $clientDef->setArgument('$httpClient', new Reference('http_client'));
        }
        if ($container->has('logger')) {
            $clientDef->setArgument('$logger', new Reference('logger'));
        }

        $container->register(IpBlockSubscriber::class, IpBlockSubscriber::class)
            ->setArgument('$client', new Reference(IpBlockClient::class))
            ->setArgument('$enabled', $config['enabled'])
            ->setArgument('$behindProxy', $config['behind_proxy'])
            ->setArgument('$blockAction', $config['block_action'])
            ->setArgument('$redirectUrl', $config['redirect_url'])
            ->setArgument('$blockMessage', $config['block_message'])
            ->setArgument('$whitelist', $config['whitelist'])
            ->addTag('kernel.event_subscriber');
    }

    public function getAlias(): string
    {
        return 'ip_block';
    }
}
