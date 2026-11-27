<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Service\Opentelemetry;

use ArrayObject;
use Codeception\Actor;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use ReflectionProperty;
use Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfoFactory;
use Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor\PostFilterBatchSpanProcessor;
use Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProvider;

/**
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method \Codeception\Lib\Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(\PHPMD\PHPMD)
 */
class OpentelemetryServiceTester extends Actor
{
    use _generated\OpentelemetryServiceTesterActions;

    /**
     * @var int
     */
    public const START_EPOCH_NANOS = 1_000_000_000_000;

    /**
     * @var int
     */
    public const NANOS_IN_MILLISECOND = 1_000_000;

    /**
     * @var list<array{string, string}>
     */
    protected const RESET_STATIC_PROPERTIES = [
        [TracerProvider::class, 'tracer'],
        [PostFilterBatchSpanProcessor::class, 'parentSpanIdMapping'],
        [PostFilterBatchSpanProcessor::class, 'spanIdsWithKeptDescendants'],
        [PostFilterBatchSpanProcessor::class, 'processedSpans'],
    ];

    /**
     * @return void
     */
    public function resetStaticState(): void
    {
        foreach (static::RESET_STATIC_PROPERTIES as [$className, $propertyName]) {
            if (!property_exists($className, $propertyName)) {
                continue;
            }

            $reflectionProperty = new ReflectionProperty($className, $propertyName);
            $defaultValue = $reflectionProperty->getDeclaringClass()->getDefaultProperties()[$propertyName] ?? null;
            $reflectionProperty->setValue(null, $defaultValue);
        }
    }

    /**
     * @param string $className
     * @param string $propertyName
     *
     * @return int
     */
    public function countStaticArrayProperty(string $className, string $propertyName): int
    {
        return count((new ReflectionProperty($className, $propertyName))->getValue(null));
    }

    /**
     * @param \OpenTelemetry\SDK\Trace\SpanProcessorInterface $spanProcessor
     * @param \OpenTelemetry\SDK\Trace\SamplerInterface|null $sampler
     *
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Tracer\TracerProvider
     */
    public function createTracerProvider(SpanProcessorInterface $spanProcessor, ?SamplerInterface $sampler = null): TracerProvider
    {
        return new TracerProvider($spanProcessor, $sampler ?? new AlwaysOnSampler(), ResourceInfoFactory::emptyResource());
    }

    /**
     * @param \OpenTelemetry\API\Trace\TracerInterface $tracer
     * @param string $spanName
     * @param \OpenTelemetry\API\Trace\SpanInterface|null $parentSpan
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    public function startSpan(TracerInterface $tracer, string $spanName, ?SpanInterface $parentSpan = null): SpanInterface
    {
        return $tracer->spanBuilder($spanName)
            ->setStartTimestamp(static::START_EPOCH_NANOS)
            ->setParent($parentSpan === null ? false : $parentSpan->storeInContext(Context::getCurrent()))
            ->startSpan();
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param int $durationMillis
     *
     * @return void
     */
    public function endSpan(SpanInterface $span, int $durationMillis): void
    {
        $span->end(static::START_EPOCH_NANOS + $durationMillis * static::NANOS_IN_MILLISECOND);
    }

    /**
     * Captures the parent span id of every span at the moment it is exported, the same way the OTLP span converter reads it.
     *
     * @param \ArrayObject<string, string|null> $exportedParentSpanIds Span name => parent span id (null when the parent context is invalid).
     *
     * @return \OpenTelemetry\SDK\Trace\SpanExporterInterface
     */
    public function createParentSpanIdCapturingExporter(ArrayObject $exportedParentSpanIds): SpanExporterInterface
    {
        return new class ($exportedParentSpanIds) implements SpanExporterInterface {
            /**
             * @param \ArrayObject<string, string|null> $exportedParentSpanIds
             */
            public function __construct(protected ArrayObject $exportedParentSpanIds)
            {
            }

            /**
             * @param iterable<\OpenTelemetry\SDK\Trace\ReadableSpanInterface> $batch
             * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
             *
             * @return \OpenTelemetry\SDK\Common\Future\FutureInterface<bool>
             */
            public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
            {
                foreach ($batch as $span) {
                    $parentSpanContext = $span->getParentContext();
                    $this->exportedParentSpanIds[$span->getName()] = $parentSpanContext->isValid() ? $parentSpanContext->getSpanId() : null;
                }

                return new CompletedFuture(true);
            }

            /**
             * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
             *
             * @return bool
             */
            public function shutdown(?CancellationInterface $cancellation = null): bool
            {
                return true;
            }

            /**
             * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
             *
             * @return bool
             */
            public function forceFlush(?CancellationInterface $cancellation = null): bool
            {
                return true;
            }
        };
    }
}
