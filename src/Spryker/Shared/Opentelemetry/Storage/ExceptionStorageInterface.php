<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Storage;

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
