<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Span as OtelSpan;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Exception\StackTraceFormatter;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Event;
use OpenTelemetry\SDK\Trace\Link;
use OpenTelemetry\SDK\Trace\ReadWriteSpanInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanLimits;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\StatusData;
use OpenTelemetry\SDK\Trace\StatusDataInterface;
use Throwable;

class Span extends OtelSpan implements ReadWriteSpanInterface, SpanDataInterface
{
    use LogsMessagesTrait;

    /**
     * @var list<\OpenTelemetry\SDK\Trace\EventInterface>
     */
    protected array $events = [];

    /**
     * @var int
     */
    protected int $totalRecordedEvents = 0;

    /**
     * @var \OpenTelemetry\SDK\Trace\StatusDataInterface
     */
    protected StatusDataInterface $status;

    /**
     * @var int
     */
    protected int $endEpochNanos = 0;

    /**
     * @var bool
     */
    protected bool $hasEnded = false;

    /**
     * @var bool
     */
    protected bool $isCritical = false;

    /**
     * @var \OpenTelemetry\SDK\Common\Attribute\AttributesInterface|null
     */
    protected ?AttributesInterface $attributes = null;

    /**
     * @param string $name
     * @param list<\OpenTelemetry\SDK\Trace\LinkInterface> $links
     */
    protected function __construct(
        protected string $name,
        protected SpanContextInterface $context,
        protected InstrumentationScopeInterface $instrumentationScope,
        protected int $kind,
        protected SpanContextInterface $parentSpanContext,
        protected SpanLimits $spanLimits,
        protected SpanProcessorInterface $spanProcessor,
        protected ResourceInfo $resource,
        protected AttributesBuilderInterface $attributesBuilder,
        protected array $links,
        protected int $totalRecordedLinks,
        protected int $startEpochNanos,
    ) {
        $this->status = StatusData::unset();
    }

    /**
     * @param string $name
     * @param \OpenTelemetry\API\Trace\SpanContextInterface $context
     * @param \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface $instrumentationScope
     * @param int $kind
     * @param \OpenTelemetry\API\Trace\SpanContextInterface $parentSpanContext
     * @param \OpenTelemetry\Context\ContextInterface $parentContext
     * @param \OpenTelemetry\SDK\Trace\SpanLimits $spanLimits
     * @param \OpenTelemetry\SDK\Trace\SpanProcessorInterface $spanProcessor
     * @param \OpenTelemetry\SDK\Resource\ResourceInfo $resource
     * @param \OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface $attributesBuilder
     * @param list<\OpenTelemetry\SDK\Trace\LinkInterface> $links
     * @param int $totalRecordedLinks
     * @param int $startEpochNanos
     *
     * @return $this
     */
    public static function startSpan(
        string $name,
        SpanContextInterface $context,
        InstrumentationScopeInterface $instrumentationScope,
        int $kind,
        SpanContextInterface $parentSpanContext,
        ContextInterface $parentContext,
        SpanLimits $spanLimits,
        SpanProcessorInterface $spanProcessor,
        ResourceInfo $resource,
        AttributesBuilderInterface $attributesBuilder,
        array $links,
        int $totalRecordedLinks,
        int $startEpochNanos,
    ): self {
        $span = new self(
            $name,
            $context,
            $instrumentationScope,
            $kind,
            $parentSpanContext,
            $spanLimits,
            $spanProcessor,
            $resource,
            $attributesBuilder,
            $links,
            $totalRecordedLinks,
            $startEpochNanos !== 0 ? $startEpochNanos : Clock::getDefault()->now()
        );

        $spanProcessor->onStart($span, $parentContext);

        return $span;
    }

    /**
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
    public function getContext(): SpanContextInterface
    {
        return $this->context;
    }

    /**
     * @return bool
     */
    public function isRecording(): bool
    {
        return !$this->hasEnded;
    }

    /**
     * @param string $key
     * @param $value
     *
     * @return $this
     */
    public function setAttribute(string $key, $value): self
    {
        if ($this->hasEnded) {
            return $this;
        }

        $this->attributesBuilder[$key] = $value;

        return $this;
    }

    /**
     * @param iterable $attributes
     *
     * @return $this
     */
    public function setAttributes(iterable $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributesBuilder[$key] = $value;
        }

        return $this;
    }

    /**
     * @param \OpenTelemetry\API\Trace\SpanContextInterface $context
     * @param iterable $attributes
     *
     * @return $this
     */
    public function addLink(SpanContextInterface $context, iterable $attributes = []): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        if (!$context->isValid()) {
            return $this;
        }
        if (++$this->totalRecordedLinks > $this->spanLimits->getLinkCountLimit()) {
            return $this;
        }

        $this->links[] = new Link(
            $context,
            $this->spanLimits
                ->getLinkAttributesFactory()
                ->builder($attributes)
                ->build(),
        );

        return $this;
    }

    /**
     * @param string $name
     * @param iterable $attributes
     * @param int|null $timestamp
     *
     * @return $this
     */
    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        if (++$this->totalRecordedEvents > $this->spanLimits->getEventCountLimit()) {
            array_shift($this->events);
        }

        $timestamp ??= Clock::getDefault()->now();
        $eventAttributesBuilder = $this->spanLimits->getEventAttributesFactory()->builder($attributes);

        $this->events[] = new Event($name, $timestamp, $eventAttributesBuilder->build());

        return $this;
    }

    /**
     * @param \Throwable $exception
     * @param iterable $attributes
     * @param int|null $timestamp
     *
     * @return $this
     */
    public function recordException(Throwable $exception, iterable $attributes = [], ?int $timestamp = null): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        if (++$this->totalRecordedEvents > $this->spanLimits->getEventCountLimit()) {
            array_shift($this->events);
        }

        $timestamp ??= Clock::getDefault()->now();
        $eventAttributesBuilder = $this->spanLimits->getEventAttributesFactory()->builder([
            'exception.type' => $exception::class,
            'exception.message' => $exception->getMessage(),
            'exception.previous_message' => $exception->getPrevious()?->getMessage(),
            'exception.stacktrace' => StackTraceFormatter::format($exception),
        ]);

        foreach ($attributes as $key => $value) {
            $eventAttributesBuilder[$key] = $value;
        }

        $this->events[] = new Event('exception', $timestamp, $eventAttributesBuilder->build());

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function updateName(string $name): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $code
     * @param string|null $description
     *
     * @return $this
     */
    public function setStatus(string $code, ?string $description = null): self
    {
        if ($this->hasEnded) {
            return $this;
        }

        if ($code === StatusCode::STATUS_UNSET) {
            return $this;
        }

        if ($this->status->getCode() === StatusCode::STATUS_OK) {
            return $this;
        }
        $this->status = StatusData::create($code, $description);

        return $this;
    }

    /**
     * @param int|null $endEpochNanos
     *
     * @return void
     */
    public function end(?int $endEpochNanos = null): void
    {
        if ($this->hasEnded) {
            return;
        }

        $this->endEpochNanos = $endEpochNanos ?? Clock::getDefault()->now();
        $this->hasEnded = true;

        $this->spanProcessor->onEnd($this);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
    public function getParentContext(): SpanContextInterface
    {
        return $this->parentSpanContext;
    }

    /**
     * @return \OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface
     */
    public function getInstrumentationScope(): InstrumentationScopeInterface
    {
        return $this->instrumentationScope;
    }

    /**
     * @return bool
     */
    public function hasEnded(): bool
    {
        return $this->hasEnded;
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\SpanDataInterface
     */
    public function toSpanData(): SpanDataInterface
    {
        return new ImmutableSpan(
            $this,
            $this->name,
            $this->links,
            $this->events,
            $this->attributesBuilder->build(),
            $this->totalRecordedLinks,
            $this->totalRecordedEvents,
            $this->status,
            $this->endEpochNanos,
            $this->hasEnded,
        );
    }

    /**
     * @return int
     */
    public function getDuration(): int
    {
        return ($this->hasEnded ? $this->endEpochNanos : Clock::getDefault()->now()) - $this->startEpochNanos;
    }

    /**
     * @return int
     */
    public function getKind(): int
    {
        return $this->kind;
    }

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getAttribute(string $key)
    {
        return $this->attributesBuilder[$key] ?? null;
    }

    /**
     * @return int
     */
    public function getStartEpochNanos(): int
    {
        return $this->startEpochNanos;
    }

    /**
     * @return int
     */
    public function getTotalRecordedLinks(): int
    {
        return $this->totalRecordedLinks;
    }

    /**
     * @return int
     */
    public function getTotalRecordedEvents(): int
    {
        return $this->totalRecordedEvents;
    }

    /**
     * @return \OpenTelemetry\SDK\Resource\ResourceInfo
     */
    public function getResource(): ResourceInfo
    {
        return $this->resource;
    }

    /**
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->context->getTraceId();
    }

    /**
     * @return string
     */
    public function getSpanId(): string
    {
        return $this->context->getSpanId();
    }

    /**
     * @return string
     */
    public function getParentSpanId(): string
    {
        return $this->parentSpanContext->getSpanId();
    }

    /**
     * @return \OpenTelemetry\SDK\Trace\StatusDataInterface
     */
    public function getStatus(): StatusDataInterface
    {
        return $this->status;
    }

    /**
     * @return \OpenTelemetry\SDK\Common\Attribute\AttributesInterface
     */
    public function getAttributes(): AttributesInterface
    {
        if (!$this->attributes) {
            $this->attributes = $this->attributesBuilder->build();
        }

        return $this->attributes;
    }

    /**
     * @return array<\OpenTelemetry\SDK\Trace\EventInterface>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @return array<\OpenTelemetry\SDK\Trace\LinkInterface>
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * @return int
     */
    public function getEndEpochNanos(): int
    {
        return $this->endEpochNanos;
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
}
