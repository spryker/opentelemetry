<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\Opentelemetry\Plugin\Log;

use Spryker\Glue\Kernel\AbstractPlugin;
use Spryker\Shared\Log\Dependency\Plugin\LogProcessorPluginInterface;

/**
 * @method \Spryker\Glue\Opentelemetry\OpentelemetryFactory getFactory()
 */
class OpentelemetryLogProcessorPlugin extends AbstractPlugin implements LogProcessorPluginInterface
{
    /**
     * {@inheritDoc}
     * - Transforms the log message into a JSON object if it is not one already.
     * - Enriches the message with trace_id, span_id, service_name, and service_namespace fields from the active OpenTelemetry span.
     *
     * @api
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function __invoke(array $data): array
    {
        return $this->getFactory()->createOpentelemetryLogProcessor()->__invoke($data);
    }
}
