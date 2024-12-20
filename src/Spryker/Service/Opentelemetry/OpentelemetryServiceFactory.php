<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry;

use Spryker\Service\Kernel\AbstractServiceFactory;
use Spryker\Service\Opentelemetry\Storage\CustomParameterStorage;
use Spryker\Service\Opentelemetry\Storage\CustomParameterStorageInterface;
use Spryker\Service\Opentelemetry\Storage\ExceptionStorage;
use Spryker\Service\Opentelemetry\Storage\ExceptionStorageInterface;
use Spryker\Service\Opentelemetry\Storage\ResourceNameStorage;
use Spryker\Service\Opentelemetry\Storage\RootSpanNameStorage;
use Spryker\Service\Opentelemetry\Storage\RootSpanNameStorageInterface;

class OpentelemetryServiceFactory extends AbstractServiceFactory
{
    /**
     * @return \Spryker\Service\Opentelemetry\Storage\CustomParameterStorageInterface
     */
    public function createCustomParameterStorage(): CustomParameterStorageInterface
    {
        return CustomParameterStorage::getInstance();
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\RootSpanNameStorageInterface
     */
    public function createRootSpanNameStorage(): RootSpanNameStorageInterface
    {
        return RootSpanNameStorage::getInstance();
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\ResourceNameStorage
     */
    public function createResourceNameStorage(): ResourceNameStorage
    {
        return ResourceNameStorage::getInstance();
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\ExceptionStorageInterface
     */
    public function createExceptionStorage(): ExceptionStorageInterface
    {
        return ExceptionStorage::getInstance();
    }
}
