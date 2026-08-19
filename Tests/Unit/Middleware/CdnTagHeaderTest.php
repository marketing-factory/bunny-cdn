<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Tests\Unit\Middleware;

use Mfd\BunnyCdn\Middleware\CdnTagHeader;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class CdnTagHeaderTest extends UnitTestCase
{
    private function createHandlerReturning(Response $response): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    public function testAddsCdnTagHeaderFromCollectedCacheTags(): void
    {
        $collector = new CacheDataCollector();
        $collector->addCacheTags(new CacheTag('pageId_5'), new CacheTag('tt_content_42'));

        $request = (new ServerRequest('https://example.com/'))
            ->withAttribute('frontend.cache.collector', $collector);

        $response = (new CdnTagHeader())->process($request, $this->createHandlerReturning(new Response()));

        self::assertSame('pageId_5 tt_content_42', $response->getHeaderLine('CDN-Tag'));
    }

    public function testDoesNotAddHeaderWithoutCacheCollectorAttribute(): void
    {
        $request = new ServerRequest('https://example.com/');

        $response = (new CdnTagHeader())->process($request, $this->createHandlerReturning(new Response()));

        self::assertFalse($response->hasHeader('CDN-Tag'));
    }

    public function testDoesNotAddHeaderWhenNoCacheTagsWereCollected(): void
    {
        $request = (new ServerRequest('https://example.com/'))
            ->withAttribute('frontend.cache.collector', new CacheDataCollector());

        $response = (new CdnTagHeader())->process($request, $this->createHandlerReturning(new Response()));

        self::assertFalse($response->hasHeader('CDN-Tag'));
    }

    public function testTruncatesHeaderValueToBunnysOneKilobyteLimit(): void
    {
        $collector = new CacheDataCollector();
        $collector->addCacheTags(new CacheTag(str_repeat('a', 2000)));

        $request = (new ServerRequest('https://example.com/'))
            ->withAttribute('frontend.cache.collector', $collector);

        $response = (new CdnTagHeader())->process($request, $this->createHandlerReturning(new Response()));

        self::assertSame(1024, strlen($response->getHeaderLine('CDN-Tag')));
    }
}
