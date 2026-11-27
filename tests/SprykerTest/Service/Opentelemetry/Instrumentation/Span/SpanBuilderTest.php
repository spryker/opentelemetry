<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Service\Opentelemetry\Instrumentation\Span;

use ArrayObject;
use Codeception\Test\Unit;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use SprykerTest\Service\Opentelemetry\OpentelemetryServiceTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Service
 * @group Opentelemetry
 * @group Instrumentation
 * @group Span
 * @group SpanBuilderTest
 * Add your own group annotations below this line
 */
class SpanBuilderTest extends Unit
{
    /**
     * @var string
     */
    protected const UNSAMPLED_SPAN_NAME = 'unsampled';

    /**
     * @var int
     */
    protected const KEPT_SPAN_DURATION_MILLIS = 50;

    /**
     * @var \SprykerTest\Service\Opentelemetry\OpentelemetryServiceTester
     */
    protected OpentelemetryServiceTester $tester;

    /**
     * @return void
     */
    protected function _before(): void
    {
        parent::_before();

        $this->tester->resetStaticState();
    }

    /**
     * @return void
     */
    protected function _after(): void
    {
        $this->tester->resetStaticState();

        parent::_after();
    }

    /**
     * @return void
     */
    public function testStartSpanParentsChildOfUnsampledSpanToSampledGrandparent(): void
    {
        // Arrange
        $exportedParentSpanIds = new ArrayObject();
        $tracerProvider = $this->tester->createTracerProvider(
            new PostFilterBatchSpanProcessor($this->tester->createParentSpanIdCapturingExporter($exportedParentSpanIds), Clock::getDefault()),
            $this->createSamplerDroppingSpansNamed(static::UNSAMPLED_SPAN_NAME),
        );
        $tracer = $tracerProvider->getTracer('test');
        $root = $this->tester->startSpan($tracer, 'root');
        $unsampledSpan = $this->tester->startSpan($tracer, static::UNSAMPLED_SPAN_NAME, $root);

        // Act
        $child = $this->tester->startSpan($tracer, 'child', $unsampledSpan);

        // Assert
        $this->tester->endSpan($child, static::KEPT_SPAN_DURATION_MILLIS);
        $unsampledSpan->end();
        $this->tester->endSpan($root, static::KEPT_SPAN_DURATION_MILLIS);
        $tracerProvider->shutdown();

        $this->assertArrayNotHasKey(static::UNSAMPLED_SPAN_NAME, $exportedParentSpanIds->getArrayCopy());
        $this->assertSame($root->getContext()->getSpanId(), $exportedParentSpanIds['child']);
    }

    /**
     * @param string $spanName
     *
     * @return \OpenTelemetry\SDK\Trace\SamplerInterface
     */
    protected function createSamplerDroppingSpansNamed(string $spanName): SamplerInterface
    {
        return new class ($spanName) implements SamplerInterface {
            /**
             * @param string $droppedSpanName
             */
            public function __construct(protected string $droppedSpanName)
            {
            }

            /**
             * @param \OpenTelemetry\Context\ContextInterface $parentContext
             * @param string $traceId
             * @param string $spanName
             * @param int $spanKind
             * @param \OpenTelemetry\SDK\Common\Attribute\AttributesInterface<string, mixed> $attributes
             * @param array<\OpenTelemetry\SDK\Trace\LinkInterface> $links
             *
             * @return \OpenTelemetry\SDK\Trace\SamplingResult
             */
            public function shouldSample(
                ContextInterface $parentContext,
                string $traceId,
                string $spanName,
                int $spanKind,
                AttributesInterface $attributes,
                array $links,
            ): SamplingResult {
                return new SamplingResult($spanName === $this->droppedSpanName ? SamplingResult::DROP : SamplingResult::RECORD_AND_SAMPLE);
            }

            /**
             * @return string
             */
            public function getDescription(): string
            {
                return 'DropByNameSampler';
            }
        };
    }
}
