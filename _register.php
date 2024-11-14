<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;

if (!class_exists(Sdk::class)) {
    return;
}

if (Sdk::isDisabled()) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    return;
}

SprykerInstrumentationBootstrap::register();
