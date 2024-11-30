<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry;

use Spryker\Service\Kernel\AbstractService;
use Spryker\Service\Opentelemetry\Storage\ResourceNameStorage;

/**
 * @method \Spryker\Service\Opentelemetry\OpentelemetryServiceFactory getFactory()
 */
class OpentelemetryService extends AbstractService implements OpentelemetryServiceInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setCustomParameter(string $key, $value): void
    {
        $this->getFactory()->createCustomParameterStorage()->setAttribute($key, $value);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setRootSpanName(string $name): void
    {
        $this->getFactory()->createRootSpanNameStorage()->setName($name);
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setResourceName(string $name): void
    {
       $this->getFactory()->createResourceNameStorage()->setName($name);
    }
}
