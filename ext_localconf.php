<?php

declare(strict_types=1);

use Mfd\BunnyCdn\Cache\Backend\BunnyPurgingBackend;
use Mfd\BunnyCdn\Message\RetryPurgeTagMessage;
use Mfd\BunnyCdn\Message\RetryPurgeUrlMessage;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;

if (!defined('TYPO3')) {
    return;
}

call_user_func(static function (): void {
    $cacheConfig = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pages'];
    $innerBackend = $cacheConfig['backend'] ?? Typo3DatabaseBackend::class;
    $innerOptions = $cacheConfig['options'] ?? [];

    $cacheConfig['backend'] = BunnyPurgingBackend::class;
    $cacheConfig['options'] = [
        'innerBackend' => $innerBackend,
        'innerOptions' => $innerOptions,
    ];

    // Only actually dispatched when the "asyncRetryEnabled" Extension
    // Configuration switch is on (see BunnyCdnService::scheduleRetry()) —
    // registering the route here is harmless either way.
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][RetryPurgeTagMessage::class] = 'doctrine';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'][RetryPurgeUrlMessage::class] = 'doctrine';
});
