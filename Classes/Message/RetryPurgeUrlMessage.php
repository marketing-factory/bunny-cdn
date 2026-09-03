<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Message;

/**
 * A URL purge that got a 429 (rate limited) from Bunny, to retry
 * asynchronously. Carries no secret — the handler re-reads the API key from
 * Extension Configuration itself.
 */
final readonly class RetryPurgeUrlMessage
{
    public function __construct(
        public string $url,
    ) {}
}
