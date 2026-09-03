<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Context;

use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;

/**
 * Whether Bunny CDN is active for the current site (config), and whether the
 * current request actually arrived through Bunny's edge (observed, from the
 * `CDN-*` headers Bunny adds to every request it proxies to origin).
 *
 * @see https://support.bunny.net/hc/en-us/articles/115003578911
 */
final class BunnyCdnAspect implements AspectInterface
{
    public function __construct(
        private readonly bool $active,
        private readonly bool $viaBunny,
        private readonly ?string $serverId,
        private readonly ?string $requestId,
        private readonly ?string $requestCountryCode,
        private readonly ?string $requestStateCode,
        private readonly ?bool $mobileDevice,
        private readonly ?string $connectionId,
        private readonly ?string $host,
        private readonly ?string $serverZone,
    ) {}

    public function get(string $name): bool|string|null
    {
        return match ($name) {
            'active' => $this->active,
            'viaBunny' => $this->viaBunny,
            'serverId' => $this->serverId,
            'requestId' => $this->requestId,
            'requestCountryCode' => $this->requestCountryCode,
            'requestStateCode' => $this->requestStateCode,
            'mobileDevice' => $this->mobileDevice,
            'connectionId' => $this->connectionId,
            'host' => $this->host,
            'serverZone' => $this->serverZone,
            default => throw new AspectPropertyNotFoundException(
                sprintf('Property "%s" not found in aspect "bunnyCdn".', $name),
                1755792000,
            ),
        };
    }
}
