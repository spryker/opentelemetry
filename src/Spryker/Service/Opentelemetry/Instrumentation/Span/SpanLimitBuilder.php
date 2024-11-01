<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\SDK\Common\Attribute\FilteredAttributesFactory;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables as Env;
use OpenTelemetry\SDK\Trace\SpanLimits;
use OpenTelemetry\SemConv\TraceAttributes;

class SpanLimitBuilder
{
    /**
     * @var int|null
     */
    protected ?int $attributeCountLimit = null;

    /**
     * @var int|null
     */
    protected ?int $attributeValueLengthLimit = null;

    /**
     * @var int|null
     */
    protected ?int $eventCountLimit = null;

    /**
     * @var int|null
     */
    protected ?int $linkCountLimit = null;

    /**
     * @var int|null
     */
    protected ?int $attributePerEventCountLimit = null;

    /**
     * @var int|null
     */
    protected ?int $attributePerLinkCountLimit = null;

    /**
     * @param int $attributeCountLimit
     *
     * @return $this
     */
    public function setAttributeCountLimit(int $attributeCountLimit)
    {
        $this->attributeCountLimit = $attributeCountLimit;

        return $this;
    }

    /**
     * @param int $attributeValueLengthLimit
     *
     * @return $this
     */
    public function setAttributeValueLengthLimit(int $attributeValueLengthLimit)
    {
        $this->attributeValueLengthLimit = $attributeValueLengthLimit;

        return $this;
    }

    /**
     * @param int $eventCountLimit
     *
     * @return $this
     */
    public function setEventCountLimit(int $eventCountLimit)
    {
        $this->eventCountLimit = $eventCountLimit;

        return $this;
    }

    /**
     * @param int $linkCountLimit
     *
     * @return $this
     */
    public function setLinkCountLimit(int $linkCountLimit)
    {
        $this->linkCountLimit = $linkCountLimit;

        return $this;
    }

    /**
     * @param int $attributePerEventCountLimit
     * @return $this
     */
    public function setAttributePerEventCountLimit(int $attributePerEventCountLimit)
    {
        $this->attributePerEventCountLimit = $attributePerEventCountLimit;

        return $this;
    }

    /**
     * @param int $attributePerLinkCountLimit
     *
     * @return $this
     */
    public function setAttributePerLinkCountLimit(int $attributePerLinkCountLimit)
    {
        $this->attributePerLinkCountLimit = $attributePerLinkCountLimit;

        return $this;
    }

    /**
     * @param bool $retain
     *
     * @return $this
     */
    public function retainGeneralIdentityAttributes(bool $retain = true)
    {
        $this->retainGeneralIdentityAttributes = $retain;

        return $this;
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\SpanLimits
     */
    public function build(): SpanLimits
    {
        $attributeCountLimit = $this->attributeCountLimit
            ?: Configuration::getInt(Env::OTEL_SPAN_ATTRIBUTE_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_ATTRIBUTE_COUNT_LIMIT);
        $attributeValueLengthLimit = $this->attributeValueLengthLimit
            ?: Configuration::getInt(Env::OTEL_SPAN_ATTRIBUTE_VALUE_LENGTH_LIMIT, SpanLimits::DEFAULT_SPAN_ATTRIBUTE_LENGTH_LIMIT);
        $eventCountLimit = $this->eventCountLimit
            ?: Configuration::getInt(Env::OTEL_SPAN_EVENT_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_EVENT_COUNT_LIMIT);
        $linkCountLimit = $this->linkCountLimit
            ?: Configuration::getInt(Env::OTEL_SPAN_LINK_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_LINK_COUNT_LIMIT);
        $attributePerEventCountLimit = $this->attributePerEventCountLimit
            ?: Configuration::getInt(Env::OTEL_EVENT_ATTRIBUTE_COUNT_LIMIT, SpanLimits::DEFAULT_EVENT_ATTRIBUTE_COUNT_LIMIT);
        $attributePerLinkCountLimit = $this->attributePerLinkCountLimit
            ?: Configuration::getInt(Env::OTEL_LINK_ATTRIBUTE_COUNT_LIMIT, SpanLimits::DEFAULT_LINK_ATTRIBUTE_COUNT_LIMIT);

        if ($attributeValueLengthLimit === PHP_INT_MAX) {
            $attributeValueLengthLimit = null;
        }

        $spanAttributesFactory = new FilteredAttributesFactory(Attributes::factory($attributeCountLimit, $attributeValueLengthLimit), [
            TraceAttributes::USER_ID,
            TraceAttributes::USER_ROLES,
        ]);

        return new SpanLimits(
            $spanAttributesFactory,
            Attributes::factory($attributePerEventCountLimit, $attributeValueLengthLimit),
            Attributes::factory($attributePerLinkCountLimit, $attributeValueLengthLimit),
            $eventCountLimit,
            $linkCountLimit,
        );
    }
}
