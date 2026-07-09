> ⚠️ **Status: untested.** This extension is provided as-is and has **not been tested in production**. Please feel free to fork, modify, improve, and open pull requests.
>
> Licensed under **GNU GPLv3** (see [LICENSE](LICENSE)).

# IP Block — Symfony Bundle

A Symfony bundle that checks every incoming request against the
[ip-block.com](https://www.ip-block.com) service on the `kernel.request` event
and blocks disallowed clients.

**Targets:** Symfony 6.4 LTS and 7.x, PHP 8.1+.

## Install

```bash
composer require ip-block/symfony-bundle
```

If you do not use Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    IpBlock\SymfonyBundle\IpBlockBundle::class => ['all' => true],
];
```

## Configure

Create `config/packages/ip_block.yaml`:

```yaml
ip_block:
    enabled: true
    site_id: '%env(IP_BLOCK_SITE_ID)%'
    api_key: '%env(IP_BLOCK_API_KEY)%'
    api_url: 'https://api.ip-block.com/v1/check'   # default
    fail_open: true          # allow on error/timeout (default)
    cache_ttl: 300           # seconds (uses cache.app pool)
    timeout: 1.0             # seconds
    behind_proxy: false      # trust X-Forwarded-For / CF-Connecting-IP
    block_action: '403'      # '403' or 'redirect'
    redirect_url: 'https://www.ip-block.com/blocked.php'
    block_message: 'Access denied.'
    whitelist:
        - '127.0.0.1'
        - '10.0.0.0/8'
```

Add the secrets to `.env.local`:

```dotenv
IP_BLOCK_SITE_ID=your-site-id
IP_BLOCK_API_KEY=your-api-key
```

## How it works

- Builds the JSON body `{api_key, site_id, ip, user_agent, referrer}` and
  `POST`s it to the API with a **1 second timeout**.
- Blocks only when the response is `{"action":"block"}`.
- **Fails open** (allows) on any error, timeout, non-2xx, or missing `action`
  — set `fail_open: false` to fail closed.
- Caches each decision for `cache_ttl` seconds in the framework cache
  (`cache.app`), keyed by `md5(ip|user_agent|referrer)`.
- Honours the `whitelist` (single IPs and CIDR ranges via `IpUtils`).
- Reads the real client IP; with `behind_proxy: true` it trusts
  `CF-Connecting-IP` then the first `X-Forwarded-For` hop.

Route-scoping is left to you — the subscriber runs on all main requests.
