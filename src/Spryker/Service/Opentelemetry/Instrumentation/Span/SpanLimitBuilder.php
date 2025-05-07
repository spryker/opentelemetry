<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables as Env;
use OpenTelemetry\SDK\Trace\SpanLimits;

class SpanLimitBuilder
{
    /**
     * @return \OpenTelemetry\SDK\Trace\SpanLimits
     */
    public function build(): SpanLimits
    {
        $attributeCountLimit = Configuration::getInt(Env::OTEL_SPAN_ATTRIBUTE_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_ATTRIBUTE_COUNT_LIMIT);
        $attributeValueLengthLimit = Configuration::getInt(Env::OTEL_SPAN_ATTRIBUTE_VALUE_LENGTH_LIMIT, SpanLimits::DEFAULT_SPAN_ATTRIBUTE_LENGTH_LIMIT);
        $eventCountLimit = Configuration::getInt(Env::OTEL_SPAN_EVENT_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_EVENT_COUNT_LIMIT);
        $linkCountLimit = Configuration::getInt(Env::OTEL_SPAN_LINK_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_LINK_COUNT_LIMIT);
        $attributePerEventCountLimit = Configuration::getInt(Env::OTEL_EVENT_ATTRIBUTE_COUNT_LIMIT, SpanLimits::DEFAULT_EVENT_ATTRIBUTE_COUNT_LIMIT);
        $attributePerLinkCountLimit = Configuration::getInt(Env::OTEL_LINK_ATTRIBUTE_COUNT_LIMIT, SpanLimits::DEFAULT_LINK_ATTRIBUTE_COUNT_LIMIT);

        if ($attributeValueLengthLimit === PHP_INT_MAX) {
            $attributeValueLengthLimit = null;
        }

        return new SpanLimits(
            Attributes::factory($attributeCountLimit, $attributeValueLengthLimit),
            Attributes::factory($attributePerEventCountLimit, $attributeValueLengthLimit),
            Attributes::factory($attributePerLinkCountLimit, $attributeValueLengthLimit),
            $eventCountLimit,
            $linkCountLimit,
        );
    }
}
