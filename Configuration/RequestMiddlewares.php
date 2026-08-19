<?php

declare(strict_types=1);

use Mfd\BunnyCdn\Middleware\CdnTagHeader;

return [
    'frontend' => [
        'mfd/bunny-cdn/cdn-tag-header' => [
            'target' => CdnTagHeader::class,
            'after' => [
                'typo3/cms-core/cache-tags-attribute',
            ],
        ],
    ],
];
