<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$application = new Tebru\Dilbot\DilbotApplication('Dilbot', '@package_version@');
$code = $application->run();
