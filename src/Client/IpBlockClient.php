<?php

declare(strict_types=1);

namespace IpBlock\SymfonyBundle\Client;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the ip-block.com API and caches decisions per client fingerprint.
 *
 * Fails open (returns "allow") on any error/timeout/non-2xx/missing action,
 * unless fail_open is disabled.
 */
final class IpBlockClient
{
    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $siteId,
        private readonly bool $failOpen = true,
        private readonly float $timeout = 1.0,
        private readonly int $cacheTtl = 300,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return bool true when the request should be blocked
     */
    public function isBlocked(string $ip, string $userAgent, string $referrer): bool
    {
        $cacheKey = 'ip_block_' . md5($ip . '|' . $userAgent . '|' . $referrer);

        if ($this->cache !== null) {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                return (bool) $item->get();
            }
        }

        $blocked = $this->query($ip, $userAgent, $referrer);

        if ($this->cache !== null && isset($item)) {
            $item->set($blocked);
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);
        }

        return $blocked;
    }

    private function query(string $ip, string $userAgent, string $referrer): bool
    {
        if ($this->httpClient === null) {
            return $this->failClosedOrOpen();
        }

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'api_key' => $this->apiKey,
                    'site_id' => $this->siteId,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'referrer' => $referrer,
                ],
                'timeout' => $this->timeout,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return $this->failClosedOrOpen();
            }

            $data = $response->toArray(false);
            if (!isset($data['action'])) {
                return $this->failClosedOrOpen();
            }

            return $data['action'] === 'block';
        } catch (\Throwable $e) {
            $this->logger?->warning('ip-block check failed: ' . $e->getMessage());

            return $this->failClosedOrOpen();
        }
    }

    private function failClosedOrOpen(): bool
    {
        // fail open => allow => not blocked (false); fail closed => blocked (true)
        return !$this->failOpen;
    }
}
