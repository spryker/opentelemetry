<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business;

interface OpenTelemetryFacadeInterface
{
    /**
     * Specification:
     * - Generates hooks for OpenTelemetry.
     *
     * @api
     *
     * @return void
     */
    public function generateHooks(): void;

    /**
     * Specification:
     * - Deletes hooks for OpenTelemetry.
     *
     * @api
     *
     * @return void
     */
    public function deleteHooks(): void;
}
