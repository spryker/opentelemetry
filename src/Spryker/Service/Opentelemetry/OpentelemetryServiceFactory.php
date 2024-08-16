<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry;

use Spryker\Service\Kernel\AbstractServiceFactory;
use Spryker\Service\Opentelemetry\Storage\AttributesStorage;
use Spryker\Service\Opentelemetry\Storage\AttributesStorageInterface;

class OpentelemetryServiceFactory extends AbstractServiceFactory
{
    /**
     * @return \Spryker\Service\Opentelemetry\Storage\AttributesStorageInterface
     */
    public function createAttributesStorage(): AttributesStorageInterface
    {
        return AttributesStorage::getInstance();
    }
}
