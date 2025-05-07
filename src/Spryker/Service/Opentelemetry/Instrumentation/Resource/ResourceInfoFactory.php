<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Resource;

use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SDK\Resource\Detectors\Composer;
use OpenTelemetry\SDK\Resource\Detectors\Environment;
use OpenTelemetry\SDK\Resource\Detectors\Host;
use OpenTelemetry\SDK\Resource\Detectors\OperatingSystem;
use OpenTelemetry\SDK\Resource\Detectors\Process;
use OpenTelemetry\SDK\Resource\Detectors\ProcessRuntime;
use OpenTelemetry\SDK\Resource\Detectors\Sdk;
use OpenTelemetry\SDK\Resource\Detectors\SdkProvided;
use OpenTelemetry\SDK\Resource\Detectors\Service;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Attributes;

class ResourceInfoFactory
{
    /**
     * @var \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo|null
     */
    protected static ?ResourceInfo $emptyResource = null;

    /**
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo
     */
    public static function defaultResource(): ResourceInfo
    {
        return (new CompositeResourceDetector([
            new Host(),
            new Process(),
            new Composer(),
            ...Registry::resourceDetectors(),
            new Environment(),
            new Sdk(),
            new Service(),
        ]))->getResource();
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo
     */
    public static function emptyResource(): ResourceInfo
    {
        if (null === self::$emptyResource) {
            self::$emptyResource = ResourceInfo::createSprykerResource(Attributes::create([]));
        }

        return self::$emptyResource;
    }
}
