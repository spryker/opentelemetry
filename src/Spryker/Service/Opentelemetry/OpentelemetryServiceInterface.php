<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry;

interface OpentelemetryServiceInterface
{
    /**
     * Specification:
     * - Sets custom parameter from Monitoring service to an internal storage.
     * - Params will be attached to the root span of the current trace.
     *
     * @api
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setCustomParameter(string $key, $value): void;

    /**
     * Specification:
     * - Sets root span name that will be shown in the list of all transactions
     *
     * @api
     *
     * @param string $name
     *
     * @return void
     */
    public function setRootSpanName(string $name): void;

    /**
     * Specification:
     * - Sets service name to the root span.
     *
     * @api
     *
     * @param string $name
     *
     * @return void
     */
    public function setResourceName(string $name): void;

    /**
     * Specification:
     * - Adds error event to the root span.
     *
     * @api
     *
     * @param string $message
     * @param \Throwable $exception
     *
     * @return void
     */
    public function setError(string $message, \Throwable $exception): void;
}
