<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributeValidator;
use OpenTelemetry\SDK\Common\Attribute\AttributeValidatorInterface;
use OpenTelemetry\SemConv\TraceAttributes;

class AttributesBuilder implements AttributesBuilderInterface
{
    public function __construct(private array $attributes, private ?int $attributeCountLimit, private ?int $attributeValueLengthLimit, private int $droppedAttributesCount, private AttributeValidatorInterface $attributeValidator = new AttributeValidator())
    {
    }

    public function build(): AttributesInterface
    {
        return new Attributes($this->attributes, $this->droppedAttributesCount);
    }

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

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    /**
     * @phan-suppress PhanUndeclaredClassAttribute
     */
    public function offsetGet($offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * @phan-suppress PhanUndeclaredClassAttribute
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
     * @phan-suppress PhanUndeclaredClassAttribute
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * @return array
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
