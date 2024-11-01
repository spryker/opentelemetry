<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Signals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Metrics\MeterProviderFactory;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanTraceIdRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProvider;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;

/**
 * @method \Spryker\Service\Opentelemetry\OpentelemetryServiceFactory getFactory()
 */
class SprykerInstrumentationBootstrap
{
    /**
     * @var string
     */
    public const NAME = 'spryker';

    /**
     * @var string
     */
    protected const INSTRUMENTATION_VERSION = '0.1';

    /**
     * @return void
     */
    public static function register(): void
    {
        $resource = static::createResource();

        Sdk::builder()
            ->setTracerProvider(static::createTracerProvider($resource))
            ->setMeterProvider(static::createMeterProvider($resource))
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }

    /**
     * @return \OpenTelemetry\SDK\Resource\ResourceInfo
     */
    protected static function createResource(): ResourceInfo
    {
        return ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAMESPACE => OpentelemetryConfig::getServiceNamespace(),
            ResourceAttributes::SERVICE_VERSION => static::INSTRUMENTATION_VERSION,
            ResourceAttributes::SERVICE_NAME => static::resolveServiceName(),
        ])));
    }

    /**
     * @param \OpenTelemetry\SDK\Resource\ResourceInfo $resource
     *
     * @return \OpenTelemetry\SDK\Metrics\MeterProviderInterface
     */
    protected static function createMeterProvider(ResourceInfo $resource): MeterProviderInterface
    {
        return (new MeterProviderFactory())->create($resource);
    }

    /**
     * @param \OpenTelemetry\SDK\Resource\ResourceInfo $resource
     *
     * @return \OpenTelemetry\SDK\Trace\TracerProviderInterface
     */
    protected static function createTracerProvider(ResourceInfo $resource): TracerProviderInterface
    {
        return TracerProvider::builder()
            ->addSpanProcessor(static::createSpanProcessor())
            ->setResource($resource)
            ->setSampler(static::createSampler())
            ->build();
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\SpanExporterInterface
     */
    protected static function createSpanExporter(): SpanExporterInterface
    {
        return new SpanExporter(
            (new GrpcTransportFactory())->create(OpentelemetryConfig::getExporterEndpoint() . OtlpUtil::method(Signals::TRACE)),
        );
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\SpanProcessorInterface
     */
    protected static function createSpanProcessor(): SpanProcessorInterface
    {
        return new PostFilterBatchSpanProcessor(
            static::createSpanExporter(),
            Clock::getDefault(),
        );
    }

    /**
     * @return \OpenTelemetry\API\Trace\SamplerInterface
     */
    protected static function createSampler(): SamplerInterface
    {
        return new CriticalSpanTraceIdRatioSampler(OpentelemetryConfig::getSamplerProbability());
    }

    /**
     * @return string
     */
    protected static function resolveServiceName(): string
    {
        $request = (new RequestProcessor())->getRequest();

        $cli = $request->server->get('argv');
        if ($cli) {
            [$vendor, $bin, $application] = explode('/', $cli[0]);

            if ($application === 'console') {
                return 'CLI ZED';
            }

            return sprintf('CLI %s', strtoupper($application));
        }

        $mapping = OpentelemetryConfig::getServiceNameMapping();

        foreach ($mapping as $pattern => $serviceName) {
            if (preg_match($pattern, $request->getHost())) {
                return $serviceName;
            }
        }

        return OpentelemetryConfig::getDefaultServiceName();
    }
}
