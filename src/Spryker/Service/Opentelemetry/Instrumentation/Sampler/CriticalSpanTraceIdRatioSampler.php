<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Sampler;

use InvalidArgumentException;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TraceStateInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;

class CriticalSpanTraceIdRatioSampler implements SamplerInterface, TraceStateAwareSamplerInterface
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
     * @var float
     */
    protected float $criticalProbability;

    protected ?SpanInterface $parentSpan = null;

    /**
     * @param float $probability
     */
    public function __construct(float $probability, float $criticalProbability)
    {
        $this->setProbability($probability);
        $this->setCriticalProbability($criticalProbability);
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
        if (!$this->parentSpan->getContext()->isValid()) {
            return new SamplingResult(SamplingResult::RECORD_AND_SAMPLE, [], $this->parentSpan->getContext()->getTraceState());
        }
        $probability = $this->probability;
        if ($attributes->has(static::IS_CRITICAL_ATTRIBUTE)) {
            $probability = $this->criticalProbability;
        }

//        $traceIdLimit = (1 << 60) - 1;
//        $lowerOrderBytes = hexdec(substr($traceId, strlen($traceId) - 15, 15));
//        $traceIdCondition = $lowerOrderBytes < round($probability * $traceIdLimit);
//        if (!$traceIdCondition && $spanName !== 'Backoffice GET http://backoffice.de.spryker.local/') {
//            var_dump($traceId,$traceIdCondition, $lowerOrderBytes, round($probability * $traceIdLimit), $traceIdLimit, $spanName);die;
//        }

        $traceIdCondition = (mt_rand() / mt_getrandmax()) <= $probability;
        //var_dump($traceIdCondition, $probability);die;
        $decision = $traceIdCondition ? SamplingResult::RECORD_AND_SAMPLE : SamplingResult::DROP;

        return new SamplingResult($decision, [], $this->parentSpan->getContext()->getTraceState());
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $parentSpan
     *
     * @return void
     */
    public function addParentSpan(SpanInterface $parentSpan): void
    {
        $this->parentSpan = $parentSpan;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return sprintf('%s{%.6F}', 'CriticalSpanTraceIdRatioSampler', $this->probability);
    }

    /**
     * @param float $probability
     *
     * @return void
     */
    protected function setProbability(float $probability): void
    {
        $this->validateProbability($probability);

        $this->probability = $probability;
    }

    /**
     * @param float $probability
     *
     * @return void
     */
    protected function setCriticalProbability(float $probability): void
    {
        $this->validateProbability($probability);

        $this->criticalProbability = $probability;
    }

    /**
     * @param float $probability
     *
     * @return void
     */
    protected function validateProbability(float $probability): void
    {
        if ($probability < 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException('probability should be be between 0.0 and 1.0.');
        }
    }
}
