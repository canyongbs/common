<?php

use phpmock\phpunit\PHPMock;

PHPMock::defineFunctionMock('CanyonGBS\Common\Parser\Part', 'function_exists');

require dirname(__DIR__) . '/vendor/autoload.php';