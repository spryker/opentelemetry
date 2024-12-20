<?php

namespace Spryker\Service\Opentelemetry\Storage;

use Throwable;

interface ExceptionStorageInterface
{
    /**
     * @return \Spryker\Service\Opentelemetry\Storage\ExceptionStorageInterface
     */
    public static function getInstance(): ExceptionStorageInterface;

    /**
     * @return array<\Throwable>
     */
    public function getExceptions(): array;

    /**
     * @param \Throwable $exception
     *
     * @return void
     */
    public function addException(Throwable $exception): void;
}
