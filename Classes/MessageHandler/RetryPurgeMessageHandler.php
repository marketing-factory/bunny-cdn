<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\MessageHandler;

use Mfd\BunnyCdn\Message\RetryPurgeTagMessage;
use Mfd\BunnyCdn\Message\RetryPurgeUrlMessage;
use Mfd\BunnyCdn\Service\BunnyCdnService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles the async retry of a Bunny CDN purge that was rate-limited (429)
 * the first time around. Only ever runs when a `messenger:consume doctrine`
 * worker picks the message up — see the "asyncRetryEnabled" Extension
 * Configuration switch that gates whether such a message gets dispatched in
 * the first place.
 */
final class RetryPurgeMessageHandler
{
    public function __construct(private readonly BunnyCdnService $cdnService) {}

    #[AsMessageHandler]
    public function retryPurgeTag(RetryPurgeTagMessage $message): void
    {
        $this->cdnService->retryPurgeTag($message->pullZoneId, $message->tag);
    }

    #[AsMessageHandler]
    public function retryPurgeUrl(RetryPurgeUrlMessage $message): void
    {
        $this->cdnService->retryPurgeUrl($message->url);
    }
}
