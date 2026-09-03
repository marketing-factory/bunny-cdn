<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Thin wrapper around the Bunny.net purge API. Never throws on a non-2xx
 * response (`http_errors` disabled) — callers branch on the status code
 * themselves, in particular to detect a 429 (rate limited) and retry it.
 * Still throws on transport-level failures (timeout, DNS, connection
 * refused, ...).
 *
 * @see https://docs.bunny.net/api-reference/core/purge/purge-url
 * @see https://docs.bunny.net/api-reference/core/pull-zone/purge-cache
 */
class BunnyApiClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_BASE_URL = 'https://api.bunny.net';

    public function __construct(private readonly RequestFactory $requestFactory) {}

    /**
     * Purges a URL across every pull zone serving it. Dedicated endpoint,
     * separate from the pull-zone-scoped purgeCache one below — no pull
     * zone ID, and purged asynchronously so this doesn't block on Bunny's
     * purge logic completing.
     */
    public function purgeUrl(string $accessKey, string $url): ResponseInterface
    {
        return $this->requestFactory->request(
            self::API_BASE_URL . '/purge',
            'POST',
            [
                'headers' => [
                    'AccessKey' => $accessKey,
                ],
                'query' => [
                    'url' => $url,
                    'async' => 'true',
                ],
                'timeout' => 5,
                'http_errors' => false,
            ],
        );
    }

    public function purgeTag(string $accessKey, int $pullZoneId, string $tag): ResponseInterface
    {
        return $this->requestFactory->request(
            self::API_BASE_URL . '/pullzone/' . $pullZoneId . '/purgeCache',
            'POST',
            [
                'headers' => [
                    'AccessKey' => $accessKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['CacheTag' => $tag],
                'timeout' => 5,
                'http_errors' => false,
            ],
        );
    }
}
