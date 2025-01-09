<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Signals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Grpc\GrpcTransportFactory;
use OpenTelemetry\Contrib\Otlp\OtlpUtil;
use OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SemConv\ResourceAttributes;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo;
use Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfoFactory;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\TraceSampleResult;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Attributes;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanConverter;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanExporter;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProvider;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Spryker\Service\Opentelemetry\Plugin\OpentelemetryMonitoringExtensionPlugin;
use Spryker\Service\Opentelemetry\Storage\CustomEventsStorage;
use Spryker\Service\Opentelemetry\Storage\CustomParameterStorage;
use Spryker\Service\Opentelemetry\Storage\ExceptionStorage;
use Spryker\Service\Opentelemetry\Storage\ResourceNameStorage;
use Spryker\Service\Opentelemetry\Storage\RootSpanNameStorage;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Spryker\Zed\Opentelemetry\Business\Generator\HookGenerator;
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
    public const ATTRIBUTE_IS_DETAILED_TRACE = 'detailed';

    /**
     * @var string
     */
    public const ATTRIBUTE_HTTP_METHOD = 'method';

    /**
     * @var string
     */
    protected const SPAN_NAME_PLACEHOLDER = '%s %s';

    /**
     * @var string
     */
    protected const INSTRUMENTATION_VERSION = '1.0';

    /**
     * @var string
     */
    protected const ZED_BIN_FILE_NAME = 'console';

    /**
     * @var string
     */
    protected const ZED_CLI_APPLICATION_NAME = 'CLI ZED';

    /**
     * @var \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo|null
     */
    protected static ?ResourceInfo $resourceInfo = null;

    /**
     * @var \OpenTelemetry\API\Trace\SpanInterface|null
     */
    protected static ?SpanInterface $rootSpan = null;

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

        static::registerRootSpan($request);

        ShutdownHandler::register($tracerProvider->shutdown(...));
        ShutdownHandler::register([static::class, 'shutdownHandler']);

        static::registerAdditionalHooks();
    }

    /**
     * @return void
     */
    protected static function registerAdditionalHooks(): void
    {
        if (TraceSampleResult::shouldSkipTraceBody()) {
            putenv('OTEL_PHP_DISABLED_INSTRUMENTATIONS=all');

            return;
        }

        $classmapFileName = APPLICATION_ROOT_DIR . '/src/Generated/OpenTelemetry/Hooks/classmap.php';
        if (!file_exists($classmapFileName)) {
            return;
        }

        require_once $classmapFileName;

        $autoload = function (string $class) use ($classmap)
        {
            if (!isset($classmap[$class])) {
                return;
            }
            include_once $classmap[$class];
        };

        spl_autoload_register($autoload, true, true);
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
        return new CriticalSpanRatioSampler(
            OpentelemetryInstrumentationConfig::getSamplerProbability(),
            OpentelemetryInstrumentationConfig::getSamplerProbabilityForCriticalSpans(),
            OpentelemetryInstrumentationConfig::getSamplerProbabilityForNonCriticalSpans(),
        );
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

            if ($application === static::ZED_BIN_FILE_NAME) {
                return static::ZED_CLI_APPLICATION_NAME;
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
     * @param Request $request
     *
     * @return void
     */
    protected static function registerRootSpan(Request $request): void
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
            ->spanBuilder($name)
            ->setParent($parent)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute(TraceAttributes::URL_QUERY, $request->getQueryString())
            ->setAttribute(static::ATTRIBUTE_IS_DETAILED_TRACE, !TraceSampleResult::shouldSkipTraceBody())
            ->setAttribute(static::ATTRIBUTE_HTTP_METHOD, $request->getMethod())
            ->startSpan();

        Context::storage()->attach($span->storeInContext($parent));

        static::$rootSpan = $span;
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
        $customParamsStorage = CustomParameterStorage::getInstance();
        if ($resourceName) {
            if ($customParamsStorage->getAttribute(OpentelemetryMonitoringExtensionPlugin::ATTRIBUTE_IS_CONSOLE_COMMAND)) {
                $resourceName = sprintf('CLI %s', $resourceName);
            }
            static::$resourceInfo->setServiceName($resourceName);
        }
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }

        $scope->detach();
        $span = static::$rootSpan;
        $name = RootSpanNameStorage::getInstance()->getName();
        if ($name) {
            $span->updateName($name);
        }

        $exceptions = ExceptionStorage::getInstance()->getExceptions();
        foreach ($exceptions as $exception) {
            $span->recordException($exception);
        }

        $events = CustomEventsStorage::getInstance()->getEvents();
        foreach ($events as $eventName => $eventAttributes) {
            $span->addEvent($eventName, $eventAttributes);
        }

        $span->setStatus($exceptions ? StatusCode::STATUS_ERROR : StatusCode::STATUS_OK);

        $span->setAttributes($customParamsStorage->getAttributes());
        $span->end();
    }
}
