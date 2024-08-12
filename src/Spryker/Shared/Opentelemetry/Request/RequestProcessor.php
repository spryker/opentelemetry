<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Request;

use Symfony\Component\HttpFoundation\Request;

class RequestProcessor implements RequestProcessorInterface
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request|null
     */
    protected static ?Request $request = null;

    /**
     * @return \Symfony\Component\HttpFoundation\Request|null
     */
    public static function getRequest(): ?Request
    {
        if (static::$request === null) {
            static::setRequest();
        }

        return static::$request;
    }

    /**
     * @return void
     */
    protected static function setRequest(): void
    {
        static::$request = Request::createFromGlobals();
    }
}
