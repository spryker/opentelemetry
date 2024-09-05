<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\SpanFilter;

use OpenTelemetry\API\Trace\SpanInterface;

interface SpanFilterInterface
{
    /**
     * @param \OpenTelemetry\API\Trace\SpanInterface|\OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     * @param bool $forceToShow
     *
     * @return \OpenTelemetry\SDK\Trace\ReadableSpanInterface
     */
    public static function filter(SpanInterface $span, bool $forceToShow = false): SpanInterface;
}
