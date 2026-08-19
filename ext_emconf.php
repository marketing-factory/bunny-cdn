<?php

declare(strict_types=1);

$EM_CONF['bunny_cdn'] = [
    'title' => 'Bunny CDN',
    'description' => 'Active Bunny CDN cache invalidation for TYPO3',
    'category' => 'services',
    'author' => 'Christian Spoo',
    'author_email' => 'christian.spoo@marketing-factory.de',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.3.99',
            'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
