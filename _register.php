<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(SprykerInstrumentationBootstrap::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry Spryker Framework auto-instrumentation', E_USER_WARNING);

    return;
}

SprykerInstrumentationBootstrap::register();

$autoload = function (string $class): void {
    $convertedClassName = str_replace('\\', '-', $class);
    $hookFilePath = sprintf(__DIR__ . '/src/Generated/OpenTelemetry/Hooks/%s/Hook.php', $convertedClassName);

    if (getenv('OTEL_SDK_DISABLED') !== true && file_exists($hookFilePath)) {
        include_once $hookFilePath;
    }
};

spl_autoload_register($autoload, true, true);
