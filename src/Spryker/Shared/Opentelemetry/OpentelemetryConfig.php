<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry;

use Spryker\Shared\Kernel\AbstractBundleConfig;

class OpentelemetryConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Returns the output directory for generated hooks.
     *
     * @api
     *
     * @return string
     */
    public static function getHooksDir(): string
    {
        return sprintf('%s/src/Generated/OpenTelemetry/Hooks/', APPLICATION_ROOT_DIR);
    }
}
