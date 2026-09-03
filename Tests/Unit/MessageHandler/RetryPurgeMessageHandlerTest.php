<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Tests\Unit\MessageHandler;

use Mfd\BunnyCdn\Message\RetryPurgeTagMessage;
use Mfd\BunnyCdn\Message\RetryPurgeUrlMessage;
use Mfd\BunnyCdn\MessageHandler\RetryPurgeMessageHandler;
use Mfd\BunnyCdn\Service\BunnyCdnService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RetryPurgeMessageHandlerTest extends UnitTestCase
{
    public function testRetryPurgeTagDelegatesToService(): void
    {
        $cdnService = $this->createMock(BunnyCdnService::class);
        $cdnService->expects($this->once())->method('retryPurgeTag')->with(42, 'tt_content_7');

        $subject = new RetryPurgeMessageHandler($cdnService);
        $subject->retryPurgeTag(new RetryPurgeTagMessage(42, 'tt_content_7'));
    }

    public function testRetryPurgeUrlDelegatesToService(): void
    {
        $cdnService = $this->createMock(BunnyCdnService::class);
        $cdnService->expects($this->once())->method('retryPurgeUrl')->with('https://example.com/foo');

        $subject = new RetryPurgeMessageHandler($cdnService);
        $subject->retryPurgeUrl(new RetryPurgeUrlMessage('https://example.com/foo'));
    }
}
