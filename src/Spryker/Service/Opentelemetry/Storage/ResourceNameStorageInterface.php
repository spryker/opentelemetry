<?php

namespace Spryker\Service\Opentelemetry\Storage;

interface ResourceNameStorageInterface
{
    public function setName(string $name): void;

    /**
     * @return ?string
     */
    public function getName(): ?string;
}
