<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(SprykerInstrumentationBootstrap::NAME) === true) {
    return;
}

SprykerInstrumentationBootstrap::register();


