<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\SpanFilter;

use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use ReflectionProperty;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;

class SamplerSpanFilter implements SpanFilterInterface
{
    /**
     * @param \OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     * @param bool $forceToShow
     *
     * @return \OpenTelemetry\SDK\Trace\ReadableSpanInterface
     */
    public static function filter(ReadableSpanInterface $span, bool $forceToShow = false): ReadableSpanInterface
    {
        $contextToChange = $span->getContext();
        $isSampled = $contextToChange->isSampled();
        $thresholdNanos = OpentelemetryConfig::THRESHOLD_NANOS;
        $reflectionProperty = new ReflectionProperty($contextToChange, 'isSampled');
        $shouldBeSampled = $forceToShow ||
            (
                $isSampled
                &&
                !(
                    $span->getDuration() < $thresholdNanos
                    && $span->toSpanData()->getParentSpanId()
                    && $span->toSpanData()->getStatus()->getCode() === StatusCode::STATUS_OK
                )
            );
        $reflectionProperty->setValue($contextToChange, $shouldBeSampled);

        return $span;
    }
}
