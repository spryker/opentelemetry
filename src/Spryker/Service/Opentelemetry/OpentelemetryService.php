<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry;

use Spryker\Service\Kernel\AbstractService;
use Spryker\Service\Opentelemetry\Storage\ResourceNameStorage;
use Throwable;

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
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $name
     *
     * @return void
     */
    public function setRootSpanName(string $name): void
    {
        $this->getFactory()->createRootSpanNameStorage()->setName($name);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $name
     *
     * @return void
     */
    public function setResourceName(string $name): void
    {
       $this->getFactory()->createResourceNameStorage()->setName($name);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $message
     * @param \Throwable $exception
     *
     * @return void
     */
    public function setError(string $message, Throwable $exception): void
    {
        $this->getFactory()->createExceptionStorage()->addException($exception);
    }

    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param string $name
     * @param array $attributes
     *
     * @return void
     */
    public function addEvent(string $name, array $attributes): void
    {
        $this->getFactory()->createCustomEventsStorage()->addEvent($name, $attributes);
    }
}
