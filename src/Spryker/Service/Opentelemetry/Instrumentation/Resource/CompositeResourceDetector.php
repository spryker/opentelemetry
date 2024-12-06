<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Resource;

use OpenTelemetry\SDK\Resource\ResourceDetectorInterface;

class CompositeResourceDetector implements ResourceDetectorInterface
{
    /**
     * @param array<\OpenTelemetry\SDK\Resource\ResourceDetectorInterface> $resourceDetectors
     */
    public function __construct(protected array $resourceDetectors)
    {
    }

    /**
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo
     */
    public function getResource(): ResourceInfo
    {
        $resource = ResourceInfoFactory::emptyResource();
        foreach ($this->resourceDetectors as $resourceDetector) {
            $detectorResource = $resourceDetector->getResource();
            $resourceToMerge = new ResourceInfo($detectorResource->getAttributes(), $detectorResource->getSchemaUrl());
            $resource = $resource->mergeSprykerResource($resourceToMerge);
        }

        return $resource;
    }
}
