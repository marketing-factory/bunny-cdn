<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Http;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Thin wrapper around the Bunny.net Pull Zone purge API.
 *
 * @see https://docs.bunny.net/reference/pullzonepublic_purgecache
 */
class BunnyApiClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_BASE_URL = 'https://api.bunny.net';

    public function __construct(private readonly RequestFactory $requestFactory) {}

    public function purgeUrl(string $accessKey, int $pullZoneId, string $url): void
    {
        $this->purge($accessKey, $pullZoneId, ['Url' => $url]);
    }

    public function purgeTag(string $accessKey, int $pullZoneId, string $tag): void
    {
        $this->purge($accessKey, $pullZoneId, ['CacheTag' => $tag]);
    }

    private function purge(string $accessKey, int $pullZoneId, array $body): void
    {
        $this->requestFactory->request(
            self::API_BASE_URL . '/pullzone/' . $pullZoneId . '/purgeCache',
            'POST',
            [
                'headers' => [
                    'AccessKey' => $accessKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'timeout' => 5,
            ],
        );
    }
}
