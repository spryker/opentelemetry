<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Sampler;

use InvalidArgumentException;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;

class CriticalSpanRatioSampler implements SamplerInterface, ParentSpanAwareSamplerInterface
{
    /**
     * @var string
     */
    public const IS_CRITICAL_ATTRIBUTE = 'is_critical';

    /**
     * @var string
     */
    public const NO_CRITICAL_ATTRIBUTE = 'no_critical';

    /**
     * @var string
     */
    public const IS_SYSTEM_ATTRIBUTE = 'spr.is_system_span';

    /**
     * @var float
     */
    protected float $probability;

    /**
     * @var float
     */
    protected float $criticalProbability;

    /**
     * @var float
     */
    protected float $nonCriticalProbability;

    protected ?SpanInterface $parentSpan = null;

    /**
     * @param float $probability
     */
    public function __construct(float $probability, float $criticalProbability, float $nonCriticalProbability)
    {
        $this->setProbability($probability);
        $this->setCriticalProbability($criticalProbability);
        $this->setNonCriticalProbability($nonCriticalProbability);
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
        //Root span nd system span MUST be sampled always
        if (!$this->parentSpan->getContext()->isValid() || $attributes->has(static::IS_SYSTEM_ATTRIBUTE)) {
            return new SamplingResult(SamplingResult::RECORD_AND_SAMPLE, [], $this->parentSpan->getContext()->getTraceState());
        }
        $probability = $this->probability;
        if ($attributes->has(static::IS_CRITICAL_ATTRIBUTE)) {
            $probability = $this->criticalProbability;
        }

        if ($attributes->has(static::NO_CRITICAL_ATTRIBUTE)) {
            $probability = $this->nonCriticalProbability;
        }

        $result = mt_rand() / mt_getrandmax();
        $traceIdCondition = $result <= $probability;
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
        return sprintf('%s{%.6F}', 'CriticalSpanRatioSampler', $this->probability);
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
    protected function setNonCriticalProbability(float $probability): void
    {
        $this->validateProbability($probability);

        $this->nonCriticalProbability = $probability;
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
