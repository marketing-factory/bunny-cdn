<?php

declare(strict_types=1);

$GLOBALS['SiteConfiguration']['site']['columns']['bunny_pull_zone_id'] = [
    'label' => 'Bunny CDN Pull Zone ID',
    'description' => 'ID of the Bunny.net Pull Zone serving this site, used for active cache invalidation',
    'config' => [
        'type' => 'number',
        'format' => 'integer',
    ],
];

$GLOBALS['SiteConfiguration']['site']['types']['0']['showitem'] .= '
    ,--div--;Bunny CDN, bunny_pull_zone_id
';
