<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Sampler;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TraceStateInterface;

interface TraceStateAwareSamplerInterface
{
    /**
     * @param \OpenTelemetry\API\Trace\TraceStateInterface $span
     *
     * @return void
     */
    public function addTraceState(TraceStateInterface $span): void;
}