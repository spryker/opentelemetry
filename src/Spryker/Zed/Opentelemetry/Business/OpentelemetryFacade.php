<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business;

use Spryker\Zed\Kernel\Business\AbstractFacade;

/**
 * @api
 *
 * @method \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory getFactory()
 */
class OpentelemetryFacade extends AbstractFacade implements OpentelemetryFacadeInterface
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
        $this->getFactory()->createHookRemover()->remove();
    }
}
