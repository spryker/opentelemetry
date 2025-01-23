<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\StatusDataInterface;

class ImmutableSpan implements SpanDataInterface
{
    public function __construct(
        protected readonly Span $span,
        protected readonly string $name,
        protected readonly array $links,
        protected readonly array $events,
        protected readonly AttributesInterface $attributes,
        protected readonly int $totalRecordedLinks,
        protected readonly int $totalRecordedEvents,
        protected readonly StatusDataInterface $status,
        protected readonly int $endEpochNanos,
        protected readonly bool $hasEnded,
    ) {
    }

    /**
     * @return int
     */
    public function getKind(): int
    {
        return $this->span->getKind();
    }

    /**
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
    public function getContext(): SpanContextInterface
    {
        return $this->span->getContext();
    }

    /**
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
    public function getParentContext(): SpanContextInterface
    {
        return $this->span->getParentContext();
    }

    /**
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->getContext()->getTraceId();
    }

    /**
     * @return string
     */
    public function getSpanId(): string
    {
        return $this->getContext()->getSpanId();
    }

    /**
     * @return string
     */
    public function getParentSpanId(): string
    {
        return $this->getParentContext()->getSpanId();
    }

    /**
     * @return int
     */
    public function getStartEpochNanos(): int
    {
        return $this->span->getStartEpochNanos();
    }

    /**
     * @return int
     */
    public function getEndEpochNanos(): int
    {
        return $this->endEpochNanos;
    }

    /**
     * @return \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface
     */
    public function getInstrumentationScope(): InstrumentationScopeInterface
    {
        return $this->span->getInstrumentationScope();
    }

    /**
     * @return \OpenTelemetry\SDK\Resource\ResourceInfo
     */
    public function getResource(): ResourceInfo
    {
        return $this->span->getResource();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array|\OpenTelemetry\SDK\Trace\LinkInterface[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @return array|\OpenTelemetry\SDK\Trace\EventInterface[]
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesInterface
     */
    public function getAttributes(): AttributesInterface
    {
        return $this->attributes;
    }

    /**
     * @return int
     */
    public function getTotalDroppedEvents(): int
    {
        return max(0, $this->totalRecordedEvents - count($this->events));
    }

    /**
     * @return int
     */
    public function getTotalDroppedLinks(): int
    {
        return max(0, $this->totalRecordedLinks - count($this->links));
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\StatusDataInterface
     */
    public function getStatus(): StatusDataInterface
    {
        return $this->status;
    }

    /**
     * @return bool
     */
    public function hasEnded(): bool
    {
        return $this->hasEnded;
    }
}
