<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Thin wrapper around the Bunny.net Pull Zone purge API.
 *
 * @see https://docs.bunny.net/reference/pullzonepublic_purgecache
 */
class BunnyApiClient implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_BASE_URL = 'https://api.bunny.net';

    private readonly ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client(['timeout' => 5]);
    }

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
        $this->client->request('POST', self::API_BASE_URL . '/pullzone/' . $pullZoneId . '/purgeCache', [
            'headers' => [
                'AccessKey' => $accessKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);
    }
}
