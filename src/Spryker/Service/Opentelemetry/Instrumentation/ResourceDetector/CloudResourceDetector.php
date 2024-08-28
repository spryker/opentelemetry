<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\ResourceDetector;

use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/resource/sdk.md#specifying-resource-information-via-an-environment-variable
 * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/configuration/sdk-environment-variables.md#general-sdk-configuration
 *
 * Apparently NewRelic requires service.instance.id attribute and collector is setting it automatically,
 * but when the app configured not to use collection, e.g. to send data directly to observability service,
 * then this attribute is not set.
 *
 * It is also possible to add it using env var in deploy file, in urlencoded format
 *     OTEL_RESOURCE_ATTRIBUTES: 'service.instance.id=abc'
 *
 * @see \OpenTelemetry\SDK\Resource\Detectors\Environment
 */
final class CloudResourceDetector implements ResourceDetectorInterface
{
    /**
     * @return \OpenTelemetry\SDK\Resource\ResourceInfo
     */
    public function getResource(): ResourceInfo
    {
        $attributes = [
            ResourceAttributes::SERVICE_INSTANCE_ID => gethostname(),
        ];

        return ResourceInfo::create(Attributes::create($attributes), ResourceAttributes::SCHEMA_URL);
    }
}
