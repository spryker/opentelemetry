<?php

namespace Spryker\Service\Opentelemetry;

use Spryker\Shared\Opentelemetry\OpentelemetryConfig;

class OpentelemetryInstrumentationConfig
{
    /**
     * @var string
     */
    protected const OTEL_SERVICE_NAMESPACE = 'OTEL_SERVICE_NAMESPACE';

    /**
     * @var string
     */
    protected const OTEL_BSP_MIN_SPAN_DURATION_THRESHOLD = 'OTEL_BSP_MIN_SPAN_DURATION_THRESHOLD';

    /**
     * @var string
     */
    protected const OTEL_BSP_MIN_CRITICAL_SPAN_DURATION_THRESHOLD = 'OTEL_BSP_MIN_CRITICAL_SPAN_DURATION_THRESHOLD';

    /**
     * @var string
     */
    protected const OTEL_EXPORTER_OTLP_ENDPOINT = 'OTEL_EXPORTER_OTLP_ENDPOINT';

    /**
     * @var string
     */
    protected const OTEL_PHP_EXCLUDED_URLS = 'OTEL_PHP_EXCLUDED_URLS';

    /**
     * @var string
     */
    protected const OTEL_SERVICE_NAME_MAPPING = 'OTEL_SERVICE_NAME_MAPPING';

    /**
     * @var string
     */
    protected const OTEL_DEFAULT_SERVICE_NAME = 'OTEL_DEFAULT_SERVICE_NAME';

    /**
     * @var string
     */
    protected const OTEL_TRACES_SAMPLER_ARG = 'OTEL_TRACES_SAMPLER_ARG';

    /**
     * @var string
     */
    protected const OTEL_TRACES_CRITICAL_SAMPLER_ARG = 'OTEL_TRACES_CRITICAL_SAMPLER_ARG';

    /**
     * @var string
     */
    protected const OTEL_TRACES_NON_CRITICAL_SAMPLER_ARG = 'OTEL_TRACES_NON_CRITICAL_SAMPLER_ARG';

    /**
     * @var string
     */
    protected const OTEL_BSP_SCHEDULE_DELAY = 'OTEL_BSP_SCHEDULE_DELAY';

    /**
     * @var string
     */
    protected const OTEL_BSP_MAX_QUEUE_SIZE = 'OTEL_BSP_MAX_QUEUE_SIZE';

    /**
     * @string
     */
    protected const OTEL_BSP_MAX_EXPORT_BATCH_SIZE = 'OTEL_BSP_MAX_EXPORT_BATCH_SIZE';

    /**
     * @string
     */
    protected const OTEL_TRACE_PROBABILITY = 'OTEL_TRACE_PROBABILITY';

    /**
     * @string
     */
    protected const OTEL_CLI_TRACE_PROBABILITY = 'OTEL_CLI_TRACE_PROBABILITY';

    /**
     * @string
     */
    protected const OTEL_DISTRIBUTED_TRACING_ENABLED = 'OTEL_DISTRIBUTED_TRACING_ENABLED';

    /**
     * @string
     */
    protected const OTEL_TRACE_PROBABILITY_NON_GET = 'OTEL_TRACE_PROBABILITY_NON_GET';

    /**
     * Specification:
     * - The threshold in nanoseconds for the span to be sampled.
     *
     * @api
     *
     * return int
     */
    public static function getSamplerThresholdNano(): int
    {
        $envValue = getenv(static::OTEL_BSP_MIN_SPAN_DURATION_THRESHOLD);
        $multiplicator = $envValue !== false ? $envValue : 20;

        return $multiplicator * 1000000;
    }

    /**
     * Specification:
     * - The threshold in nanoseconds for the critical span to be sampled.
     *
     * @api
     *
     * return int
     */
    public static function getSamplerThresholdNanoForCriticalSpan(): int
    {
        $envValue = getenv(static::OTEL_BSP_MIN_CRITICAL_SPAN_DURATION_THRESHOLD);
        $multiplicator = $envValue !== false ? $envValue : 10;

        return $multiplicator * 1000000;
    }

    /**
     * Specification:
     * - Returns a namespace for the service that is added to resource attributes.
     *
     * @api
     *
     * @return string
     */
    public static function getServiceNamespace(): string
    {
        return getenv(static::OTEL_SERVICE_NAMESPACE) ?: 'spryker';
    }

    /**
     * Specification:
     * - Returns the endpoint for the OTLP exporter.
     *
     * @api
     *
     * @return string
     */
    public static function getExporterEndpoint(): string
    {
        return getenv(static::OTEL_EXPORTER_OTLP_ENDPOINT) ?: 'http://collector:4317';
    }

    /**
     * Specification:
     * - Returns the excluded routes for the instrumentation. Instrumentation will not be executed to these routes.
     *
     * @api
     *
     * @return array<string>
     */
    public static function getExcludedRoutes(): array
    {
        $urls = getenv(static::OTEL_PHP_EXCLUDED_URLS) ?: '';

        return explode(',', $urls);
    }

    /**
     * Specification:
     * - An key => value array where key is a service name and value is a regex pattern to match the service name.
     * - Those values are used in order to determine the fallback service name based on the current request.
     * @api
     *
     * @return array<string>
     */
    public static function getServiceNameMapping(): array
    {
        $mapping = getenv(static::OTEL_SERVICE_NAME_MAPPING) ?: '{}';
        $mapping = json_decode($mapping, true);

        if ($mapping === []) {
            return [
                'Yves' => '/(?:https?:\/\/)?(yves)\./',
                'Backoffice' => '/(?:https?:\/\/)?(backoffice)\./',
                'REST API' => '/(?:https?:\/\/)?(glue)\./',
                'Merchant Portal' => '/(?:https?:\/\/)?(merchant)\./',
                'Backend Gateway' => '/(?:https?:\/\/)?(backend-gateway)\./',
                'Storefront API' => '/(?:https?:\/\/)?(glue-storefront)\./',
                'Backend API' => '/(?:https?:\/\/)?(glue-backend)\./',
            ];
        }

        return $mapping;
    }

    /**
     * Specification:
     * - Returns the default service name that will be used if no service name is determined or provided by other means.
     *
     * @api
     *
     * @return string
     */
    public static function getDefaultServiceName(): string
    {
        return getenv(static::OTEL_DEFAULT_SERVICE_NAME) ?: 'Default Service';
    }

    /**
     * Specification:
     * - Returns the probability for the sampler to sample spans in transaction.
     * - This values is used for `regular` spans.
     *
     * @api
     *
     * @return float
     */
    public static function getSamplerProbability(): float
    {
        $envVar = getenv(static::OTEL_TRACES_SAMPLER_ARG);
        $probability = $envVar !== false ? $envVar : 0.1;

        return (float)$probability;
    }

    /**
     * Specification:
     *  - Returns the probability for the sampler to sample spans in transaction.
     *  - This values is used for `critical` spans, like facade methods or not SELECT database queries.
     *
     * @api
     *
     * @return float
     */
    public static function getSamplerProbabilityForCriticalSpans(): float
    {
        $envVar = getenv(static::OTEL_TRACES_CRITICAL_SAMPLER_ARG);
        $probability = $envVar !== false ? $envVar : 0.5;

        return (float)$probability;
    }

    /**
     * Specification:
     *  - Returns the probability for the sampler to sample spans in transaction.
     *  - This values is used for `not critical` spans, like SELECT database queries.
     *
     * @api
     *
     * @return float
     */
    public static function getSamplerProbabilityForNonCriticalSpans(): float
    {
        $envVar = getenv(static::OTEL_TRACES_NON_CRITICAL_SAMPLER_ARG);
        $probability = $envVar !== false ? $envVar : 0.1;

        return (float)$probability;
    }

    /**
     * Specification:
     * - Defines the delay in milliseconds before sending a batch of spans to the exporter.
     *
     * @api
     *
     * @return int
     */
    public static function getSpanProcessorScheduleDelay(): int
    {
        $delay = getenv(static::OTEL_BSP_SCHEDULE_DELAY);
        if ($delay === false) {
            return 1000;
        }

        return (int)$delay;
    }

    /**
     * Specification:
     * - Defines the maximum queue size for the span processor. All spans that are not processed will be dropped.
     *
     * @api
     *
     * @return int
     */
    public static function getSpanProcessorMaxQueueSize(): int
    {
        $delay = getenv(static::OTEL_BSP_MAX_QUEUE_SIZE);
        if ($delay === false) {
            return 2048;
        }

        return (int)$delay;
    }

    /**
     * Specification:
     * - Defines the batch size for spans. Once this limit is reached, the batch is sent to the exporter.
     *
     * @api
     *
     * @return int
     */
    public static function getSpanProcessorMaxBatchSize(): int
    {
        $delay = getenv(static::OTEL_BSP_MAX_EXPORT_BATCH_SIZE);
        if ($delay === false) {
            return 512;
        }

        return (int)$delay;
    }

    /**
     * Specification:
     * - Returns the probability for the trace to be detailed or not for HTTP GET requests.
     *
     * @api
     *
     * @return float
     */
    public static function getTraceSamplerProbability(): float
    {
        $envVar = getenv(static::OTEL_TRACE_PROBABILITY);
        $probability = $envVar !== false ? $envVar : 0.3;

        return (float)$probability;
    }

    /**
     * Specification:
     * - Returns the probability for the trace to be detailed or not for HTTP non GET requests.
     *
     * @api
     *
     * @return float
     */
    public static function getTraceSamplerProbabilityNonGet(): float
    {
        $envVar = getenv(static::OTEL_TRACE_PROBABILITY_NON_GET);
        $probability = $envVar !== false ? $envVar : 1;

        return (float)$probability;
    }

    /**
     * Specification:
     * - Returns the probability for the trace for CLI commands.
     *
     * @api
     *
     * @return float
     */
    public static function getTraceCLISamplerProbability(): float
    {
        $envVar = getenv(static::OTEL_CLI_TRACE_PROBABILITY);
        $probability = $envVar !== false ? $envVar : 0.5;

        return (float)$probability;
    }

    /**
     * Specification:
     * - Defines if distributed tracing is enabled. if set to false the traceparent header will not be propagated or processed.
     *
     * @api
     *
     * @return bool
     */
    public static function getIsDistributedTracingEnabled(): bool
    {
        $dtEnabled = getenv(static::OTEL_DISTRIBUTED_TRACING_ENABLED);

        if ($dtEnabled === 0) {
            return false;
        }

        return true;
    }

    /**
     * Specification:
     * - Returns the directory where the generated hooks are stored.
     *
     * @api
     *
     * @return string
     */
    public static function getHooksDir(): string
    {
        return OpentelemetryConfig::getHooksDir();
    }
}
