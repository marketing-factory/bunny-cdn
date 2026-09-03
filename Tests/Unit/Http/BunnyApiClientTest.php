<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Tests\Unit\Http;

use Mfd\BunnyCdn\Http\BunnyApiClient;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BunnyApiClientTest extends UnitTestCase
{
    public function testPurgeUrlSendsExpectedRequest(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects($this->once())->method('request')
            ->with(
                'https://api.bunny.net/purge',
                'POST',
                [
                    'headers' => [
                        'AccessKey' => 'secret-key',
                    ],
                    'query' => [
                        'url' => 'https://example.com/foo',
                        'async' => 'true',
                    ],
                    'timeout' => 5,
                    'http_errors' => false,
                ],
            )
            ->willReturn(new Response());

        $subject = new BunnyApiClient($requestFactory);
        $response = $subject->purgeUrl('secret-key', 'https://example.com/foo');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testPurgeUrlReturnsTheRateLimitedResponseInsteadOfThrowing(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response(statusCode: 429));

        $subject = new BunnyApiClient($requestFactory);
        $response = $subject->purgeUrl('secret-key', 'https://example.com/foo');

        self::assertSame(429, $response->getStatusCode());
    }

    public function testPurgeTagSendsExpectedRequest(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects($this->once())->method('request')
            ->with(
                'https://api.bunny.net/pullzone/7/purgeCache',
                'POST',
                [
                    'headers' => [
                        'AccessKey' => 'secret-key',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => ['CacheTag' => 'pageId_5'],
                    'timeout' => 5,
                    'http_errors' => false,
                ],
            )
            ->willReturn(new Response());

        $subject = new BunnyApiClient($requestFactory);
        $response = $subject->purgeTag('secret-key', 7, 'pageId_5');

        self::assertSame(200, $response->getStatusCode());
    }

    public function testPurgeTagReturnsTheRateLimitedResponseInsteadOfThrowing(): void
    {
        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->method('request')->willReturn(new Response(statusCode: 429));

        $subject = new BunnyApiClient($requestFactory);
        $response = $subject->purgeTag('secret-key', 7, 'pageId_5');

        self::assertSame(429, $response->getStatusCode());
    }
}
