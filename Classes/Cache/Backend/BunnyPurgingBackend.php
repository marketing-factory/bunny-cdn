<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Cache\Backend;

use Mfd\BunnyCdn\Service\BunnyCdnService;
use TYPO3\CMS\Core\Cache\Backend\BackendInterface;
use TYPO3\CMS\Core\Cache\Backend\TaggableBackendInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Decorates the real "pages" cache backend: every local flush-by-tag also
 * triggers a Bunny CDN purge for the same tag (and, for page tags, the
 * page's frontend URL).
 *
 * Instantiated directly via `new` by TYPO3\CMS\Core\Cache\CacheManager, so it
 * cannot rely on constructor autowiring — the wrapped backend and its own
 * options are passed through under the `innerBackend`/`innerOptions` keys
 * (see ext_localconf.php), and everything else needed is fetched via
 * GeneralUtility::makeInstance() inside the constructor.
 */
class BunnyPurgingBackend implements BackendInterface, TaggableBackendInterface
{
    private readonly BackendInterface&TaggableBackendInterface $innerBackend;

    public function __construct(array $options = [])
    {
        $innerBackendClass = $options['innerBackend'];
        $innerOptions = $options['innerOptions'] ?? [];
        $this->innerBackend = new $innerBackendClass($innerOptions);
    }

    public function setCache(FrontendInterface $cache): void
    {
        $this->innerBackend->setCache($cache);
    }

    public function set(string $entryIdentifier, string $data, array $tags = [], ?int $lifetime = null): void
    {
        $this->innerBackend->set($entryIdentifier, $data, $tags, $lifetime);
    }

    public function get(string $entryIdentifier): mixed
    {
        return $this->innerBackend->get($entryIdentifier);
    }

    public function has(string $entryIdentifier): bool
    {
        return $this->innerBackend->has($entryIdentifier);
    }

    public function remove(string $entryIdentifier): bool
    {
        return $this->innerBackend->remove($entryIdentifier);
    }

    public function flush(): void
    {
        $this->innerBackend->flush();
    }

    public function collectGarbage(): void
    {
        $this->innerBackend->collectGarbage();
    }

    public function flushByTag(string $tag): void
    {
        $this->flushByTags([$tag]);
    }

    public function flushByTags(array $tags): void
    {
        $this->innerBackend->flushByTags($tags);
        $this->purgeBunny($tags);
    }

    public function findIdentifiersByTag(string $tag): array
    {
        return $this->innerBackend->findIdentifiersByTag($tag);
    }

    private function purgeBunny(array $tags): void
    {
        try {
            GeneralUtility::makeInstance(BunnyCdnService::class)->purgeByTags($tags);
        } catch (\Exception $exception) {
            GeneralUtility::makeInstance(LogManager::class)
                ->getLogger(self::class)
                ->error('Bunny CDN purge failed', ['exception' => $exception]);
        }
    }
}
