<?php

declare(strict_types=1);

use Mfd\BunnyCdn\Middleware\BunnyCdnAspectMiddleware;
use Mfd\BunnyCdn\Middleware\CdnTagHeader;

return [
    'frontend' => [
        'mfd/bunny-cdn/cdn-tag-header' => [
            'target' => CdnTagHeader::class,
            'after' => [
                'typo3/cms-core/cache-tags-attribute',
            ],
        ],
        'mfd/bunny-cdn/aspect' => [
            'target' => BunnyCdnAspectMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
