<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Tracer;

use OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class TracerProviderBuilder
{
    /**
     * @var \OpenTelemetry\SDK\Trace\SpanProcessorInterface|null
     */
    protected ?SpanProcessorInterface $spanProcessor = null;

    /**
     * @var \OpenTelemetry\SDK\Resource\ResourceInfo|null
     */
    protected ?ResourceInfo $resource = null;

    /**
     * @var \OpenTelemetry\SDK\Trace\SamplerInterface|null
     */
    protected ?SamplerInterface $sampler = null;

    /**
     * @var \OpenTelemetry\SDK\Common\InstrumentationScope\Configurator|null
     */
    protected ?Configurator $configurator = null;

    /**
     * @param \OpenTelemetry\SDK\Trace\SpanProcessorInterface $spanProcessor
     *
     * @return $this
     */
    public function addSpanProcessor(SpanProcessorInterface $spanProcessor)
    {
        $this->spanProcessor = $spanProcessor;

        return $this;
    }

    /**
     * @param \OpenTelemetry\SDK\Resource\ResourceInfo $resource
     *
     * @return $this
     */
    public function setResource(ResourceInfo $resource)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * @param \OpenTelemetry\SDK\Trace\SamplerInterface $sampler
     *
     * @return $this
     */
    public function setSampler(SamplerInterface $sampler)
    {
        $this->sampler = $sampler;

        return $this;
    }

    /**
     * @param \OpenTelemetry\SDK\Common\InstrumentationScope\Configurator $configurator
     *
     * @return $this
     */
    public function setConfigurator(Configurator $configurator)
    {
        $this->configurator = $configurator;

        return $this;
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\TracerProviderInterface
     */
    public function build(): TracerProviderInterface
    {
        return new TracerProvider(
            $this->spanProcessor,
            $this->sampler,
            $this->resource,
            configurator: $this->configurator ?? Configurator::tracer(),
        );
    }
}
