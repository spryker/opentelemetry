<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Reader;

use OpenTelemetry\SemConv\ResourceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\SprykerInstrumentationBootstrap;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Spryker\Service\Opentelemetry\Plugin\OpentelemetryMonitoringExtensionPlugin;
use Spryker\Shared\Opentelemetry\Storage\CustomParameterStorageInterface;
use Spryker\Shared\Opentelemetry\Storage\ResourceNameStorageInterface;

class ResourceNameReader implements ResourceNameReaderInterface
{
    public function __construct(
        protected ResourceNameStorageInterface $resourceNameStorage,
        protected CustomParameterStorageInterface $customParameterStorage,
    ) {}

    public function readName(): string
    {
        $runtimeAddedName = $this->resourceNameStorage->getName();
        if ($runtimeAddedName === null) {
            $resource = SprykerInstrumentationBootstrap::getResourceInfo();

            if ($resource === null) {
                return OpentelemetryInstrumentationConfig::getDefaultServiceName();
            }

            return $resource->getAttributes()[ResourceAttributes::SERVICE_NAME];
        }

        $cli = $this->customParameterStorage->getAttribute(OpentelemetryMonitoringExtensionPlugin::ATTRIBUTE_IS_CONSOLE_COMMAND);
        if ($cli) {
            $runtimeAddedName = sprintf('CLI %s', $runtimeAddedName);
        }

        return $runtimeAddedName;
    }
}
