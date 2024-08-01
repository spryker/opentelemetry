<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business;

use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @api
 *
 * @method \Spryker\Zed\OpenTelemetry\Business\OpenTelemetryBusinessFactory getFactory()
 */
class OpenTelemetryFacade extends AbstractFacade implements OpenTelemetryFacadeInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return void
     */
    public function generateHooks(): void
    {
        $this->getFactory()->createHookGenerator()->generate();
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @return void
     */
    public function deleteHooks(): void
    {
        $this->getFactory()->createHookRemover()->clear();
    }
}
