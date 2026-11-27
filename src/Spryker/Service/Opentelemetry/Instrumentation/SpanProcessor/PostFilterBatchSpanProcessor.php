<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor;

use InvalidArgumentException;
use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\ObserverInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\ReadWriteSpanInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use SplQueue;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Span\SpanIdUpdateAwareSpanContextInterface;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Throwable;

class PostFilterBatchSpanProcessor implements SpanProcessorInterface
{
    use LogsMessagesTrait;

    /**
     * @var int
     */
    public const DEFAULT_SCHEDULE_DELAY = 5000;

    /**
     * @var int
     */
    public const DEFAULT_MAX_QUEUE_SIZE = 2048;

    /**
     * @var int
     */
    public const DEFAULT_MAX_EXPORT_BATCH_SIZE = 512;

    /**
     * @var array<string, string>
     */
    protected const ATTRIBUTES_PROCESSOR = ['processor' => 'batching'];

    /**
     * @var array<string, string>
     */
    protected const ATTRIBUTES_QUEUED = self::ATTRIBUTES_PROCESSOR + ['state' => 'queued'];

    /**
     * @var array<string, string>
     */
    protected const ATTRIBUTES_PENDING = self::ATTRIBUTES_PROCESSOR + ['state' => 'pending'];

    /**
     * @var array<string, string>
     */
    protected const ATTRIBUTES_PROCESSED = self::ATTRIBUTES_PROCESSOR + ['state' => 'processed'];

    /**
     * @var array<string, string>
     */
    protected const ATTRIBUTES_DROPPED = self::ATTRIBUTES_PROCESSOR + ['state' => 'dropped'];

    /**
     * @var array<string, string>
     */
    protected const ATTRIBUTES_FREE = self::ATTRIBUTES_PROCESSOR + ['state' => 'free'];

    /**
     * Upper bound for each static parent span id registry. The oldest entries are evicted first;
     * an evicted entry can only result in an orphaned span in the trace, never in a lost span.
     *
     * @var int
     */
    protected const MAX_PARENT_SPAN_ID_REGISTRY_SIZE = 10000;

    /**
     * @var int
     */
    protected int $maxQueueSize;

    /**
     * @var int
     */
    protected int $scheduledDelayNanos;

    /**
     * @var int
     */
    protected int $maxExportBatchSize;

    /**
     * @var \OpenTelemetry\Context\ContextInterface
     */
    protected ContextInterface $exportContext;

    /**
     * @var int|null
     */
    protected ?int $nextScheduledRun = null;

    /**
     * @var bool
     */
    protected bool $running = false;

    /**
     * @var int
     */
    protected int $dropped = 0;

    /**
     * @var int
     */
    protected int $processed = 0;

    /**
     * @var int
     */
    protected int $batchId = 0;

    /**
     * @var int
     */
    protected int $queueSize = 0;

    /**
     * @var list<\Spryker\Service\Opentelemetry\Instrumentation\Span\Span>
     */
    protected array $batch = [];

    /**
     * @var \SplQueue<list<\Spryker\Service\Opentelemetry\Instrumentation\Span\Span>>
     */
    protected SplQueue $queue;

    /**
     * @var \SplQueue<array{int, string, ?CancellationInterface, bool, ContextInterface}>
     */
    protected SplQueue $flush;

    /**
     * @var bool
     */
    protected bool $closed = false;

    /**
     * Dropped span id => its parent span id. Only dropped spans that have a buffered kept descendant are recorded.
     *
     * @var array<string, string>
     */
    protected static $parentSpanIdMapping = [];

    /**
     * Ids of spans that have a buffered kept descendant, either directly or through dropped spans.
     *
     * @var array<string, bool>
     */
    protected static array $spanIdsWithKeptDescendants = [];

    /**
     * @var int
     */
    protected static int $processedSpans = 0;

    /**
     * @param \OpenTelemetry\SDK\Trace\SpanExporterInterface $exporter
     * @param \OpenTelemetry\API\Common\Time\ClockInterface $clock
     * @param int $maxQueueSize
     * @param int $scheduledDelayMillis
     * @param int $maxExportBatchSize
     * @param bool $autoFlush
     * @param \OpenTelemetry\API\Metrics\MeterProviderInterface|null $meterProvider
     */
    public function __construct(
        protected SpanExporterInterface $exporter,
        protected ClockInterface $clock,
        int $maxQueueSize = self::DEFAULT_MAX_QUEUE_SIZE,
        int $scheduledDelayMillis = self::DEFAULT_SCHEDULE_DELAY,
        int $maxExportBatchSize = self::DEFAULT_MAX_EXPORT_BATCH_SIZE,
        protected bool $autoFlush = false,
        ?MeterProviderInterface $meterProvider = null,
    ) {
        $this->setMaxQueueSize($maxQueueSize);
        $this->setScheduledDelayTime($scheduledDelayMillis);
        $this->setMaxExportBatchSize($maxExportBatchSize, $maxQueueSize);

        $this->exportContext = Context::getCurrent();
        $this->queue = new SplQueue();
        $this->flush = new SplQueue();

        $this->initMetrics($meterProvider);
    }

    /**
     * @return int
     */
    public static function getNumberOfProcessedSpans(): int
    {
        return static::$processedSpans;
    }

    /**
     * @param \OpenTelemetry\SDK\Trace\ReadWriteSpanInterface $span
     * @param \OpenTelemetry\Context\ContextInterface $parentContext
     *
     * @return void
     */
    public function onStart(ReadWriteSpanInterface $span, ContextInterface $parentContext): void
    {
        //Nothing is required here, but method is a part of an API
    }

    /**
     * @param \OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     *
     * @return void
     */
    public function onEnd(ReadableSpanInterface $span): void
    {
        if ($this->closed) {
            return;
        }

        if (!$span->getContext()->isSampled()) {
            return;
        }

        $duration = $span->getAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE) === true
            ? OpentelemetryInstrumentationConfig::getSamplerThresholdNanoForCriticalSpan()
            : OpentelemetryInstrumentationConfig::getSamplerThresholdNano();

        if ($span->getAttribute(CriticalSpanRatioSampler::IS_SYSTEM_ATTRIBUTE) !== true
            && $span->getDuration() < $duration
            && $span->getParentSpanId()
            && $span->getStatus()->getCode() === StatusCode::STATUS_OK
        ) {
            $this->dropped++;
            $this->registerDroppedSpan($span);

            return;
        }

        // The root span ends last, so in long-running processes the queue is usually full by then; without it the trace has no root.
        if ($this->queueSize >= $this->maxQueueSize && $span->getAttribute(SprykerInstrumentationBootstrap::ROOT_ATTRIBUTE) !== true) {
            $this->dropped++;
            $this->registerDroppedSpan($span);

            return;
        }

        $this->queueSize++;
        static::$processedSpans++;
        $this->registerKeptSpan($span);

        $this->batch[] = $span;
        $this->nextScheduledRun ??= $this->clock->now() + $this->scheduledDelayNanos;

        if (count($this->batch) === $this->maxExportBatchSize) {
            $this->enqueueBatch();
        }
        if ($this->autoFlush) {
            $this->flush();
        }
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
     * @throws \Exception
     *
     * @return bool
     */
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        return $this->flush(__FUNCTION__, $cancellation);
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
     * @throws \Exception
     *
     * @return bool
     */
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if ($this->closed) {
            return false;
        }

        $this->closed = true;

        return $this->flush(__FUNCTION__, $cancellation);
    }

    /**
     * @param \OpenTelemetry\SDK\Trace\SpanExporterInterface $exporter
     *
     * @return \OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder
     */
    public static function builder(SpanExporterInterface $exporter): BatchSpanProcessorBuilder
    {
        return new BatchSpanProcessorBuilder($exporter);
    }

    /**
     * @param int $maxQueueSize
     *
     * @return void
     */
    protected function setMaxQueueSize(int $maxQueueSize): void
    {
        if ($maxQueueSize <= 0) {
            throw new InvalidArgumentException(sprintf('Maximum queue size (%d) must be greater than zero', $maxQueueSize));
        }

        $this->maxQueueSize = $maxQueueSize;
    }

    /**
     * @param int $scheduledDelayMillis
     *
     * @return void
     */
    protected function setScheduledDelayTime(int $scheduledDelayMillis): void
    {
        if ($scheduledDelayMillis <= 0) {
            throw new InvalidArgumentException(sprintf('Scheduled delay (%d) must be greater than zero', $scheduledDelayMillis));
        }

        $this->scheduledDelayNanos = $scheduledDelayMillis * 1_000_000;
    }

    /**
     * @param int $maxExportBatchSize
     * @param int $maxQueueSize
     *
     * @return void
     */
    protected function setMaxExportBatchSize(int $maxExportBatchSize, int $maxQueueSize): void
    {
        if ($maxExportBatchSize <= 0) {
            throw new InvalidArgumentException(sprintf('Maximum export batch size (%d) must be greater than zero', $maxExportBatchSize));
        }
        if ($maxExportBatchSize > $maxQueueSize) {
            throw new InvalidArgumentException(sprintf('Maximum export batch size (%d) must be less than or equal to maximum queue size (%d)', $maxExportBatchSize, $maxQueueSize));
        }

        $this->maxExportBatchSize = $maxExportBatchSize;
    }
    /**
     * @param \OpenTelemetry\API\Metrics\MeterProviderInterface|null $meterProvider
     *
     * @return void
     */
    protected function initMetrics(?MeterProviderInterface $meterProvider = null): void
    {
        if ($meterProvider === null) {
            return;
        }

        $meter = $meterProvider->getMeter('io.spryker.metrics');
        $meter
            ->createObservableUpDownCounter(
                'otel.trace.span_processor.spans',
                '{spans}',
                'The number of sampled spans received by the span processor',
            )
            ->observe(function (ObserverInterface $observer): void {
                $queued = $this->queue->count() * $this->maxExportBatchSize + count($this->batch);
                $pending = $this->queueSize - $queued;
                $processed = $this->processed;
                $dropped = $this->dropped;

                $observer->observe($queued, static::ATTRIBUTES_QUEUED);
                $observer->observe($pending, static::ATTRIBUTES_PENDING);
                $observer->observe($processed, static::ATTRIBUTES_PROCESSED);
                $observer->observe($dropped, static::ATTRIBUTES_DROPPED);
            });
        $meter
            ->createObservableUpDownCounter(
                'otel.trace.span_processor.queue.limit',
                '{spans}',
                'The queue size limit',
            )
            ->observe(function (ObserverInterface $observer): void {
                $observer->observe($this->maxQueueSize, static::ATTRIBUTES_PROCESSOR);
            });
        $meter
            ->createObservableUpDownCounter(
                'otel.trace.span_processor.queue.usage',
                '{spans}',
                'The current queue usage',
            )
            ->observe(function (ObserverInterface $observer): void {
                $queued = $this->queue->count() * $this->maxExportBatchSize + count($this->batch);
                $pending = $this->queueSize - $queued;
                $free = $this->maxQueueSize - $this->queueSize;

                $observer->observe($queued, static::ATTRIBUTES_QUEUED);
                $observer->observe($pending, static::ATTRIBUTES_PENDING);
                $observer->observe($free, static::ATTRIBUTES_FREE);
            });
    }

    /**
     * @param string|null $flushMethod
     * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
     * @throws \Throwable
     *
     * @return bool
     */
    protected function flush(?string $flushMethod = null, ?CancellationInterface $cancellation = null): bool
    {
        if ($flushMethod !== null) {
            $flushId = $this->batchId + $this->queue->count() + (int)(bool)$this->batch;
            $this->flush->enqueue([$flushId, $flushMethod, $cancellation, !$this->running, Context::getCurrent()]);
        }

        if ($this->running) {
            return false;
        }

        $success = true;
        $exception = null;
        $this->running = true;

        try {
            for (;;) {
                while (!$this->flush->isEmpty() && $this->flush->bottom()[0] <= $this->batchId) {
                    [, $flushMethod, $cancellation, $propagateResult, $context] = $this->flush->dequeue();
                    $scope = $context->activate();

                    try {
                        $result = $this->exporter->$flushMethod($cancellation);
                        if ($propagateResult) {
                            $success = $result;
                        }
                    } catch (Throwable $e) {
                        if ($propagateResult) {
                            $exception = $e;
                        } else {
                            self::logError(sprintf('Unhandled %s error', $flushMethod), ['exception' => $e]);
                        }
                    } finally {
                        $scope->detach();
                    }
                }

                if (!$this->shouldFlush()) {
                    break;
                }

                if ($this->queue->isEmpty()) {
                    $this->enqueueBatch();
                }
                $batchSize = count($this->queue->bottom());
                $this->batchId++;
                $scope = $this->exportContext->activate();

                try {
                    $batch = $this->queue->dequeue();
                    $this->resolveParentSpanIds($batch);
                    $this->exporter->export($batch)->await();
                } catch (Throwable $e) {
                    self::logError('Unhandled export error', ['exception' => $e]);
                } finally {
                    $this->processed += $batchSize;
                    $this->queueSize -= $batchSize;
                    $scope->detach();
                }
            }
        } finally {
            $this->running = false;
        }

        if ($this->batch === [] && $this->queue->isEmpty()) {
            // Entries are only needed to re-parent buffered spans; with nothing buffered they can never be used again.
            static::$parentSpanIdMapping = [];
            static::$spanIdsWithKeptDescendants = [];
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $success;
    }

    /**
     * @return bool
     */
    protected function shouldFlush(): bool
    {
        return !$this->flush->isEmpty()
            || $this->autoFlush && !$this->queue->isEmpty()
            || $this->autoFlush && $this->nextScheduledRun !== null && $this->clock->now() > $this->nextScheduledRun;
    }

    /**
     * @return void
     */
    protected function enqueueBatch(): void
    {
        assert($this->batch !== []);

        $this->queue->enqueue($this->batch);
        $this->batch = [];
        $this->nextScheduledRun = null;
    }

    /**
     * Spans end children-first, so the kept descendants of a span are already buffered when it ends.
     *
     * @param \OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     *
     * @return void
     */
    protected function registerKeptSpan(ReadableSpanInterface $span): void
    {
        unset(static::$spanIdsWithKeptDescendants[$span->getContext()->getSpanId()]);

        $this->markSpanAsHavingKeptDescendant($span->getParentContext());
    }

    /**
     * Records the parent of a dropped span only when a buffered kept span has to be re-parented over it.
     *
     * @param \OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     *
     * @return void
     */
    protected function registerDroppedSpan(ReadableSpanInterface $span): void
    {
        $spanId = $span->getContext()->getSpanId();

        if (!isset(static::$spanIdsWithKeptDescendants[$spanId])) {
            return;
        }

        unset(static::$spanIdsWithKeptDescendants[$spanId]);

        $parentSpanContext = $span->getParentContext();
        if (!$parentSpanContext->isValid()) {
            return;
        }

        static::$parentSpanIdMapping[$spanId] = $parentSpanContext->getSpanId();
        if (count(static::$parentSpanIdMapping) > static::MAX_PARENT_SPAN_ID_REGISTRY_SIZE) {
            unset(static::$parentSpanIdMapping[array_key_first(static::$parentSpanIdMapping)]);
        }

        $this->markSpanAsHavingKeptDescendant($parentSpanContext);
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanContextInterface $spanContext
     *
     * @return void
     */
    protected function markSpanAsHavingKeptDescendant(SpanContextInterface $spanContext): void
    {
        if (!$spanContext->isValid()) {
            return;
        }

        static::$spanIdsWithKeptDescendants[$spanContext->getSpanId()] = true;
        if (count(static::$spanIdsWithKeptDescendants) > static::MAX_PARENT_SPAN_ID_REGISTRY_SIZE) {
            unset(static::$spanIdsWithKeptDescendants[array_key_first(static::$spanIdsWithKeptDescendants)]);
        }
    }

    /**
     * Re-parents spans to their closest not dropped ancestor right before the export,
     * when the drop decision is known for every ancestor that has already ended.
     *
     * @param list<\OpenTelemetry\SDK\Trace\ReadableSpanInterface> $spans
     *
     * @return void
     */
    protected function resolveParentSpanIds(array $spans): void
    {
        if (static::$parentSpanIdMapping === []) {
            return;
        }

        foreach ($spans as $span) {
            $parentSpanContext = $span->getParentContext();
            if (!$parentSpanContext instanceof SpanIdUpdateAwareSpanContextInterface) {
                continue;
            }

            $parentSpanId = $this->findClosestNotDroppedAncestorSpanId($parentSpanContext->getSpanId());
            if ($parentSpanId !== $parentSpanContext->getSpanId()) {
                $parentSpanContext->updateSpanId($parentSpanId);
            }
        }
    }

    /**
     * @param string $spanId
     *
     * @return string
     */
    protected function findClosestNotDroppedAncestorSpanId(string $spanId): string
    {
        $visitedSpanIds = [];

        while (isset(static::$parentSpanIdMapping[$spanId]) && !isset($visitedSpanIds[$spanId])) {
            $visitedSpanIds[$spanId] = true;
            $spanId = static::$parentSpanIdMapping[$spanId];
        }

        return $spanId;
    }
}
