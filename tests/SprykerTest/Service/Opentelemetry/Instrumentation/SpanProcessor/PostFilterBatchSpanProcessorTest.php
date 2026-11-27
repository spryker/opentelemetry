<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Service\Opentelemetry\Instrumentation\SpanProcessor;

use ArrayObject;
use Codeception\Test\Unit;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;
use SprykerTest\Service\Opentelemetry\OpentelemetryServiceTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Service
 * @group Opentelemetry
 * @group Instrumentation
 * @group SpanProcessor
 * @group PostFilterBatchSpanProcessorTest
 * Add your own group annotations below this line
 */
class PostFilterBatchSpanProcessorTest extends Unit
{
    /**
     * @var array<string, string>
     */
    protected const THRESHOLD_ENV_VARIABLES = [
        'OTEL_BSP_MIN_SPAN_DURATION_THRESHOLD' => '20',
        'OTEL_BSP_MIN_CRITICAL_SPAN_DURATION_THRESHOLD' => '10',
    ];

    /**
     * Shorter than both duration thresholds, so a span with status OK is dropped.
     *
     * @var int
     */
    protected const DROPPED_SPAN_DURATION_MILLIS = 2;

    /**
     * @var int
     */
    protected const KEPT_SPAN_DURATION_MILLIS = 50;

    /**
     * @var \SprykerTest\Service\Opentelemetry\OpentelemetryServiceTester
     */
    protected OpentelemetryServiceTester $tester;

    /**
     * @var array<string, string|false>
     */
    protected array $originalEnvVariables = [];

    /**
     * @var \ArrayObject<string, string|null>
     */
    protected ArrayObject $exportedParentSpanIds;

    /**
     * @return void
     */
    protected function _before(): void
    {
        parent::_before();

        foreach (static::THRESHOLD_ENV_VARIABLES as $name => $value) {
            $this->originalEnvVariables[$name] = getenv($name);
            putenv(sprintf('%s=%s', $name, $value));
        }

        $this->tester->resetStaticState();
        $this->exportedParentSpanIds = new ArrayObject();
    }

    /**
     * @return void
     */
    protected function _after(): void
    {
        foreach ($this->originalEnvVariables as $name => $value) {
            putenv($value === false ? $name : sprintf('%s=%s', $name, $value));
        }

        $this->tester->resetStaticState();

        parent::_after();
    }

    /**
     * @return void
     */
    public function testOnEndReparentsKeptSpanToKeptGrandparentWhenParentIsDropped(): void
    {
        // Arrange
        $tracerProvider = $this->tester->createTracerProvider($this->createProcessor());
        $tracer = $tracerProvider->getTracer('test');
        [$keptAncestor] = $this->startAndEndTraceWithDroppedChain($tracer, 1);

        // Act
        $tracerProvider->shutdown();

        // Assert
        $this->assertArrayNotHasKey('dropped0', $this->exportedParentSpanIds->getArrayCopy());
        $this->assertSame($keptAncestor->getContext()->getSpanId(), $this->exportedParentSpanIds['errorChild']);
    }

    /**
     * @return void
     */
    public function testOnEndReparentsKeptSpanToFirstKeptAncestorWhenSeveralAncestorsInARowAreDropped(): void
    {
        // Arrange
        $tracerProvider = $this->tester->createTracerProvider($this->createProcessor());
        $tracer = $tracerProvider->getTracer('test');
        [$keptAncestor] = $this->startAndEndTraceWithDroppedChain($tracer, 3);

        // Act
        $tracerProvider->shutdown();

        // Assert
        $this->assertSame($keptAncestor->getContext()->getSpanId(), $this->exportedParentSpanIds['errorChild']);
    }

    /**
     * @return void
     */
    public function testOnEndDoesNotChangeParentOfKeptSpanWhoseParentIsKept(): void
    {
        // Arrange
        $tracerProvider = $this->tester->createTracerProvider($this->createProcessor());
        $tracer = $tracerProvider->getTracer('test');
        $root = $this->tester->startSpan($tracer, 'root');
        $child = $this->tester->startSpan($tracer, 'child', $root);
        $this->tester->endSpan($child, static::KEPT_SPAN_DURATION_MILLIS);
        $this->tester->endSpan($root, static::KEPT_SPAN_DURATION_MILLIS);

        // Act
        $tracerProvider->shutdown();

        // Assert
        $this->assertSame($root->getContext()->getSpanId(), $this->exportedParentSpanIds['child']);
        $this->assertNull($this->exportedParentSpanIds['root']);
    }

    /**
     * @return void
     */
    public function testOnEndDoesNotRecordDroppedSpansWithoutKeptDescendants(): void
    {
        // Arrange
        $tracer = $this->tester->createTracerProvider($this->createProcessor())->getTracer('test');
        $root = $this->tester->startSpan($tracer, 'root');

        // Act
        for ($i = 0; $i < 100; $i++) {
            $droppedSpan = $this->tester->startSpan($tracer, 'dropped' . $i, $root);
            $droppedSpan->setStatus(StatusCode::STATUS_OK);
            $this->tester->endSpan($droppedSpan, static::DROPPED_SPAN_DURATION_MILLIS);
        }

        // Assert
        $this->assertSame(0, $this->tester->countStaticArrayProperty(PostFilterBatchSpanProcessor::class, 'parentSpanIdMapping'));
    }

    /**
     * @return void
     */
    public function testForceFlushClearsParentSpanIdMappingWhenAllSpansAreExported(): void
    {
        // Arrange
        $tracerProvider = $this->tester->createTracerProvider($this->createProcessor());
        $this->startAndEndTraceWithDroppedChain($tracerProvider->getTracer('test'), 1);

        // Act
        $tracerProvider->forceFlush();

        // Assert
        $this->assertSame(0, $this->tester->countStaticArrayProperty(PostFilterBatchSpanProcessor::class, 'parentSpanIdMapping'));
    }

    /**
     * @return void
     */
    public function testOnEndEvictsOldestParentSpanIdMappingEntriesWhenRegistrySizeLimitIsReached(): void
    {
        // Arrange
        $processor = new class ($this->tester->createParentSpanIdCapturingExporter($this->exportedParentSpanIds), Clock::getDefault()) extends PostFilterBatchSpanProcessor {
            /**
             * @var int
             */
            protected const MAX_PARENT_SPAN_ID_REGISTRY_SIZE = 2;
        };
        $tracer = $this->tester->createTracerProvider($processor)->getTracer('test');
        $root = $this->tester->startSpan($tracer, 'root');

        // Act
        for ($i = 0; $i < 5; $i++) {
            $droppedSpan = $this->tester->startSpan($tracer, 'dropped' . $i, $root);
            $droppedSpan->setStatus(StatusCode::STATUS_OK);
            $errorChild = $this->tester->startSpan($tracer, 'errorChild' . $i, $droppedSpan);
            $errorChild->setStatus(StatusCode::STATUS_ERROR);
            $this->tester->endSpan($errorChild, static::DROPPED_SPAN_DURATION_MILLIS);
            $this->tester->endSpan($droppedSpan, static::DROPPED_SPAN_DURATION_MILLIS);
        }

        // Assert
        $this->assertSame(2, $this->tester->countStaticArrayProperty(PostFilterBatchSpanProcessor::class, 'parentSpanIdMapping'));
    }

    /**
     * @return void
     */
    public function testOnEndKeepsRootSpanWhenQueueIsFull(): void
    {
        // Arrange
        $maxQueueSize = 2;
        $tracerProvider = $this->tester->createTracerProvider(new PostFilterBatchSpanProcessor(
            $this->tester->createParentSpanIdCapturingExporter($this->exportedParentSpanIds),
            Clock::getDefault(),
            $maxQueueSize,
            PostFilterBatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
            $maxQueueSize,
        ));
        $tracer = $tracerProvider->getTracer('test');
        $rootSpan = $tracer->spanBuilder('root')
            ->setParent(false)
            ->setStartTimestamp(OpentelemetryServiceTester::START_EPOCH_NANOS)
            ->setAttribute(SprykerInstrumentationBootstrap::ROOT_ATTRIBUTE, true)
            ->startSpan();

        // Act
        for ($i = 0; $i <= $maxQueueSize; $i++) {
            $this->tester->endSpan($this->tester->startSpan($tracer, 'child' . $i, $rootSpan), static::KEPT_SPAN_DURATION_MILLIS);
        }
        $lateChild = $this->tester->startSpan($tracer, 'lateChild', $rootSpan);
        $this->tester->endSpan($rootSpan, static::KEPT_SPAN_DURATION_MILLIS);
        $this->tester->endSpan($lateChild, static::KEPT_SPAN_DURATION_MILLIS);
        $tracerProvider->shutdown();

        // Assert
        $this->assertSame(['child0', 'child1', 'root'], array_keys($this->exportedParentSpanIds->getArrayCopy()));
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor
     */
    protected function createProcessor(): PostFilterBatchSpanProcessor
    {
        return new PostFilterBatchSpanProcessor(
            $this->tester->createParentSpanIdCapturingExporter($this->exportedParentSpanIds),
            Clock::getDefault(),
        );
    }

    /**
     * Builds root -> keptAncestor -> dropped0 -> ... -> dropped{N-1} -> errorChild and ends the spans children-first.
     *
     * @param \OpenTelemetry\API\Trace\TracerInterface $tracer
     * @param int $numberOfDroppedSpans
     *
     * @return array{\OpenTelemetry\API\Trace\SpanInterface, \OpenTelemetry\API\Trace\SpanInterface}
     */
    protected function startAndEndTraceWithDroppedChain(TracerInterface $tracer, int $numberOfDroppedSpans): array
    {
        $root = $this->tester->startSpan($tracer, 'root');
        $keptAncestor = $this->tester->startSpan($tracer, 'keptAncestor', $root);

        $droppedSpans = [];
        $parentSpan = $keptAncestor;
        for ($i = 0; $i < $numberOfDroppedSpans; $i++) {
            $parentSpan = $this->tester->startSpan($tracer, 'dropped' . $i, $parentSpan);
            $parentSpan->setStatus(StatusCode::STATUS_OK);
            $droppedSpans[] = $parentSpan;
        }

        $errorChild = $this->tester->startSpan($tracer, 'errorChild', $parentSpan);
        $errorChild->setStatus(StatusCode::STATUS_ERROR);

        $this->tester->endSpan($errorChild, static::DROPPED_SPAN_DURATION_MILLIS);
        foreach (array_reverse($droppedSpans) as $droppedSpan) {
            $this->tester->endSpan($droppedSpan, static::DROPPED_SPAN_DURATION_MILLIS);
        }
        $this->tester->endSpan($keptAncestor, static::KEPT_SPAN_DURATION_MILLIS);
        $this->tester->endSpan($root, static::KEPT_SPAN_DURATION_MILLIS);

        return [$keptAncestor, $root];
    }
}
