<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Signals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo;
use Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfoFactory;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanTraceIdRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\TraceSampleResult;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Attributes;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanConverter;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanExporter;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProvider;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Spryker\Service\Opentelemetry\Storage\CustomParameterStorage;
use Spryker\Service\Opentelemetry\Storage\ResourceNameStorage;
use Spryker\Service\Opentelemetry\Storage\RootSpanNameStorage;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
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
     * @var \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo|null
     */
    protected static ?ResourceInfo $resourceInfo = null;

    /**
     * @return void
     */
    public static function register(): void
    {
        $request = RequestProcessor::getRequest();

        TraceSampleResult::shouldSample($request);

        if (TraceSampleResult::shouldSkipRootSpan()) {
            return;
        }

        $serviceName = static::resolveServiceName();
        $resource = static::createResource($serviceName);

        $tracerProvider = static::createTracerProvider($resource);

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->buildAndRegisterGlobal();

        static::registerRootSpan($serviceName, $request);

        ShutdownHandler::register($tracerProvider->shutdown(...));
    }

    /**
     * @param string $serviceName
     *
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo
     */
    protected static function createResource(string $serviceName): ResourceInfo
    {
        static::$resourceInfo = ResourceInfoFactory::defaultResource()->mergeSprykerResource(ResourceInfo::createSprykerResource(Attributes::create([
            ResourceAttributes::SERVICE_NAMESPACE => OpentelemetryInstrumentationConfig::getServiceNamespace(),
            ResourceAttributes::SERVICE_VERSION => static::INSTRUMENTATION_VERSION,
            ResourceAttributes::SERVICE_NAME => $serviceName,
        ])));

        return static::$resourceInfo;
    }

    /**
     * @param \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo $resource
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
            (new GrpcTransportFactory())->create(OpentelemetryInstrumentationConfig::getExporterEndpoint() . OtlpUtil::method(Signals::TRACE)),
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
            OpentelemetryInstrumentationConfig::getSpanProcessorMaxQueueSize(),
            OpentelemetryInstrumentationConfig::getSpanProcessorScheduleDelay(),
            OpentelemetryInstrumentationConfig::getSpanProcessorMaxBatchSize(),
        );
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\SamplerInterface
     */
    protected static function createSampler(): SamplerInterface
    {
        return new CriticalSpanTraceIdRatioSampler(OpentelemetryInstrumentationConfig::getSamplerProbability(), OpentelemetryInstrumentationConfig::getSamplerProbabilityForCriticalSpans());
        //return new ParentBased(new CriticalSpanTraceIdRatioSampler(OpentelemetryInstrumentationConfig::getSamplerProbability()));
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

        $mapping = OpentelemetryInstrumentationConfig::getServiceNameMapping();

        foreach ($mapping as $serviceName => $pattern) {
            if (preg_match($pattern, $request->getHost())) {
                return $serviceName;
            }
        }

        return OpentelemetryInstrumentationConfig::getDefaultServiceName();
    }

    /**
     * @param string $servicedName
     *
     * @return void
     */
    protected static function registerRootSpan(string $servicedName, Request $request): void
    {
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
            ->setAttribute('is_full_trace', !TraceSampleResult::shouldSkipTraceBody())
            ->setAttribute('method', $request->getMethod())
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
        $resourceName = ResourceNameStorage::getInstance()->getName();
        if ($resourceName) {
            static::$resourceInfo->setServiceName($resourceName);
        }
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
