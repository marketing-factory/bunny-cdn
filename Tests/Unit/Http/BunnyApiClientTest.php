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
                ],
            )
            ->willReturn(new Response());

        $subject = new BunnyApiClient($requestFactory);
        $subject->purgeUrl('secret-key', 'https://example.com/foo');
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
                ],
            )
            ->willReturn(new Response());

        $subject = new BunnyApiClient($requestFactory);
        $subject->purgeTag('secret-key', 7, 'pageId_5');
    }
}
