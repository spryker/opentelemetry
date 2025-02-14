<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation as OpenTelemetryCachedInstrumentation;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;

class CachedInstrumentation implements CachedInstrumentationInterface
{
    /**
     * @var string
     */
    protected const INSTRUMENTATION_NAME = 'io.opentelemetry.contrib.php.spryker';

    /**
     * @var \OpenTelemetry\API\Instrumentation\CachedInstrumentation|null
     */
    protected static ?OpenTelemetryCachedInstrumentation $instrumentation = null;

    /**
     * @return \OpenTelemetry\API\Instrumentation\CachedInstrumentation|null
     */
    public static function getCachedInstrumentation(): ?OpenTelemetryCachedInstrumentation
    {
        if (static::$instrumentation === null) {
            static::setCachedInstrumentation();
        }

        return static::$instrumentation;
    }

    /**
     * @return void
     */
    protected static function setCachedInstrumentation(): void
    {
        static::$instrumentation = (new OpenTelemetryCachedInstrumentation(
            static::INSTRUMENTATION_NAME,
            SprykerInstrumentationBootstrap::INSTRUMENTATION_VERSION,
        ));
    }
}
