<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Sampler;

use InvalidArgumentException;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\Span;

class CriticalSpanTraceIdRatioSampler implements SamplerInterface, ParentSpanAwareSamplerInterface
{
    /**
     * @var string
     */
    public const IS_CRITICAL_ATTRIBUTE = 'is_critical';

    /**
     * @var float
     */
    protected float $probability;

    /**
     * @var \OpenTelemetry\API\Trace\SpanInterface|null
     */
    protected ?SpanInterface $parentSpan = null;

    /**
     * @var \OpenTelemetry\API\Trace\SpanContextInterface|null
     */
    protected ?SpanContextInterface $parentSpanContext = null;

    /**
     * @param float $probability
     */
    public function __construct(float $probability)
    {
        if ($probability < 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException('probability should be be between 0.0 and 1.0.');
        }
        $this->probability = $probability;
    }

    /**
     * @param \OpenTelemetry\Context\ContextInterface $parentContext
     * @param string $traceId
     * @param string $spanName
     * @param int $spanKind
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributesInterface $attributes
     * @param array $links
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
        $parentSpan = $this->parentSpan ?: Span::fromContext($parentContext);
        $parentSpanContext = $this->parentSpanContext ?: $parentSpan->getContext();
        $traceState = $parentSpanContext->getTraceState();

        if ($attributes->has(static::IS_CRITICAL_ATTRIBUTE)) {
            return new SamplingResult(SamplingResult::RECORD_AND_SAMPLE, [], $traceState);
        }

        $traceIdLimit = (1 << 60) - 1;
        $lowerOrderBytes = hexdec(substr($traceId, strlen($traceId) - 15, 15));
        $traceIdCondition = $lowerOrderBytes < round($this->probability * $traceIdLimit);
        $decision = $traceIdCondition ? SamplingResult::RECORD_AND_SAMPLE : SamplingResult::DROP;

        return new SamplingResult($decision, [], $traceState);
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     *
     * @return void
     */
    public function addParentSpan(SpanInterface $span): void
    {
        $this->parentSpan = $span;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return sprintf('%s{%.6F}', 'CriticalSpanTraceIdRatioSampler', $this->probability);
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanContextInterface $spanContext
     *
     * @return void
     */
    public function addParentSpanContext(SpanContextInterface $spanContext): void
    {
        $this->parentSpanContext = $spanContext;
    }
}
