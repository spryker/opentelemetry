<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Trace\Link;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\TracerSharedState;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\ParentSpanAwareSamplerInterface;

class SpanBuilder implements SpanBuilderInterface
{
    /**
     * @var \OpenTelemetry\Context\ContextInterface|false|null
     */
    protected ContextInterface|false|null $parentContext = null;

    /**
     * @var int
     */
    protected int $spanKind = SpanKind::KIND_INTERNAL;

    /**
     * @var array<\OpenTelemetry\SDK\Trace\LinkInterface>
     */
    protected array $links = [];

    /**
     * @var \OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface
     */
    protected AttributesBuilderInterface $attributesBuilder;

    /**
     * @var int
     */
    protected int $totalNumberOfLinksAdded = 0;

    /**
     * @var int
     */
    protected int $startEpochNanos = 0;

    /**
     * @var array<string, string>
     */
    protected static $parentMapping = [];

    /**
     * @param string $spanName
     * @param \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface $instrumentationScope
     * @param \OpenTelemetry\SDK\Trace\TracerSharedState $tracerSharedState
     */
    public function __construct(
        protected string $spanName,
        protected InstrumentationScopeInterface $instrumentationScope,
        protected TracerSharedState $tracerSharedState,
    ) {
        $this->attributesBuilder = $this->tracerSharedState->getSpanLimits()->getAttributesFactory()->builder();
    }

    /**
     * @param \OpenTelemetry\Context\ContextInterface|false|null $context
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function setParent(ContextInterface|false|null $context): SpanBuilderInterface
    {
        $this->parentContext = $context;

        return $this;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanContextInterface $context
     * @param iterable $attributes
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function addLink(SpanContextInterface $context, iterable $attributes = []): SpanBuilderInterface
    {
        if (!$context->isValid()) {
            return $this;
        }

        $this->totalNumberOfLinksAdded++;

        if (count($this->links) === $this->tracerSharedState->getSpanLimits()->getLinkCountLimit()) {
            return $this;
        }

        $this->links[] = new Link(
            $context,
            $this->tracerSharedState
                ->getSpanLimits()
                ->getLinkAttributesFactory()
                ->builder($attributes)
                ->build(),
        );

        return $this;
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function setAttribute(string $key, $value): SpanBuilderInterface
    {
        $this->attributesBuilder[$key] = $value;

        return $this;
    }

    /**
     * @param iterable $attributes
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function setAttributes(iterable $attributes): SpanBuilderInterface
    {
        foreach ($attributes as $key => $value) {
            $this->attributesBuilder[$key] = $value;
        }

        return $this;
    }

    /**
     * @param int $spanKind
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function setSpanKind(int $spanKind): SpanBuilderInterface
    {
        $this->spanKind = $spanKind;

        return $this;
    }

    /**
     * @param int $timestampNanos
     *
     * @return \OpenTelemetry\API\Trace\SpanBuilderInterface
     */
    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface
    {
        if ($timestampNanos < 0) {
            return $this;
        }

        $this->startEpochNanos = $timestampNanos;

        return $this;
    }

    /**
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    public function startSpan(): SpanInterface
    {
        $parentContext = Context::resolve($this->parentContext);
        $parentSpan = Span::fromContext($parentContext);
        $parentSpanContext = $parentSpan->getContext();

        $spanId = $this->tracerSharedState->getIdGenerator()->generateSpanId();
        $traceId = $parentSpanContext->isValid() ? $parentSpanContext->getTraceId() : $this->tracerSharedState->getIdGenerator()->generateTraceId();

        $samplingResult = $this->getSamplingResult($parentSpan, $parentContext, $traceId);
        $samplingDecision = $samplingResult->getDecision();
        $samplingResultTraceState = $samplingResult->getTraceState();

        $file = fopen(APPLICATION_ROOT_DIR . '/span' . $traceId, 'a');
        fwrite($file, $this->spanName . PHP_EOL);
        fclose($file);
        $spanContext = SpanContext::create(
            $traceId,
            $spanId,
            $samplingDecision === SamplingResult::RECORD_AND_SAMPLE ? TraceFlags::SAMPLED : TraceFlags::DEFAULT,
            $samplingResultTraceState,
        );

        if (!in_array($samplingDecision, [SamplingResult::RECORD_AND_SAMPLE, SamplingResult::RECORD_ONLY], true)) {
            static::$parentMapping[$spanId] = $parentSpanContext->getSpanId();

            if ($spanContext instanceof SpanIdUpdateAwareSpanContextInterface) {
                $spanContext->updateSpanId($parentSpanContext->getSpanId());
            }

            return Span::wrap($spanContext);
        }

        if (isset(static::$parentMapping[$parentSpanContext->getSpanId()]) && $parentSpanContext instanceof SpanIdUpdateAwareSpanContextInterface) {
            $parentSpanContext->updateSpanId(static::$parentMapping[$parentSpanContext->getSpanId()]);
        }

        return Span::startSpan(
            $this->spanName,
            $spanContext,
            $this->instrumentationScope,
            $this->spanKind,
            $parentSpanContext,
            $parentContext,
            $this->tracerSharedState->getSpanLimits(),
            $this->tracerSharedState->getSpanProcessor(),
            $this->tracerSharedState->getResource(),
            $this->attributesBuilder,
            $this->links,
            $this->totalNumberOfLinksAdded,
            $this->startEpochNanos,
        );
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $parentSpan
     * @param \OpenTelemetry\Context\ContextInterface $parentContext
     * @param string $traceId
     *
     * @return \OpenTelemetry\SDK\Trace\SamplingResult
     */
    protected function getSamplingResult(SpanInterface $parentSpan, ContextInterface $parentContext, string $traceId): SamplingResult
    {
        $sampler = $this
            ->tracerSharedState
            ->getSampler();

        if ($sampler instanceof ParentSpanAwareSamplerInterface) {
            $sampler->addParentSpan($parentSpan);;
        }

        return $sampler
            ->shouldSample(
                $parentContext,
                $traceId,
                $this->spanName,
                $this->spanKind,
                $this->attributesBuilder->build(),
                $this->links,
            );
    }
}
