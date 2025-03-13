<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry;

/**
 * Declares global environment configuration keys. Do not use it for other class constants.
 */
interface OpentelemetryConstants
{
    /**
     * Specification:
     * - Defines a HTTP root span name if no route was provided.
     *
     * @api
     *
     * @var string
     */
    public const FALLBACK_HTTP_ROOT_SPAN_NAME = 'OPENTELEMETRY:FALLBACK_HTTP_ROOT_SPAN_NAME';
}
