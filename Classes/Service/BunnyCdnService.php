<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Service;

use Mfd\BunnyCdn\Http\BunnyApiClient;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Purges the Bunny CDN edge cache for whatever page/tag combination TYPO3 just
 * flushed from its own "pages" cache, and answers whether Bunny CDN is active
 * for a given site.
 */
class BunnyCdnService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const PAGE_TAG_PATTERN = '/^pageId_(\d+)$/';

    public function __construct(
        private readonly BunnyApiClient $client,
        private readonly SiteFinder $siteFinder,
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @param string[] $tags
     */
    public function purgeByTags(array $tags): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $accessKey = $this->getAccessKey();
        if ($accessKey === '') {
            return;
        }

        $pullZoneIds = $this->getConfiguredPullZoneIds();
        if ($pullZoneIds === []) {
            return;
        }

        foreach ($tags as $tag) {
            foreach ($pullZoneIds as $pullZoneId) {
                $this->purgeTagSafely($accessKey, $pullZoneId, $tag);
            }

            if (preg_match(self::PAGE_TAG_PATTERN, $tag, $matches) === 1) {
                $this->purgePageUrls($accessKey, (int)$matches[1]);
            }
        }
    }

    public function isActiveForSite(Site $site): bool
    {
        return $this->isEnabled()
            && $this->getAccessKey() !== ''
            && $this->getPullZoneIdForSite($site) !== null;
    }

    private function purgePageUrls(string $accessKey, int $pageId): void
    {
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (\Exception) {
            // Page has no site (e.g. deleted, or outside any site root) — nothing to purge by URL.
            return;
        }

        $pullZoneId = $this->getPullZoneIdForSite($site);
        if ($pullZoneId === null) {
            return;
        }

        $router = $site->getRouter();
        foreach ($site->getLanguages() as $language) {
            try {
                $url = (string)$router->generateUri(
                    $pageId,
                    ['_language' => $language],
                    '',
                    RouterInterface::ABSOLUTE_URL
                );
            } catch (\Exception $exception) {
                $this->logger?->warning('Could not generate URL for Bunny CDN purge', [
                    'pageId' => $pageId,
                    'language' => $language->getLanguageId(),
                    'exception' => $exception,
                ]);
                continue;
            }

            $this->purgeUrlSafely($accessKey, $pullZoneId, $url);
        }
    }

    private function purgeTagSafely(string $accessKey, int $pullZoneId, string $tag): void
    {
        try {
            $this->client->purgeTag($accessKey, $pullZoneId, $tag);
        } catch (\Exception $exception) {
            $this->logger?->error('Bunny CDN tag purge failed', [
                'pullZoneId' => $pullZoneId,
                'tag' => $tag,
                'exception' => $exception,
            ]);
        }
    }

    private function purgeUrlSafely(string $accessKey, int $pullZoneId, string $url): void
    {
        try {
            $this->client->purgeUrl($accessKey, $url);
        } catch (\Exception $exception) {
            $this->logger?->error('Bunny CDN URL purge failed', [
                'pullZoneId' => $pullZoneId,
                'url' => $url,
                'exception' => $exception,
            ]);
        }
    }

    private function isEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('bunny_cdn', 'enabled');
        } catch (\Exception) {
            return false;
        }
    }

    private function getAccessKey(): string
    {
        try {
            return (string)$this->extensionConfiguration->get('bunny_cdn', 'apiKey');
        } catch (\Exception) {
            return '';
        }
    }

    private function getPullZoneIdForSite(Site $site): ?int
    {
        try {
            $pullZoneId = (int)$site->getAttribute('bunny_pull_zone_id');
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $pullZoneId > 0 ? $pullZoneId : null;
    }

    /**
     * @return int[]
     */
    private function getConfiguredPullZoneIds(): array
    {
        $pullZoneIds = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $pullZoneId = $this->getPullZoneIdForSite($site);
            if ($pullZoneId !== null) {
                $pullZoneIds[$pullZoneId] = $pullZoneId;
            }
        }

        return array_values($pullZoneIds);
    }
}
