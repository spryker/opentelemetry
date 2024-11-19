<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use IteratorAggregate;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactoryInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use Traversable;

class Attributes implements AttributesInterface, IteratorAggregate
{
    /**
     * @internal
     */
    public function __construct(
        protected readonly array $attributes,
        protected readonly int $droppedAttributesCount,
    ) {
    }

    public static function create(iterable $attributes): AttributesInterface
    {
        return self::factory()->builder($attributes)->build();
    }

    public static function factory(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): AttributesFactoryInterface
    {
        return new AttributesFactory($attributeCountLimit, $attributeValueLengthLimit);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    public function get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /** @psalm-mutation-free */
    public function count(): int
    {
        return count($this->attributes);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->attributes as $key => $value) {
            yield (string) $key => $value;
        }
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function getDroppedAttributesCount(): int
    {
        return $this->droppedAttributesCount;
    }
}
