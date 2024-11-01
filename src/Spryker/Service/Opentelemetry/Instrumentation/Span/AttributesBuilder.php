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

class AttributesBuilder implements AttributesBuilderInterface
{
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
        if (!in_array($offset, $this->getSafeAttributes()) && !$this->attributeValidator->validate($value)) {
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
            'query',
            TraceAttributes::HTTP_REQUEST_METHOD,
            'queue.name',
            'queryTime',
            'search.index',
            'search.query',
            'root.url',
            TraceAttributes::URL_DOMAIN,
        ];
    }
}
