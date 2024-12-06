<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesFactoryInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributeValidator;
use OpenTelemetry\SDK\Common\Attribute\AttributeValidatorInterface;

class AttributesFactory implements AttributesFactoryInterface
{
    /**
     * @param int|null $attributeCountLimit
     * @param int|null $attributeValueLengthLimit
     */
    public function __construct(
        protected ?int $attributeCountLimit = null,
        protected ?int $attributeValueLengthLimit = null,
    ) {
    }

    /**
     * @param iterable $attributes
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributeValidatorInterface|null $attributeValidator
     *
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface
     */
    public function builder(iterable $attributes = [], ?AttributeValidatorInterface $attributeValidator = null): AttributesBuilderInterface
    {
        $builder = new AttributesBuilder(
            [],
            $this->attributeCountLimit,
            $this->attributeValueLengthLimit,
            0,
            $attributeValidator ?? new AttributeValidator(),
        );
        foreach ($attributes as $key => $value) {
            $builder[$key] = $value;
        }

        return $builder;
    }
}
