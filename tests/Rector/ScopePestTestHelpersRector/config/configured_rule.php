<?php

use CanyonGBS\Common\Rector\ScopePestTestHelpersRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRules([
        ScopePestTestHelpersRector::class,
    ]);
