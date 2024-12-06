<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Resource;

use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo as OtelResourceInfo;
use OpenTelemetry\SemConv\ResourceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Attributes;
use Spryker\Service\Opentelemetry\Instrumentation\Span\WritableAttributesInterface;

class ResourceInfo extends OtelResourceInfo
{
    public function __construct(
        protected AttributesInterface $attributes,
        protected ?string $schemaUrl = null,
    ) {
    }

    /**
     * @param string $name
     * @return void
     */
    public function setServiceName(string $name): void
    {
        if ($this->attributes instanceof WritableAttributesInterface) {
            $this->attributes->set(ResourceAttributes::SERVICE_NAME, $name);
        }
    }

    /**
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesInterface
     */
    public function getAttributes(): AttributesInterface
    {
        return $this->attributes;
    }

    /**
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributesInterface $attributes
     * @param string|null $schemaUrl
     *
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo
     */
    public static function createSprykerResource(AttributesInterface $attributes, ?string $schemaUrl = null): ResourceInfo
    {
        return new ResourceInfo($attributes, $schemaUrl);
    }

    /**
     * @return string|null
     */
    public function getSchemaUrl(): ?string
    {
        return $this->schemaUrl;
    }

    /**
     * @param \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo $updating
     *
     * @return \Spryker\Service\Opentelemetry\Instrumentation\Resource\ResourceInfo
     */
    public function mergeSprykerResource(ResourceInfo $updating): ResourceInfo
    {
        $schemaUrl = static::mergeSchemaUrl($this->getSchemaUrl(), $updating->getSchemaUrl());
        $attributes = Attributes::factory()->builder()->merge($this->getAttributes(), $updating->getAttributes());

        return ResourceInfo::createSprykerResource($attributes, $schemaUrl);
    }

    /**
     * @param string|null $old
     * @param string|null $updating
     *
     * @return string|null
     */
    protected static function mergeSchemaUrl(?string $old, ?string $updating): ?string
    {
        if (empty($old)) {
            return $updating;
        }
        if (empty($updating)) {
            return $old;
        }
        if ($old === $updating) {
            return $old;
        }

        return null;
    }
}
