<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use IteratorAggregate;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactoryInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use Traversable;

class Attributes implements AttributesInterface, IteratorAggregate, WritableAttributesInterface
{
    /**
     * @param array $attributes
     * @param int $droppedAttributesCount
     */
    public function __construct(
        protected array $attributes,
        protected int $droppedAttributesCount,
    ) {
    }

    /**
     * @param iterable $attributes
     *
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesInterface
     */
    public static function create(iterable $attributes): AttributesInterface
    {
        return self::factory()->builder($attributes)->build();
    }

    /**
     * @param int|null $attributeCountLimit
     * @param int|null $attributeValueLengthLimit
     *
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesFactoryInterface
     */
    public static function factory(?int $attributeCountLimit = null, ?int $attributeValueLengthLimit = null): AttributesFactoryInterface
    {
        return new AttributesFactory($attributeCountLimit, $attributeValueLengthLimit);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->attributes);
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): Traversable
    {
        foreach ($this->attributes as $key => $value) {
            yield (string) $key => $value;
        }
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return int
     */
    public function getDroppedAttributesCount(): int
    {
        return $this->droppedAttributesCount;
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function set(string $key, $value): void
    {
        if ($value === null) {
            unset($this->attributes[$key]);
        }

        $this->attributes[$key] = $value;
    }
}
