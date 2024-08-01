<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business\Generator\Request;

use Symfony\Component\HttpFoundation\Request;

class RequestProcessor
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request|null
     */
    protected static ?Request $request = null;

    /**
     * @return void
     */
    public static function setRequest(): void
    {
        static::$request = Request::createFromGlobals();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public static function getRequest(): Request
    {
        if (static::$request === null) {
            static::setRequest();
        }

        return static::$request;
    }
}
