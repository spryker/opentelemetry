<?php

namespace Spryker\Service\Opentelemetry\Storage;

class ResourceNameStorage implements ResourceNameStorageInterface
{
    private static ?ResourceNameStorage $instance = null;

    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\ResourceNameStorage
     */
    public static function getInstance(): ResourceNameStorage
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
