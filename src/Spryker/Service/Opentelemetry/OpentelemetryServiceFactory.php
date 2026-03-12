<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry;

use Spryker\Service\Kernel\AbstractServiceFactory;
use Spryker\Shared\Opentelemetry\Reader\ResourceNameReader;
use Spryker\Shared\Opentelemetry\Reader\ResourceNameReaderInterface;
use Spryker\Shared\Opentelemetry\Storage\CustomEventsStorage;
use Spryker\Shared\Opentelemetry\Storage\CustomEventsStorageInterface;
use Spryker\Shared\Opentelemetry\Storage\CustomParameterStorage;
use Spryker\Shared\Opentelemetry\Storage\CustomParameterStorageInterface;
use Spryker\Shared\Opentelemetry\Storage\ExceptionStorage;
use Spryker\Shared\Opentelemetry\Storage\ExceptionStorageInterface;
use Spryker\Shared\Opentelemetry\Storage\ResourceNameStorage;
use Spryker\Shared\Opentelemetry\Storage\RootSpanNameStorage;
use Spryker\Shared\Opentelemetry\Storage\RootSpanNameStorageInterface;

class OpentelemetryServiceFactory extends AbstractServiceFactory
{
    public function createCustomParameterStorage(): CustomParameterStorageInterface
    {
        return CustomParameterStorage::getInstance();
    }

    public function createRootSpanNameStorage(): RootSpanNameStorageInterface
    {
        return RootSpanNameStorage::getInstance();
    }

    public function createResourceNameStorage(): ResourceNameStorage
    {
        return ResourceNameStorage::getInstance();
    }

    public function createExceptionStorage(): ExceptionStorageInterface
    {
        return ExceptionStorage::getInstance();
    }
    public function createCustomEventsStorage(): CustomEventsStorageInterface
    {
        return CustomEventsStorage::getInstance();
    }

    public function createResourceNameReader(): ResourceNameReaderInterface
    {
        return new ResourceNameReader($this->createResourceNameStorage(), $this->createCustomParameterStorage());
    }
}
