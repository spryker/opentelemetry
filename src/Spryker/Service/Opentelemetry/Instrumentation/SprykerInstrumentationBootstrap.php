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
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
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
use Spryker\Glue\GlueApplication\Rest\ResourceRouter;
use Spryker\Service\Opentelemetry\Instrumentation\Propagation\SymfonyRequestPropagationGetter;
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
use Spryker\Shared\Config\Config;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\OpentelemetryConstants;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Symfony\Component\Console\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Router;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

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
    public const INSTRUMENTATION_VERSION = '1.11.0';

    /**
     * @var string
     */
    protected const SPAN_NAME_PLACEHOLDER = '%s %s';

    /**
     * @var string
     */
    protected const ZED_BIN_FILE_NAME = 'console';

    /**
     * @var string
     */
    protected const ZED_CLI_APPLICATION_NAME = 'CLI ZED';

    /**
     * @var string
     */
    protected const ROOT_ATTRIBUTE = 'spr.root';

    /**
     * @var string
     */
    protected const ATTRIBUTE_PROCESSED_SPANS = 'spr.processed_spans';

    /**
     * @var string
     */
    protected const FINAL_HTTP_ROOT_SPAN_NAME = '/*';

    /**
     * @var \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo|null
     */
    protected static ?ResourceInfo $resourceInfo = null;

    /**
     * @var \OpenTelemetry\API\Trace\SpanInterface|null
     */
    protected static ?SpanInterface $rootSpan = null;

    /**
     * @var int|null
     */
    protected static ?int $cliReturnCode = null;

    /**
     * @var string|null
     */
    protected static ?string $route = null;

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
        static::registerConsoleReturnCodeHook();
        static::registerRouteMatcherHooks();
        if (TraceSampleResult::shouldSkipTraceBody()) {
            putenv('OTEL_PHP_DISABLED_INSTRUMENTATIONS=all');

            return;
        }

        $classmapFileName = APPLICATION_ROOT_DIR . '/src/Generated/OpenTelemetry/Hooks/classmap.php';
        if (!file_exists($classmapFileName)) {
            return;
        }

        $classmap = [];

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
     * @return void
     */
    protected static function registerConsoleReturnCodeHook(): void
    {
        hook(
            Application::class,
            'doRun',
            function () {},
            function ($instance, array $params, $returnValue, ?Throwable $exception) {
                static::$cliReturnCode = $returnValue;
            }
        );
    }

    /**
     * @return void
     */
    protected static function registerRouteMatcherHooks(): void
    {
        //Backoffice and Yves
        hook(
            UrlMatcher::class,
            'matchRequest',
            function () {},
            function ($instance, array $params, $returnValue, ?Throwable $exception) {
                static::$route = $returnValue['_route'] ?? null;
            }
        );

        if (class_exists(ResourceRouter::class)) {
            //REST API
            hook(
                ResourceRouter::class,
                'matchRequest',
                function () {},
                function ($instance, array $params, $returnValue, ?Throwable $exception) {
                    static::$route = $returnValue['_route'] ?? null;
                }
            );
        }

        //Storefront/Backend API
        hook(
            Router::class,
            'match',
            function () {},
            function ($instance, array $params, $returnValue, ?Throwable $exception) {
                static::$route = $returnValue['_route'] ?? null;
            }
        );

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
            $cliLine = explode('/', $cli[0]);
            $application = end($cliLine);

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
        $name = static::formatGeneralSpanName($request, false);

        $instrumentation = CachedInstrumentation::getCachedInstrumentation();
        $parent = Context::getCurrent();
        if (OpentelemetryInstrumentationConfig::getIsDistributedTracingEnabled()) {
            $parent = TraceContextPropagator::getInstance()->extract($request, static::createRequestPropagatorGetter());
        }
        $spanBuilder = $instrumentation->tracer()
            ->spanBuilder($name)
            ->setParent($parent)
            ->setSpanKind($cli ? SpanKind::KIND_INTERNAL : SpanKind::KIND_SERVER)
            ->setAttribute(CriticalSpanRatioSampler::IS_SYSTEM_ATTRIBUTE, true)
            ->setAttribute(static::ROOT_ATTRIBUTE, true)
            ->setAttribute(static::ATTRIBUTE_IS_DETAILED_TRACE, !TraceSampleResult::shouldSkipTraceBody());

            $span = $spanBuilder->startSpan();

        Context::storage()->attach($span->storeInContext($parent));

        static::$rootSpan = $span;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    protected static function addHttpAttributes(SpanInterface $span, Request $request): SpanInterface
    {
        $span->setAttribute(TraceAttributes::URL_FULL, $request->getUri())
            ->setAttribute(TraceAttributes::URL_PATH, $request->getPathInfo())
            ->setAttribute(TraceAttributes::URL_SCHEME, $request->getScheme())
            ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
            ->setAttribute(TraceAttributes::SERVER_PORT, $request->getPort())
            ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getHost())
            ->setAttribute(TraceAttributes::HTTP_ROUTE, $request->attributes->get('_route'))
            ->setAttribute(TraceAttributes::URL_QUERY, $request->getQueryString())
            ->setAttribute(TraceAttributes::CLIENT_ADDRESS, $request->getClientIp())
            ->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, http_response_code());

        return $span;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    protected static function addCliAttributes(SpanInterface $span, Request $request): SpanInterface
    {
        $span->setAttribute(TraceAttributes::PROCESS_EXIT_CODE, static::$cliReturnCode);

        return $span;
    }

    /**
     * @return \OpenTelemetry\Context\Propagation\PropagationGetterInterface
     */
    protected static function createRequestPropagatorGetter(): PropagationGetterInterface
    {
        return new SymfonyRequestPropagationGetter();
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param bool $isApplicationAvailable
     *
     * @return string
     */
    protected static function formatSpanName(Request $request, bool $isApplicationAvailable = false): string
    {
        $target = static::$route ?: ($isApplicationAvailable ? Config::get(OpentelemetryConstants::FALLBACK_HTTP_ROOT_SPAN_NAME, static::FINAL_HTTP_ROOT_SPAN_NAME) : static::FINAL_HTTP_ROOT_SPAN_NAME);

        return trim(sprintf(static::SPAN_NAME_PLACEHOLDER, $isApplicationAvailable && !Config::get(OpentelemetryConstants::SHOW_HTTP_METHOD_IN_ROOT_SPAN_NAME, true) ? '' : $request->getMethod(), $target));
    }

    /**
     * @return void
     */
    public static function shutdownHandler(): void
    {
        $request = RequestProcessor::getRequest();
        $resourceName = ResourceNameStorage::getInstance()->getName();
        $customParamsStorage = CustomParameterStorage::getInstance();
        $cli = $customParamsStorage->getAttribute(OpentelemetryMonitoringExtensionPlugin::ATTRIBUTE_IS_CONSOLE_COMMAND);
        if ($resourceName) {
            if ($cli) {
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

        if ($cli || $request->server->get('argv')) {
            $span = static::addCliAttributes($span, $request);
        } else {
            $span = static::addHttpAttributes($span, $request);
        }

        $name = RootSpanNameStorage::getInstance()->getName();
        $span->updateName($name ?: static::formatGeneralSpanName($request, true));

        $exceptions = ExceptionStorage::getInstance()->getExceptions();
        foreach ($exceptions as $exception) {
            $span->recordException($exception);
        }

        $events = CustomEventsStorage::getInstance()->getEvents();
        foreach ($events as $eventName => $eventAttributes) {
            $span->addEvent($eventName, $eventAttributes);
        }

        $processedSpans = PostFilterBatchSpanProcessor::getNumberOfProcessedSpans();
        $span->setAttribute(static::ATTRIBUTE_PROCESSED_SPANS, $processedSpans);
        $span->setAttribute(static::ATTRIBUTE_IS_DETAILED_TRACE, $processedSpans >= 1);

        $span->setStatus(static::getSpanStatus());

        $span->setAttributes($customParamsStorage->getAttributes());
        $span->end();
    }

    /**
     * @return string
     */
    protected static function getSpanStatus(): string
    {
        $exceptions = ExceptionStorage::getInstance()->getExceptions();

        if ($exceptions !== []) {
            return StatusCode::STATUS_ERROR;
        }

        if (static::$cliReturnCode !== null && static::$cliReturnCode !== 0) {
            return StatusCode::STATUS_ERROR;
        }

        $httpResponseCode = http_response_code();
        if (is_int($httpResponseCode) && $httpResponseCode >= 500) {
            return StatusCode::STATUS_ERROR;
        }

        return StatusCode::STATUS_UNSET;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param bool $isApplicationAvailable
     *
     * @return string
     */
    protected static function formatGeneralSpanName(Request $request, bool $isApplicationAvailable = false): string
    {
        $cli = $request->server->get('argv');
        if ($cli) {
            return implode(' ', $cli);
        }

        return static::formatSpanName($request, $isApplicationAvailable);
    }
}
