<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Tests\Unit\Service;

use GuzzleHttp\Psr7\Uri;
use Mfd\BunnyCdn\Http\BunnyApiClient;
use Mfd\BunnyCdn\Service\BunnyCdnService;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BunnyCdnServiceTest extends UnitTestCase
{
    private function createExtensionConfiguration(string $apiKey, bool $enabled = true): ExtensionConfiguration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturnMap([
            ['bunny_cdn', 'enabled', $enabled],
            ['bunny_cdn', 'apiKey', $apiKey],
        ]);

        return $extensionConfiguration;
    }

    private function createSite(string $identifier, int $rootPageId, int $pullZoneId, array $languageIds = [0]): Site
    {
        $languages = [];
        foreach ($languageIds as $languageId) {
            $languages[] = [
                'languageId' => $languageId,
                'locale' => $languageId === 0 ? 'de_DE.UTF-8' : 'en_US.UTF-8',
                'base' => $languageId === 0 ? '/' : '/en/',
            ];
        }

        return new Site($identifier, $rootPageId, [
            'base' => 'https://' . $identifier . '.example.com/',
            'bunny_pull_zone_id' => $pullZoneId,
            'languages' => $languages,
        ]);
    }

    /**
     * Registers a fake PageRouter for the *next* Site::getRouter() call.
     * Site::getRouter() always does `new PageRouter($this, $context)` with
     * non-empty constructor arguments, which GeneralUtility::makeInstance()
     * only lets addInstance() intercept (a container lookup would be skipped).
     */
    private function fakeRouterGenerating(UriInterface $uri): PageRouter
    {
        $router = new class ($uri) extends PageRouter {
            public function __construct(private readonly UriInterface $uri) {}

            public function generateUri($route, array $parameters = [], string $fragment = '', string $type = ''): UriInterface
            {
                return $this->uri;
            }
        };
        GeneralUtility::addInstance(PageRouter::class, $router);

        return $router;
    }

    public function testPurgeByTagsDoesNothingWhenDisabled(): void
    {
        $client = $this->createMock(BunnyApiClient::class);
        $client->expects($this->never())->method(self::anything());
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects($this->never())->method(self::anything());

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration('secret-key', false));
        $subject->purgeByTags(['pageId_5']);
    }

    public function testPurgeByTagsDoesNothingWithoutAccessKey(): void
    {
        $client = $this->createMock(BunnyApiClient::class);
        $client->expects($this->never())->method(self::anything());
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->expects($this->never())->method(self::anything());

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration(''));
        $subject->purgeByTags(['pageId_5']);
    }

    public function testPurgeByTagsDoesNothingWithoutConfiguredPullZones(): void
    {
        $client = $this->createMock(BunnyApiClient::class);
        $client->expects($this->never())->method(self::anything());
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([]);

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration('secret-key'));
        $subject->purgeByTags(['pageId_5']);
    }

    public function testPurgeByTagsPurgesTagOnEveryConfiguredPullZone(): void
    {
        $siteDe = $this->createSite('de', 1, 42);
        $siteCom = $this->createSite('com', 2, 99);

        $client = $this->createMock(BunnyApiClient::class);
        $client->expects($this->exactly(2))->method('purgeTag')
            ->with('secret-key', self::logicalOr(42, 99), 'tt_content_7');
        $client->expects($this->never())->method('purgeUrl');

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$siteDe, $siteCom]);
        $siteFinder->expects($this->never())->method('getSiteByPageId');

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration('secret-key'));
        $subject->purgeByTags(['tt_content_7']);
    }

    public function testPurgeByTagsAlsoPurgesPageUrlForEveryLanguageOnPageTags(): void
    {
        $site = $this->createSite('de', 1, 42, [0, 1]);

        $client = $this->createMock(BunnyApiClient::class);
        $client->expects($this->once())->method('purgeTag')->with('secret-key', 42, 'pageId_5');
        $client->expects($this->exactly(2))->method('purgeUrl')
            ->with('secret-key', 42, 'https://de.example.com/page-5');

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$site]);
        $siteFinder->method('getSiteByPageId')->with(5)->willReturn($site);

        $this->fakeRouterGenerating(new Uri('https://de.example.com/page-5'));

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration('secret-key'));
        $subject->purgeByTags(['pageId_5']);
    }

    public function testPurgeByTagsSkipsUrlPurgeWhenPageHasNoSite(): void
    {
        $site = $this->createSite('de', 1, 42);

        $client = $this->createMock(BunnyApiClient::class);
        $client->expects($this->once())->method('purgeTag')->with('secret-key', 42, 'pageId_999');
        $client->expects($this->never())->method('purgeUrl');

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$site]);
        $siteFinder->method('getSiteByPageId')->with(999)
            ->willThrowException(new SiteNotFoundException('no site', 1234));

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration('secret-key'));
        $subject->purgeByTags(['pageId_999']);
    }

    public function testPurgeByTagsDoesNotBreakWhenBunnyApiThrows(): void
    {
        $site = $this->createSite('de', 1, 42);

        $client = $this->createMock(BunnyApiClient::class);
        $client->method('purgeTag')->willThrowException(new \RuntimeException('Bunny API is down'));

        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn([$site]);
        $siteFinder->expects($this->never())->method('getSiteByPageId');

        $subject = new BunnyCdnService($client, $siteFinder, $this->createExtensionConfiguration('secret-key'));
        $subject->purgeByTags(['tt_content_7']);
    }

    public function testIsActiveForSiteIsFalseWhenDisabled(): void
    {
        $site = $this->createSite('de', 1, 42);
        $subject = new BunnyCdnService(
            $this->createMock(BunnyApiClient::class),
            $this->createMock(SiteFinder::class),
            $this->createExtensionConfiguration('secret-key', false),
        );

        self::assertFalse($subject->isActiveForSite($site));
    }

    public function testIsActiveForSiteIsFalseWithoutAccessKey(): void
    {
        $site = $this->createSite('de', 1, 42);
        $subject = new BunnyCdnService(
            $this->createMock(BunnyApiClient::class),
            $this->createMock(SiteFinder::class),
            $this->createExtensionConfiguration(''),
        );

        self::assertFalse($subject->isActiveForSite($site));
    }

    public function testIsActiveForSiteIsFalseWithoutPullZone(): void
    {
        $site = $this->createSite('de', 1, 0);
        $subject = new BunnyCdnService(
            $this->createMock(BunnyApiClient::class),
            $this->createMock(SiteFinder::class),
            $this->createExtensionConfiguration('secret-key'),
        );

        self::assertFalse($subject->isActiveForSite($site));
    }

    public function testIsActiveForSiteIsTrueWhenEverythingConfigured(): void
    {
        $site = $this->createSite('de', 1, 42);
        $subject = new BunnyCdnService(
            $this->createMock(BunnyApiClient::class),
            $this->createMock(SiteFinder::class),
            $this->createExtensionConfiguration('secret-key'),
        );

        self::assertTrue($subject->isActiveForSite($site));
    }
}
