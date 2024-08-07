<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation as OpenTelemetryCachedInstrumentation;

class CachedInstrumentation
{
    /**
     * @var string
     */
    protected const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.spryker';

    /**
     * @var string
     */
    protected const INSTRUMENTATION_VERSION = '1.0.0';

    /**
     * @var \OpenTelemetry\API\Instrumentation\CachedInstrumentation|null
     */
    protected static ?OpenTelemetryCachedInstrumentation $instrumentation = null;

    /**
     * @return void
     */
    public static function setCachedInstrumentation(): void
    {
        static::$instrumentation = (new OpenTelemetryCachedInstrumentation(static::INSTRUMENTATION_NAME, static::INSTRUMENTATION_VERSION));
    }

    /**
     * @return \OpenTelemetry\API\Instrumentation\CachedInstrumentation
     */
    public static function getCachedInstrumentation(): OpenTelemetryCachedInstrumentation
    {
        if (static::$instrumentation === null) {
            static::setCachedInstrumentation();
        }

        return static::$instrumentation;
    }
}
