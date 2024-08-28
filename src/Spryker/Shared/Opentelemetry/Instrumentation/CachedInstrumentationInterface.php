<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation as OpenTelemetryCachedInstrumentation;

interface CachedInstrumentationInterface
{
    /**
     * @return \OpenTelemetry\API\Instrumentation\CachedInstrumentation|null
     */
    public static function getCachedInstrumentation(): ?OpenTelemetryCachedInstrumentation;
}
