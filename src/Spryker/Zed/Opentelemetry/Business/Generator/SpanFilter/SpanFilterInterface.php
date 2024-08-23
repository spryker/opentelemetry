<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\SpanFilter;

use OpenTelemetry\SDK\Trace\ReadableSpanInterface;

interface SpanFilterInterface
{
    /**
     * @param \OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     * @param bool $forceToShow
     *
     * @return \OpenTelemetry\SDK\Trace\ReadableSpanInterface
     */
    public static function filter(ReadableSpanInterface $span, bool $forceToShow = false): ReadableSpanInterface;
}
