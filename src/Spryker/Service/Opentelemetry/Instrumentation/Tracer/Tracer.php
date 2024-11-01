<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Tracer;

use OpenTelemetry\API\Trace\NoopSpanBuilder;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Common\InstrumentationScope\Config;
use OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
use OpenTelemetry\SDK\Trace\TracerConfig;
use OpenTelemetry\SDK\Trace\TracerSharedState;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanBuilder;

class Tracer implements TracerInterface
{
    /**
     * @var string
     */
    protected const FALLBACK_SPAN_NAME = 'empty';

    /**
     * @var \OpenTelemetry\SDK\Common\InstrumentationScope\Config|\OpenTelemetry\SDK\Trace\TracerConfig
     */
    protected Config $config;

    /**
     * @var \OpenTelemetry\SDK\Trace\TracerSharedState
     */
    protected TracerSharedState $tracerSharedState;

    /**
     * @var \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface
     */
    protected InstrumentationScopeInterface $instrumentationScope;

    /**
     * @param \OpenTelemetry\SDK\Trace\TracerSharedState $tracerSharedState
     * @param \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface $instrumentationScope
     * @param \OpenTelemetry\SDK\Common\InstrumentationScope\Configurator|null $configurator
     */
    public function __construct(
        TracerSharedState $tracerSharedState,
        InstrumentationScopeInterface $instrumentationScope,
        ?Configurator $configurator = null,
    ) {
        $this->tracerSharedState = $tracerSharedState;
        $this->instrumentationScope = $instrumentationScope;
        $this->config = $configurator ? $configurator->resolve($this->instrumentationScope) : TracerConfig::default();
    }

    /**
     * @param string $spanName
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function spanBuilder(string $spanName): SpanBuilderInterface
    {
        if (ctype_space($spanName)) {
            $spanName = static::FALLBACK_SPAN_NAME;
        }
        // If a Tracer is disabled, it MUST behave equivalently to No-op Tracer
        if (!$this->config->isEnabled() || $this->tracerSharedState->hasShutdown()) {
            return new NoopSpanBuilder(Context::storage());
        }

        return new SpanBuilder(
            $spanName,
            $this->instrumentationScope,
            $this->tracerSharedState,
        );
    }

    /**
     * @return \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface
     */
    public function getInstrumentationScope(): InstrumentationScopeInterface
    {
        return $this->instrumentationScope;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * @param \OpenTelemetry\SDK\Common\InstrumentationScope\Configurator $configurator
     *
     * @return void
     */
    public function updateConfig(Configurator $configurator): void
    {
        $this->config = $configurator->resolve($this->instrumentationScope);
    }
}
