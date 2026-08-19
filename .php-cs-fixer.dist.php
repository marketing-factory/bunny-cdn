<?php

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__)
    ->exclude(['vendor', '.Build', 'public'])
    ->append([__FILE__, __DIR__ . '/rector.php'])
;

return $config;
