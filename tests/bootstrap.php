<?php

declare(strict_types=1);

// Load Composer autoloader — works both standalone and within xoops_lib/vendor/
$autoloadPaths = [
    dirname(__DIR__) . '/vendor/autoload.php',       // standalone package
    dirname(__DIR__, 3) . '/autoload.php',            // inside xoops_lib/vendor/
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Load test stubs
require_once __DIR__ . '/stubs/TemplateStub.php';
require_once __DIR__ . '/stubs/SmartyExtensionBase.php';
require_once __DIR__ . '/stubs/XoopsStubs.php';
require_once __DIR__ . '/stubs/XmfI18nStubs.php';
