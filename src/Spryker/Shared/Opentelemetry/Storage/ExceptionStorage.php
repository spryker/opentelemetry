<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Storage;

use Throwable;

class ExceptionStorage implements ExceptionStorageInterface
{
    /**
     * @var \Spryker\Service\Opentelemetry\Storage\ExceptionStorageInterface|null
     */
    private static ?ExceptionStorageInterface $instance = null;

    /**
     * @var array<\Throwable>
     */
    protected array $exceptions = [];

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\ExceptionStorageInterface
     */
    public static function getInstance(): ExceptionStorageInterface
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @return array<\Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }

    /**
     * @param \Throwable $exception
     *
     * @return void
     */
    public function addException(Throwable $exception): void
    {
        $this->exceptions[] = $exception;
    }
}
