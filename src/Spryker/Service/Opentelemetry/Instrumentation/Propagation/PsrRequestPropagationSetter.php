<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Propagation;

use OpenTelemetry\Context\Propagation\PropagationSetterInterface;

class PsrRequestPropagationSetter implements PropagationSetterInterface
{
    /**
     * @param \Psr\Http\Message\RequestInterface $carrier
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set(&$carrier, string $key, string $value): void
    {
        $carrier = $carrier->withAddedHeader($key, $value);
    }
}
