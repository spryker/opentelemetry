<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use OpenTelemetry\SDK\Registry;
use Spryker\Service\Opentelemetry\Instrumentation\ResourceDetector\CloudResourceDetector;

/**
 * @method \Spryker\Service\Opentelemetry\OpentelemetryServiceFactory getFactory()
 */
class SprykerInstrumentationBootstrap
{
    /**
     * @var string
     */
    public const NAME = 'spryker';

    /**
     * @var string
     */
    protected const INSTANCE_ID = 'instance.id';

    /**
     * @return void
     */
    public static function register(): void
    {
        Registry::registerResourceDetector(
            static::INSTANCE_ID,
            new CloudResourceDetector(),
        );
    }
}
