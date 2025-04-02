<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributeValidator;
use OpenTelemetry\SDK\Common\Attribute\AttributeValidatorInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\ElasticaInstrumentation;
use Spryker\Service\Opentelemetry\Instrumentation\RedisInstrumentation;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;

class AttributesBuilder implements AttributesBuilderInterface
{
    /**
     * @param array $attributes
     * @param int|null $attributeCountLimit
     * @param int|null $attributeValueLengthLimit
     * @param int $droppedAttributesCount
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributeValidatorInterface $attributeValidator
     */
    public function __construct(
        protected array $attributes,
        protected ?int $attributeCountLimit,
        protected ?int $attributeValueLengthLimit,
        protected int $droppedAttributesCount,
        protected AttributeValidatorInterface $attributeValidator = new AttributeValidator()
    ){}

    /**
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesInterface
     */
    public function build(): AttributesInterface
    {
        return new Attributes($this->attributes, $this->droppedAttributesCount);
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributesInterface $old
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributesInterface $updating
     *
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesInterface
     */
    public function merge(AttributesInterface $old, AttributesInterface $updating): AttributesInterface
    {
        $new = $old->toArray();
        $dropped = $old->getDroppedAttributesCount() + $updating->getDroppedAttributesCount();
        foreach ($updating->toArray() as $key => $value) {
            if (count($new) === $this->attributeCountLimit && !array_key_exists($key, $new)) {
                $dropped++;
            } else {
                $new[$key] = $value;
            }
        }

        return new Attributes($new, $dropped);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            return;
        }
        if ($value === null) {
            unset($this->attributes[$offset]);

            return;
        }
        if (!in_array($offset, $this->getSafeAttributes(), true) && !$this->attributeValidator->validate($value)) {
            $this->droppedAttributesCount++;

            return;
        }
        if (count($this->attributes) === $this->attributeCountLimit && !array_key_exists($offset, $this->attributes)) {
            $this->droppedAttributesCount++;

            return;
        }

        $this->attributes[$offset] = $value;
    }

    /**
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * @return array<string>
     */
    protected function getSafeAttributes(): array
    {
        return [
            TraceAttributes::CODE_NAMESPACE,
            TraceAttributes::CODE_FILEPATH,
            TraceAttributes::CODE_LINENO,
            TraceAttributes::CODE_FUNCTION,
            TraceAttributes::URL_QUERY,
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE,
            TraceAttributes::HTTP_REQUEST_METHOD,
            TraceAttributes::URL_FULL,
            TraceAttributes::DB_QUERY_TEXT,
            TraceAttributes::DB_SYSTEM_NAME,
            TraceAttributes::MESSAGING_DESTINATION_NAME,
            TraceAttributes::USER_AGENT_ORIGINAL,
            TraceAttributes::NETWORK_PROTOCOL_VERSION,
            TraceAttributes::SERVER_PORT,
            TraceAttributes::SERVER_ADDRESS,
            TraceAttributes::ERROR_TYPE,
            TraceAttributes::EXCEPTION_MESSAGE,
            TraceAttributes::URL_DOMAIN,
            RedisInstrumentation::ATTRIBUTE_EVAL_SCRIPT,
            ElasticaInstrumentation::ATTRIBUTE_QUERY_TIME,
            ElasticaInstrumentation::ATTRIBUTE_SEARCH_INDEX,
            ElasticaInstrumentation::ATTRIBUTE_SEARCH_ID,
            ElasticaInstrumentation::ATTRIBUTE_SEARCH_IDS,
            ElasticaInstrumentation::ATTRIBUTE_SEARCH_INDEXES,
            CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE,
            CriticalSpanRatioSampler::NO_CRITICAL_ATTRIBUTE,
            SprykerInstrumentationBootstrap::ATTRIBUTE_IS_DETAILED_TRACE,
            TraceAttributes::DB_OPERATION_NAME,
            TraceAttributes::DB_COLLECTION_NAME,
            TraceAttributes::DB_QUERY_SUMMARY,
            TraceAttributes::DB_RESPONSE_RETURNED_ROWS,
        ];
    }
}
