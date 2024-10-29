<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Trace\Link;
use OpenTelemetry\SDK\Trace\LinkInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\TracerSharedState;

class SpanBuilder implements SpanBuilderInterface
{
    private ContextInterface|false|null $parentContext = null;

    /**
     * @psalm-var SpanKind::KIND_*
     */
    private int $spanKind = SpanKind::KIND_INTERNAL;

    /** @var list<LinkInterface> */
    private array $links = [];

    private AttributesBuilderInterface $attributesBuilder;
    private int $totalNumberOfLinksAdded = 0;
    private int $startEpochNanos = 0;

    /** @param non-empty-string $spanName */
    public function __construct(
        private readonly string $spanName,
        private readonly InstrumentationScopeInterface $instrumentationScope,
        private readonly TracerSharedState $tracerSharedState,
    ) {
        $this->attributesBuilder = $this->tracerSharedState->getSpanLimits()->getAttributesFactory()->builder();
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
    public function setAttribute(string $key, mixed $value): SpanBuilderInterface
    {
        $this->attributesBuilder[$key] = $value;

        return $this;
    }

    /** @inheritDoc */
    public function setAttributes(iterable $attributes): SpanBuilderInterface
    {
        foreach ($attributes as $key => $value) {
            $this->attributesBuilder[$key] = $value;
        }

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @psalm-param SpanKind::KIND_* $spanKind
     */
    public function setSpanKind(int $spanKind): SpanBuilderInterface
    {
        $this->spanKind = $spanKind;

        return $this;
    }

    /** @inheritDoc */
    public function setStartTimestamp(int $timestampNanos): SpanBuilderInterface
    {
        if (0 > $timestampNanos) {
            return $this;
        }

        $this->startEpochNanos = $timestampNanos;

        return $this;
    }

    /** @inheritDoc */
    public function startSpan(): \OpenTelemetry\API\Trace\SpanInterface
    {
        $parentContext = Context::resolve($this->parentContext);
        $parentSpan = Span::fromContext($parentContext);
        $parentSpanContext = $parentSpan->getContext();

        $spanId = $this->tracerSharedState->getIdGenerator()->generateSpanId();

        if (!$parentSpanContext->isValid()) {
            $traceId = $this->tracerSharedState->getIdGenerator()->generateTraceId();
        } else {
            $traceId = $parentSpanContext->getTraceId();
        }

        $sampler = $this
            ->tracerSharedState
            ->getSampler();
        $sampler->addParentSpan($parentSpan);
        $samplingResult = $sampler
            ->shouldSample(
                $parentContext,
                $traceId,
                $this->spanName,
                $this->spanKind,
                $this->attributesBuilder->build(),
                $this->links,
            );
        $samplingDecision = $samplingResult->getDecision();
        $samplingResultTraceState = $samplingResult->getTraceState();

        $spanContext = SpanContext::create(
            $traceId,
            $spanId,
            SamplingResult::RECORD_AND_SAMPLE === $samplingDecision ? TraceFlags::SAMPLED : TraceFlags::DEFAULT,
            $samplingResultTraceState,
        );

        if (!in_array($samplingDecision, [SamplingResult::RECORD_AND_SAMPLE, SamplingResult::RECORD_ONLY], true)) {
            return Span::wrap($spanContext);
        }

        $attributesBuilder = clone $this->attributesBuilder;
        foreach ($samplingResult->getAttributes() as $key => $value) {
            $attributesBuilder[$key] = $value;
        }

        return Span::startSpan(
            $this->spanName,
            $spanContext,
            $this->instrumentationScope,
            $this->spanKind,
            $parentSpan,
            $parentContext,
            $this->tracerSharedState->getSpanLimits(),
            $this->tracerSharedState->getSpanProcessor(),
            $this->tracerSharedState->getResource(),
            $attributesBuilder,
            $this->links,
            $this->totalNumberOfLinksAdded,
            $this->startEpochNanos,
        );
    }
}
