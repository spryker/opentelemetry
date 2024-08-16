<?php

namespace Spryker\Service\Opentelemetry\Storage;

class AttributesStorage implements AttributesStorageInterface
{
    private static ?AttributesStorage $instance = null;

    /**
     * @var array
     */
    protected array $attributes = [];

    public static function getInstance(): AttributesStorage
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @return array<mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
