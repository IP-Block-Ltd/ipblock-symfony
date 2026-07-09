<?php

declare(strict_types=1);

namespace IpBlock\SymfonyBundle\EventSubscriber;

use IpBlock\SymfonyBundle\Client\IpBlockClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs on kernel.request and blocks clients rejected by ip-block.com.
 */
final class IpBlockSubscriber implements EventSubscriberInterface
{
    /**
     * @param string[] $whitelist
     */
    public function __construct(
        private readonly IpBlockClient $client,
        private readonly bool $enabled = true,
        private readonly bool $behindProxy = false,
        private readonly string $blockAction = '403',
        private readonly string $redirectUrl = 'https://www.ip-block.com/blocked.php',
        private readonly string $blockMessage = 'Access denied.',
        private readonly array $whitelist = [],
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // High priority so blocking happens before most application logic.
        return [KernelEvents::REQUEST => ['onKernelRequest', 4096]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $ip = $this->clientIp($request);

        if ($ip === '' || $this->isWhitelisted($ip)) {
            return;
        }

        $userAgent = (string) $request->headers->get('User-Agent', '');
        $referrer = (string) $request->headers->get('Referer', '');

        if (!$this->client->isBlocked($ip, $userAgent, $referrer)) {
            return;
        }

        if ($this->blockAction === 'redirect') {
            $event->setResponse(new RedirectResponse($this->redirectUrl, Response::HTTP_FOUND));

            return;
        }

        $event->setResponse(new Response($this->blockMessage, Response::HTTP_FORBIDDEN));
    }

    private function clientIp(Request $request): string
    {
        if ($this->behindProxy) {
            $cf = $request->headers->get('CF-Connecting-IP');
            if ($cf !== null && $cf !== '') {
                return trim($cf);
            }

            $xff = $request->headers->get('X-Forwarded-For');
            if ($xff !== null && $xff !== '') {
                // First hop is the original client.
                $parts = explode(',', $xff);

                return trim($parts[0]);
            }
        }

        return (string) $request->getRemoteAddr();
    }

    private function isWhitelisted(string $ip): bool
    {
        return $this->whitelist !== [] && IpUtils::checkIp($ip, $this->whitelist);
    }
}
