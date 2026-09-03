<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Service;

use Mfd\BunnyCdn\Http\BunnyApiClient;
use Mfd\BunnyCdn\Message\RetryPurgeTagMessage;
use Mfd\BunnyCdn\Message\RetryPurgeUrlMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
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
    private const RATE_LIMITED_STATUS_CODE = 429;
    private const RETRY_DELAY_MS = 5000;

    public function __construct(
        private readonly BunnyApiClient $client,
        private readonly SiteFinder $siteFinder,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly MessageBusInterface $messageBus,
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

    /**
     * Retries a single tag purge that was rate-limited (429) the first time
     * around. Called by RetryPurgeMessageHandler. Goes through the same
     * purgeTagSafely() as the original purge, so a 429 here schedules
     * *another* delayed retry — Bunny getting rate-limited again just keeps
     * pushing this out 5s at a time until it succeeds (or asyncRetryEnabled
     * gets turned off).
     */
    public function retryPurgeTag(int $pullZoneId, string $tag): void
    {
        $accessKey = $this->getAccessKey();
        if ($accessKey === '') {
            return;
        }

        $this->purgeTagSafely($accessKey, $pullZoneId, $tag);
    }

    /**
     * @see retryPurgeTag()
     */
    public function retryPurgeUrl(string $url): void
    {
        $accessKey = $this->getAccessKey();
        if ($accessKey === '') {
            return;
        }

        $this->purgeUrlSafely($accessKey, null, $url);
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
            $response = $this->client->purgeTag($accessKey, $pullZoneId, $tag);
        } catch (\Exception $exception) {
            $this->logger?->error('Bunny CDN tag purge failed', [
                'pullZoneId' => $pullZoneId,
                'tag' => $tag,
                'exception' => $exception,
            ]);
            return;
        }

        if ($response->getStatusCode() === self::RATE_LIMITED_STATUS_CODE) {
            $this->scheduleRetry(
                new RetryPurgeTagMessage($pullZoneId, $tag),
                'tag',
                ['pullZoneId' => $pullZoneId, 'tag' => $tag],
            );
            return;
        }

        if ($response->getStatusCode() >= 400) {
            $this->logger?->error('Bunny CDN tag purge failed', [
                'pullZoneId' => $pullZoneId,
                'tag' => $tag,
                'statusCode' => $response->getStatusCode(),
            ]);
        }
    }

    private function purgeUrlSafely(string $accessKey, ?int $pullZoneId, string $url): void
    {
        try {
            $response = $this->client->purgeUrl($accessKey, $url);
        } catch (\Exception $exception) {
            $this->logger?->error('Bunny CDN URL purge failed', [
                'pullZoneId' => $pullZoneId,
                'url' => $url,
                'exception' => $exception,
            ]);
            return;
        }

        if ($response->getStatusCode() === self::RATE_LIMITED_STATUS_CODE) {
            $this->scheduleRetry(
                new RetryPurgeUrlMessage($url),
                'URL',
                ['pullZoneId' => $pullZoneId, 'url' => $url],
            );
            return;
        }

        if ($response->getStatusCode() >= 400) {
            $this->logger?->error('Bunny CDN URL purge failed', [
                'pullZoneId' => $pullZoneId,
                'url' => $url,
                'statusCode' => $response->getStatusCode(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $logContext
     */
    private function scheduleRetry(object $message, string $kind, array $logContext): void
    {
        if (!$this->asyncRetryEnabled()) {
            $this->logger?->warning("Bunny CDN {$kind} purge rate-limited (429), dropped — enable asyncRetryEnabled to retry it", $logContext);
            return;
        }

        $this->messageBus->dispatch($message, [new DelayStamp(self::RETRY_DELAY_MS)]);
        $this->logger?->info("Bunny CDN {$kind} purge rate-limited (429), scheduled for async retry", $logContext);
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

    private function asyncRetryEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('bunny_cdn', 'asyncRetryEnabled');
        } catch (\Exception) {
            return false;
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
