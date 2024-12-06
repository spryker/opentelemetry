<?php

namespace Spryker\Service\Opentelemetry\Storage;

interface RootSpanNameStorageInterface
{
    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void;

    /**
     * @return string|null
     */
    public function getName(): ?string;
}
