<?php

namespace Spryker\Service\Opentelemetry;

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
     * Specification:
     * - The threshold in nanoseconds for the span to be sampled.
     *
     * @api
     *
     * return int
     */
    public static function getSamplerThresholdNano(): int
    {
        $multiplicator = getenv(static::OTEL_BSP_MIN_SPAN_DURATION_THRESHOLD) ?: 20;

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
        $multiplicator = getenv(static::OTEL_BSP_MIN_CRITICAL_SPAN_DURATION_THRESHOLD) ?: 10;

        return $multiplicator * 1000000;
    }

    /**
     * @api
     *
     * @return string
     */
    public static function getServiceNamespace(): string
    {
        return getenv(static::OTEL_SERVICE_NAMESPACE) ?: 'spryker';
    }

    /**
     * @api
     *
     * @return string
     */
    public static function getExporterEndpoint(): string
    {
        return getenv(static::OTEL_EXPORTER_OTLP_ENDPOINT) ?: 'http://collector:4317';
    }

    /**
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
     * @api
     *
     * @return string
     */
    public static function getDefaultServiceName(): string
    {
        return getenv(static::OTEL_DEFAULT_SERVICE_NAME) ?: 'Default Service';
    }

    /**
     * @api
     *
     * @return float
     */
    public static function getSamplerProbability(): float
    {
        $probability = getenv(static::OTEL_TRACES_SAMPLER_ARG) ?: 0.1;

        return (float)$probability;
    }

    /**
     * @api
     *
     * @return float
     */
    public static function getSamplerProbabilityForCriticalSpans(): float
    {
        $probability = getenv(static::OTEL_TRACES_CRITICAL_SAMPLER_ARG) ?: 0.5;

        return (float)$probability;
    }

    /**
     * @api
     *
     * @return float
     */
    public static function getSamplerProbabilityForNonCriticalSpans(): float
    {
        $probability = getenv(static::OTEL_TRACES_NON_CRITICAL_SAMPLER_ARG) ?: 0.1;

        return (float)$probability;
    }

    /**
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
     * @return float
     */
    public static function getTraceSamplerProbability(): float
    {
        $probability = getenv(static::OTEL_TRACE_PROBABILITY) ?: 0.3;

        return (float)$probability;
    }

    /**
     * @return float
     */
    public static function getTraceCLISamplerProbability(): float
    {
        $probability = getenv(static::OTEL_CLI_TRACE_PROBABILITY) ?: 0.5;

        return (float)$probability;
    }

    /**
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
}
