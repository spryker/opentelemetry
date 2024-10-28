<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Sampler;

use InvalidArgumentException;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\Span;

class CriticalSpanTraceIdRatioSampler implements SamplerInterface
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
     * @param float $probability Probability float value between 0.0 and 1.0.
     */
    public function __construct(float $probability)
    {
        if ($probability < 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException('probability should be be between 0.0 and 1.0.');
        }
        $this->probability = $probability;
    }

    /**
     * Returns `SamplingResult` based on probability. Respects the parent `SampleFlag`
     * {@inheritdoc}
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
        $parentSpanContext = $parentSpan->getContext();
        $traceState = $parentSpanContext->getTraceState();

        if ($attributes->has(static::IS_CRITICAL_ATTRIBUTE)) {
            return new SamplingResult(SamplingResult::RECORD_AND_SAMPLE, [], $traceState);
        }

        /**
         * Since php can only store up to 63 bit positive integers
         */
        $traceIdLimit = (1 << 60) - 1;
        $lowerOrderBytes = hexdec(substr($traceId, strlen($traceId) - 15, 15));
        $traceIdCondition = $lowerOrderBytes < round($this->probability * $traceIdLimit);
        $decision = $traceIdCondition ? SamplingResult::RECORD_AND_SAMPLE : SamplingResult::DROP;

        return new SamplingResult($decision, [], $traceState);
    }

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

}
