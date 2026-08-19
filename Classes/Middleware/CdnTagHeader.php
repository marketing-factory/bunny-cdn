<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;

/**
 * Adds the "CDN-Tag" header Bunny CDN uses for tag-based purging, reusing the
 * same cache tags TYPO3 core already collects for each page render (page ID,
 * content elements, any custom `cache.tags` set via TypoScript).
 *
 * @see https://bunny.net/docs/cdn/purge-cache
 */
class CdnTagHeader implements MiddlewareInterface
{
    /**
     * Bunny truncates CDN-Tag header values longer than this.
     */
    private const MAX_HEADER_LENGTH = 1024;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $collector = $request->getAttribute('frontend.cache.collector');
        if (!$collector instanceof CacheDataCollector) {
            return $response;
        }

        $tagNames = array_map(static fn(CacheTag $cacheTag): string => $cacheTag->name, $collector->getCacheTags());
        if ($tagNames === []) {
            return $response;
        }

        $headerValue = substr(implode(' ', $tagNames), 0, self::MAX_HEADER_LENGTH);

        return $response->withHeader('CDN-Tag', $headerValue);
    }
}
