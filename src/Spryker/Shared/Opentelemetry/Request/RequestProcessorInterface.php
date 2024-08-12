<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Request;

use Symfony\Component\HttpFoundation\Request;

interface RequestProcessorInterface
{
    /**
     * @return \Symfony\Component\HttpFoundation\Request|null
     */
    public static function getRequest(): ?Request;
}
