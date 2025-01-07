<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use Spryker\Service\Opentelemetry\Instrumentation\ElasticaInstrumentation;
use Spryker\Service\Opentelemetry\Instrumentation\PropelInstrumentation;
use Spryker\Service\Opentelemetry\Instrumentation\RabbitMqInstrumentation;
use Spryker\Service\Opentelemetry\Instrumentation\RedisInstrumentation;
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
ElasticaInstrumentation::register();
PropelInstrumentation::register();
RabbitMqInstrumentation::register();
RedisInstrumentation::register();
