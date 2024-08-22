<?php

namespace Spryker\Zed\Opentelemetry\Business\Generator\SpanFilter;

use OpenTelemetry\API\Trace\SpanInterface;
use Spryker\Shared\Config\Config;
use Spryker\Zed\Opentelemetry\Business\Generator\SpanFilter\SpanFilterInterface;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;

class SamplerSpanFilter implements SpanFilterInterface
{
    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface $span
     * @param bool $forceToShow
     *
     * @return \OpenTelemetry\API\Trace\SpanInterface
     */
    public static function filter(SpanInterface $span, bool $forceToShow = false): SpanInterface
    {
        $contextToChange = $span->getContext();
        $isSampled = $contextToChange->isSampled();
        $thresholdNanos = OpentelemetryConfig::THRESHOLD_NANOS;
        $reflectionProperty = new \ReflectionProperty($contextToChange, 'isSampled');
        $shouldBeSampled = $forceToShow ||
            (
                $isSampled
                &&
                !(
                    $span->getDuration() < $thresholdNanos
                    && $span->toSpanData()->getParentSpanId()
                    && $span->toSpanData()->getStatus()->getCode() === \OpenTelemetry\API\Trace\StatusCode::STATUS_OK
                )
            );
        $reflectionProperty->setValue($contextToChange, $shouldBeSampled);

        return $span;
    }
}
