<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Propagation;

use Exception;
use OpenTelemetry\Context\Propagation\PropagationGetterInterface;
use Symfony\Component\HttpFoundation\Request;

class SymfonyRequestPropagationGetter implements PropagationGetterInterface
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $carrier
     *
     * @return array<string>
     */
    public function keys($carrier): array
    {
        $this->checkCarrier($carrier);

        return $carrier->headers->keys();
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $carrier $carrier
     * @param string $key
     *
     * @return string|null
     */
    public function get($carrier, string $key) : ?string
    {
        $this->checkCarrier($carrier);

        return $carrier->headers->get($key);
    }

    /**
     * @param mixed $carrier
     *
     * @throws \Exception
     *
     * @return void
     */
    protected function checkCarrier($carrier): void
    {
        if (!$carrier instanceof Request) {
            throw new Exception('Carrier should be instance of Symfony Request');
        }
    }
}
