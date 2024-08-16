<?php

namespace Spryker\Service\Opentelemetry\Storage;

interface AttributesStorageInterface
{
    public static function getInstance(): AttributesStorage;

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setAttribute(string $key, $value): void;

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getAttribute(string $key);

    /**
     * @return array
     */
    public function getAttributes(): array;
}
