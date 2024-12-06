<?php

namespace Spryker\Service\Opentelemetry\Storage;

interface ResourceNameStorageInterface
{
    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void;

    /**
     * @return ?string
     */
    public function getName(): ?string;
}
