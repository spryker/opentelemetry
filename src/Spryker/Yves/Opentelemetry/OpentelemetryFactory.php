<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Yves\Opentelemetry;

use Monolog\Processor\ProcessorInterface;
use Spryker\Shared\Opentelemetry\Log\Processor\OpentelemetryLogProcessor;
use Spryker\Shared\Opentelemetry\Reader\ResourceNameReader;
use Spryker\Shared\Opentelemetry\Reader\ResourceNameReaderInterface;
use Spryker\Shared\Opentelemetry\Storage\CustomParameterStorage;
use Spryker\Shared\Opentelemetry\Storage\CustomParameterStorageInterface;
use Spryker\Shared\Opentelemetry\Storage\ResourceNameStorage;
use Spryker\Shared\Opentelemetry\Storage\ResourceNameStorageInterface;
use Spryker\Yves\Kernel\AbstractFactory;

class OpentelemetryFactory extends AbstractFactory
{
    public function createOpentelemetryLogProcessor(): ProcessorInterface
    {
        return new OpentelemetryLogProcessor($this->createResourceNameReader());
    }

    public function createResourceNameReader(): ResourceNameReaderInterface
    {
        return new ResourceNameReader($this->createResourceNameStorage(), $this->createCustomParamsStorage());
    }

    public function createResourceNameStorage(): ResourceNameStorageInterface
    {
        return ResourceNameStorage::getInstance();
    }

    public function createCustomParamsStorage(): CustomParameterStorageInterface
    {
        return CustomParameterStorage::getInstance();
    }
}
