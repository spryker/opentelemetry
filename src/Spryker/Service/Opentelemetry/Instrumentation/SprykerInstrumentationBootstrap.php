<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Signals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Metrics\MeterProviderFactory;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanTraceIdRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanConverter;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanExporter;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProvider;
use Spryker\Service\Opentelemetry\Storage\CustomParameterStorage;
use Spryker\Service\Opentelemetry\Storage\RootSpanNameStorage;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;
use Symfony\Component\HttpFoundation\Request;

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
    protected const SPAN_NAME_PLACEHOLDER = '%s %s';

    /**
     * @var string
     */
    protected const INSTRUMENTATION_VERSION = '0.1';

    /**
     * @var \OpenTelemetry\API\Trace\SpanInterface|null
     */
    protected static ?SpanInterface $rootSpan = null;

    /**
     * @return void
     */
    public static function register(): void
    {
        $serviceName = static::resolveServiceName();
        $resource = static::createResource($serviceName);

        $tracerProvider = static::createTracerProvider($resource);
        //$meterProvider = static::createMeterProvider($resource);

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            //->setMeterProvider($meterProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->buildAndRegisterGlobal();

        static::registerRootSpan($serviceName);

        ShutdownHandler::register($tracerProvider->shutdown(...));
        //ShutdownHandler::register($meterProvider->shutdown(...));
    }

    /**
     * @param string $serviceName
     *
     * @return \OpenTelemetry\SDK\Resource\ResourceInfo
     */
    protected static function createResource(string $serviceName): ResourceInfo
    {
        return ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAMESPACE => OpentelemetryConfig::getServiceNamespace(),
            ResourceAttributes::SERVICE_VERSION => static::INSTRUMENTATION_VERSION,
            ResourceAttributes::SERVICE_NAME => $serviceName,
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
            new SpanConverter(),
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
     * @return \OpenTelemetry\SDK\Trace\SamplerInterface
     */
    protected static function createSampler(): SamplerInterface
    {
        return new ParentBased(new CriticalSpanTraceIdRatioSampler(OpentelemetryConfig::getSamplerProbability()));
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

    /**
     * @param string $servicedName
     *
     * @return void
     */
    protected static function registerRootSpan(string $servicedName): void
    {
        $request = (new RequestProcessor())->getRequest();

        $cli = $request->server->get('argv');
        if ($cli) {
            $name = implode(' ', $cli);
        } else {
            $name = static::formatSpanName($request);
        }

        $instrumentation = CachedInstrumentation::getCachedInstrumentation();
        $parent = Context::getCurrent();
        $span = $instrumentation->tracer()
            ->spanBuilder($servicedName . ' ' . $name)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute(TraceAttributes::URL_QUERY, $request->getQueryString())
            //->setAttribute(CriticalSpanTraceIdRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
            ->startSpan();

        Context::storage()->attach($span->storeInContext($parent));

        register_shutdown_function([static::class, 'shutdownHandler']);
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return string
     */
    protected static function formatSpanName(Request $request): string
    {
        $relativeUriWithoutQueryString = str_replace('?' . $request->getQueryString(), '', $request->getUri());

        return sprintf(static::SPAN_NAME_PLACEHOLDER, $request->getMethod(), $relativeUriWithoutQueryString);
    }

    /**
     * @return void
     */
    public static function shutdownHandler(): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        $name = RootSpanNameStorage::getInstance()->getName();
        if ($name) {
            $span->updateName($name);
        }
        $customParamsStorage = CustomParameterStorage::getInstance();
        $span->setAttributes($customParamsStorage->getAttributes());
        $span->end();
    }
}
