<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Tests\Unit\Cache\Backend;

use Mfd\BunnyCdn\Cache\Backend\BunnyPurgingBackend;
use Mfd\BunnyCdn\Service\BunnyCdnService;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BunnyPurgingBackendTest extends UnitTestCase
{
    // TransientMemoryBackend/LogManager register themselves as singletons via makeInstance().
    protected bool $resetSingletonInstances = true;

    private function createSubject(): BunnyPurgingBackend
    {
        $backend = new BunnyPurgingBackend([
            'innerBackend' => TransientMemoryBackend::class,
            'innerOptions' => [],
        ]);
        $backend->setCache(new VariableFrontend('pages', $backend));

        return $backend;
    }

    public function testFlushByTagsFlushesInnerBackendAndTriggersBunnyPurge(): void
    {
        $subject = $this->createSubject();
        $subject->set('entry', 'payload', ['pageId_5']);

        $purgeService = $this->createMock(BunnyCdnService::class);
        $purgeService->expects($this->once())->method('purgeByTags')->with(['pageId_5']);
        GeneralUtility::addInstance(BunnyCdnService::class, $purgeService);

        $subject->flushByTags(['pageId_5']);

        self::assertFalse($subject->get('entry'));
    }

    public function testFlushByTagDelegatesToFlushByTagsWithSingleTag(): void
    {
        $subject = $this->createSubject();

        $purgeService = $this->createMock(BunnyCdnService::class);
        $purgeService->expects($this->once())->method('purgeByTags')->with(['tt_content_42']);
        GeneralUtility::addInstance(BunnyCdnService::class, $purgeService);

        $subject->flushByTag('tt_content_42');
    }

    public function testFlushByTagsStillFlushesInnerBackendWhenBunnyPurgeThrows(): void
    {
        $subject = $this->createSubject();
        $subject->set('entry', 'payload', ['pageId_6']);

        $purgeService = $this->createMock(BunnyCdnService::class);
        $purgeService->method('purgeByTags')->willThrowException(new \RuntimeException('Bunny API is down'));
        GeneralUtility::addInstance(BunnyCdnService::class, $purgeService);

        $subject->flushByTags(['pageId_6']);

        self::assertFalse($subject->get('entry'));
    }
}
