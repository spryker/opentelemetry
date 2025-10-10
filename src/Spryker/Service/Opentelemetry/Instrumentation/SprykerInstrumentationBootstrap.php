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
use Spryker\Service\Kernel\ClassResolver\AbstractClassResolver;
use Spryker\Service\Kernel\ClassResolver\Factory\FactoryResolver;
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

class SprykerInstrumentationBootstrap
{
    /**
     * @var string
     */
    public const NAME = 'spryker';

    /**
     * @var string Attribute to mark a trace that is has at least one more span except the root one.
     */
    public const ATTRIBUTE_IS_DETAILED_TRACE = 'detailed';

    /**
     * @var string Version of the instrumentation. Must be equal to the release version. This version is shown in the traces.
     */
    public const INSTRUMENTATION_VERSION = '1.18.1';

    /**
     * @var string Default span name placeholder for root spans. By default the first placeholder is for HTTP method and the second one for the route.
     */
    protected const SPAN_NAME_PLACEHOLDER = '%s %s';

    /**
     * @var string File name of the ZED console binary.
     */
    protected const ZED_BIN_FILE_NAME = 'console';

    /**
     * @var string Default service name for CLI ZED application.
     */
    protected const ZED_CLI_APPLICATION_NAME = 'CLI ZED';

    /**
     * @var string Attribute to mark a span as a root span. Required as the span that is considered as root, can be a child span if the trace is collected from multiple services, e.g. Yves call to Zed.
     */
    protected const ROOT_ATTRIBUTE = 'spr.root';

    /**
     * @var string Attribute to store the number of spans in the trace.
     */
    protected const ATTRIBUTE_PROCESSED_SPANS = 'spr.processed_spans';

    /**
     * @var string The name of the root span if no other mechanism was executed during the request.
     */
    protected const FINAL_HTTP_ROOT_SPAN_NAME = '/*';

    /**
     * @var string Attribute to store the host of the request.
     */
    protected const HOST_ATTRIBUTE = 'host';

    /**
     * @var \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo|null
     */
    protected static ?ResourceInfo $resourceInfo = null;

    /**
     * @var \OpenTelemetry\API\Trace\SpanInterface|null In order to always have a reference to the root span, it is stored statically.
     */
    protected static ?SpanInterface $rootSpan = null;

    /**
     * @var int|null Will have a value only for CLI applications. It will be set to the return code of the console command.
     */
    protected static ?int $cliReturnCode = null;

    /**
     * @var string|null Will have a value only for HTTP requests. It will be set to the route name of the request.
     */
    protected static ?string $route = null;

    /**
     * @var null|\Spryker\Service\Opentelemetry\OpentelemetryServiceFactory
     */
    protected static $factory = null;

    /**
     * @return void
     */
    public static function register(): void
    {
        //Disabling error reporting for Otel. Will be overwritten by the application.
        error_reporting(0);

        //Get a request
        $request = RequestProcessor::getRequest();

        //Decide whether to sample the request or not
        TraceSampleResult::shouldSample($request);

        //Skip the instrumentation if the request should not be sampled. E.g. the the route is in the stop list.
        if (TraceSampleResult::shouldSkipRootSpan()) {
            return;
        }

        $serviceName = static::resolveServiceName();
        $resource = static::createResource($serviceName);

        $tracerProvider = static::createTracerProvider($resource);

        //Prepare a main SDK instance
        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->buildAndRegisterGlobal();

        static::registerRootSpan($request);

        // Add a shutdown handler to ensure that the root span is ended and additional attributes are set.
        ShutdownHandler::register($tracerProvider->shutdown(...));
        ShutdownHandler::register([static::class, 'shutdownHandler']);

        //Register the autogenerated and system hooks.
        static::registerAdditionalHooks();
    }

    /**
     * @return void
     */
    protected static function registerAdditionalHooks(): void
    {
        //Will catch return code of the console command.
        static::registerConsoleReturnCodeHook();
        //Will catch the route name of the request.
        static::registerRouteMatcherHooks();
        //Disable all other hooks
        if (TraceSampleResult::shouldSkipTraceBody()) {
            putenv('OTEL_PHP_DISABLED_INSTRUMENTATIONS=all');

            return;
        }

        //Autoloading of the generated hooks.
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
     * Creates a hook to capture the return code of the console command.
     *
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
     * Creates hooks to capture the route name of the request. Different hooks are used for different routing systems.
     *
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
     * Prepares a resource with the service name and other attributes.
     *
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
     * Hardcoded span exporter for OTLP gRPC. It is used to export spans to the OpenTelemetry Collector or other OTLP-compatible backends.
     *
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
     * Creates a span processor with a post-filtering mechanism that filters out spans that are faster than the configured threshold.
     *
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
     * Creates a getter for the request propagator. It cannot be created via factory as root span is created before the factory is initialized.
     *
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
        $resourceName = static::getFactory()->createResourceNameStorage()->getName();
        $customParamsStorage = static::getFactory()->createCustomParameterStorage();
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

        $span = static::updateRootSpanName($span, $request);
        $span = static::recordExceptions($span);
        $span = static::recordEvents($span);
        $span = static::recordCountOfProcessesSpans($span);

        $span->setStatus(static::getSpanStatus());

        $attributes = static::validateAttributes($customParamsStorage->getAttributes(), $request);

        $span->setAttributes($attributes);
        $span->end();
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    protected static function updateRootSpanName(SpanInterface $span, Request $request): SpanInterface
    {
        $name = static::getFactory()->createRootSpanNameStorage()->getName();
        $span->updateName($name ?: static::formatGeneralSpanName($request, true));

        return $span;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    protected static function recordExceptions(SpanInterface $span): SpanInterface
    {
        $exceptions = static::getFactory()->createExceptionStorage()->getExceptions();
        foreach ($exceptions as $exception) {
            $span->recordException($exception);
        }

        return $span;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    protected static function recordEvents(SpanInterface $span): SpanInterface
    {
        $events = static::getFactory()->createCustomEventsStorage()->getEvents();
        foreach ($events as $eventAttributes) {
            $span->addEvent($eventAttributes[CustomEventsStorage::EVENT_NAME], $eventAttributes[CustomEventsStorage::EVENT_ATTRIBUTES]);
        }

        return $span;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    protected static function recordCountOfProcessesSpans(SpanInterface $span): SpanInterface
    {
        $processedSpans = PostFilterBatchSpanProcessor::getNumberOfProcessedSpans();
        $span->setAttribute(static::ATTRIBUTE_PROCESSED_SPANS, $processedSpans);
        $span->setAttribute(static::ATTRIBUTE_IS_DETAILED_TRACE, $processedSpans >= 1);

        return $span;
    }

    /**
     * @return string
     */
    protected static function getSpanStatus(): string
    {
        $exceptions = static::getFactory()->createExceptionStorage()->getExceptions();

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

    /**
     * Gets a service factory instance. Should be used with caution, as if it called to early, it may fail as Spryker application was not boot properly yet.
     *
     * @return \Spryker\Service\Opentelemetry\OpentelemetryServiceFactory
     */
    protected static function getFactory()
    {
        if (static::$factory === null) {
            static::$factory = static::getFactoryResolver()->resolve(static::class);
        }

        return static::$factory;
    }

    /**
     * @return \Spryker\Service\Kernel\ClassResolver\AbstractClassResolver
     */
    protected static function getFactoryResolver(): AbstractClassResolver
    {
        return new FactoryResolver();
    }

    /**
     * Validates attributes array by ensuring they are valid.
     *
     * @param array<string, mixed> $attributes
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    protected static function validateAttributes(array $attributes, Request $request): array
    {
        $attributes = static::ensureValidHostAttribute($attributes, $request);

        return $attributes;
    }

    /**
     * Fixes invalid host attribute in attributes array by replacing it with correct host from request.
     *
     * @param array<string, mixed> $attributes
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array<string, mixed>
     */
    protected static function ensureValidHostAttribute(array $attributes, Request $request): array
    {
        $hostAttribute = $attributes[static::HOST_ATTRIBUTE] ?? null;
        if ($hostAttribute && is_string($hostAttribute) && static::isInvalidHost($hostAttribute)) {
            $attributes[static::HOST_ATTRIBUTE] = $request->getHost();
        }

        return $attributes;
    }

    /**
     * Checks if host value looks like a path (contains '/') and is therefore invalid.
     *
     * @param string $host
     *
     * @return bool
     */
    protected static function isInvalidHost(string $host): bool
    {
        return str_contains($host, '/');
    }
}
