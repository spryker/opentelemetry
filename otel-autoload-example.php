<?php
/**
 * This file should be copied to the root of Spryker application and enabled in `autoload.files` section of composer.json file.
 */

$autoload = function (string $class)
{
    $convertedClassName = str_replace('\\', '-', $class);
    $hookFilePath = __DIR__.'/src/Generated/OpenTelemetry/Hooks/' . $convertedClassName . 'Hook.php';

    if (getenv('OTEL_SDK_DISABLED') !== true && file_exists($hookFilePath)) {
        include_once $hookFilePath;
    }
};

spl_autoload_register($autoload, true, true);
