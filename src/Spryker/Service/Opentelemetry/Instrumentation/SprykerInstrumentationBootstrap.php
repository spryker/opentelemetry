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
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
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
        $resource = ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAMESPACE => OpentelemetryConfig::getServiceNamespace(),
            ResourceAttributes::SERVICE_VERSION => static::INSTRUMENTATION_VERSION,
            ResourceAttributes::SERVICE_NAME => static::resolveServiceName(),
        ])));

        $meterProvider = (new MeterProviderFactory())->create($resource);

        Sdk::builder()
            ->setTracerProvider(static::createTracerProvider($resource))
            ->setMeterProvider($meterProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }

    /**
     * @param $resource
     *
     * @return \OpenTelemetry\SDK\Trace\TracerProviderInterface
     */
    protected static function createTracerProvider($resource): TracerProviderInterface
    {
        $spanExporter = new SpanExporter(
            (new GrpcTransportFactory())->create(OpentelemetryConfig::getExporterEndpoint() . OtlpUtil::method(Signals::TRACE))
        );

        return TracerProvider::builder()
            ->addSpanProcessor(
                new PostFilterBatchSpanProcessor(
                    $spanExporter,
                    Clock::getDefault(),
                ),
            )
            ->setResource($resource)
            ->setSampler(new ParentBased(new TraceIdRatioBasedSampler(OpentelemetryConfig::getSamplerProbability())))
            ->build();
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

            return 'CLI ' . $application;
        }

        $mapping = OpentelemetryConfig::getServiceNameMapping();

        foreach ($mapping as $pattern => $serviceName) {
            if (preg_match($pattern, $request->getHost())) {
                return $serviceName;
            }
        }

        return 'Undefined service';
    }
}
