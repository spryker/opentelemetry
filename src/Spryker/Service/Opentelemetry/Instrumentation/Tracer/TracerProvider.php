<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Tracer;

use OpenTelemetry\SDK\Trace\IdGeneratorInterface;
use OpenTelemetry\SDK\Trace\RandomIdGenerator;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanLimits;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\TracerSharedState;
use OpenTelemetry\API\Trace as API;
use OpenTelemetry\API\Trace\NoopTracer;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactoryInterface;
use OpenTelemetry\SDK\Common\InstrumentationScope\Configurator;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanLimitBuilder;
use WeakMap;

class TracerProvider implements TracerProviderInterface
{
    /**
     * @var \OpenTelemetry\SDK\Trace\TracerSharedState
     */
    protected TracerSharedState $tracerSharedState;

    /**
     * @var \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactoryInterface|\OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory
     */
    protected InstrumentationScopeFactoryInterface $instrumentationScopeFactory;

    /**
     * @var \WeakMap
     */
    protected WeakMap $tracers;

    /**
     * @param \OpenTelemetry\SDK\Trace\SpanProcessorInterface $spanProcessor
     * @param \OpenTelemetry\SDK\Trace\SamplerInterface $sampler
     * @param \OpenTelemetry\SDK\Resource\ResourceInfo $resource
     * @param \OpenTelemetry\SDK\Trace\SpanLimits|null $spanLimits
     * @param \OpenTelemetry\SDK\Trace\IdGeneratorInterface|null $idGenerator
     * @param \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactoryInterface|null $instrumentationScopeFactory
     * @param \OpenTelemetry\SDK\Common\InstrumentationScope\Configurator|null $configurator
     */
    public function __construct(
        SpanProcessorInterface $spanProcessor,
        SamplerInterface $sampler,
        ResourceInfo $resource,
        ?SpanLimits $spanLimits = null,
        ?IdGeneratorInterface $idGenerator = null,
        ?InstrumentationScopeFactoryInterface $instrumentationScopeFactory = null,
        protected ?Configurator $configurator = null,
    ) {
        $idGenerator ??= new RandomIdGenerator();
        $spanLimits ??= (new SpanLimitBuilder())->build();

        $this->tracerSharedState = new TracerSharedState(
            $idGenerator,
            $resource,
            $spanLimits,
            $sampler,
            [$spanProcessor],
        );
        $this->instrumentationScopeFactory = $instrumentationScopeFactory ?? new InstrumentationScopeFactory(Attributes::factory());
        $this->tracers = new WeakMap();
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
     *
     * @return bool
     */
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->tracerSharedState->getSpanProcessor()->forceFlush($cancellation);
    }

    /**
     * @param string $name
     * @param string|null $version
     * @param string|null $schemaUrl
     * @param iterable $attributes
     *
     * @return \OpenTelemetry\API\Trace\TracerInterface
     */
    public function getTracer(
        string $name,
        ?string $version = null,
        ?string $schemaUrl = null,
        iterable $attributes = [],
    ): API\TracerInterface {
        if ($this->tracerSharedState->hasShutdown()) {
            return NoopTracer::getInstance();
        }

        $scope = $this->instrumentationScopeFactory->create($name, $version, $schemaUrl, $attributes);
        $tracer = new Tracer(
            $this->tracerSharedState,
            $scope,
            $this->configurator,
        );
        $this->tracers->offsetSet($tracer, null);

        return $tracer;
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\SamplerInterface
     */
    public function getSampler(): SamplerInterface
    {
        return $this->tracerSharedState->getSampler();
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
     *
     * @return bool
     */
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if ($this->tracerSharedState->hasShutdown()) {
            return true;
        }

        return $this->tracerSharedState->shutdown($cancellation);
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProviderBuilder
     */
    public static function builder(): TracerProviderBuilder
    {
        return new TracerProviderBuilder();
    }

    /**
     * @param \OpenTelemetry\SDK\Common\InstrumentationScope\Configurator $configurator
     *
     * @return void
     */
    public function updateConfigurator(Configurator $configurator): void
    {
        $this->configurator = $configurator;

        foreach ($this->tracers as $tracer => $unused) {
            $tracer->updateConfig($configurator);
        }
    }
}
