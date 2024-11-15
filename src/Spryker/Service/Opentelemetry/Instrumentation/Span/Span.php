<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Span as OtelSpan;
use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesBuilderInterface;
use OpenTelemetry\SDK\Common\Dev\Compatibility\Util as BcUtil;
use OpenTelemetry\SDK\Common\Exception\StackTraceFormatter;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Event;
use OpenTelemetry\SDK\Trace\EventInterface;
use OpenTelemetry\SDK\Trace\Link;
use OpenTelemetry\SDK\Trace\LinkInterface;
use OpenTelemetry\SDK\Trace\ReadWriteSpanInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanLimits;
use OpenTelemetry\SDK\Trace\SpanProcessorInterface;
use OpenTelemetry\SDK\Trace\StatusData;
use OpenTelemetry\SDK\Trace\StatusDataInterface;
use Throwable;

class Span extends OtelSpan implements ReadWriteSpanInterface
{
    use LogsMessagesTrait;

    /** @var list<EventInterface> */
    private array $events = [];
    private int $totalRecordedEvents = 0;
    private StatusDataInterface $status;
    private int $endEpochNanos = 0;
    private bool $hasEnded = false;

    /**
     * @param non-empty-string $name
     * @param list<LinkInterface> $links
     */
    private function __construct(
        private string $name,
        private readonly SpanContextInterface $context,
        private readonly InstrumentationScopeInterface $instrumentationScope,
        private readonly int $kind,
        private readonly SpanContextInterface $parentSpanContext,
        private readonly SpanLimits $spanLimits,
        private readonly SpanProcessorInterface $spanProcessor,
        private readonly ResourceInfo $resource,
        private AttributesBuilderInterface $attributesBuilder,
        private array $links,
        private int $totalRecordedLinks,
        private readonly int $startEpochNanos,
    ) {
        $this->status = StatusData::unset();
    }

    public static function startSpan(
        string $name,
        SpanContextInterface $context,
        InstrumentationScopeInterface $instrumentationScope,
        int $kind,
        SpanInterface $parentSpan,
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
            $parentSpan->getContext(),
            $spanLimits,
            $spanProcessor,
            $resource,
            $attributesBuilder,
            $links,
            $totalRecordedLinks,
            $startEpochNanos !== 0 ? $startEpochNanos : Clock::getDefault()->now()
        );

        // Call onStart here to ensure the span is fully initialized.
        $spanProcessor->onStart($span, $parentContext);

        return $span;
    }

    /**
     * Backward compatibility methods
     *
     * @codeCoverageIgnore
     */
    public static function formatStackTrace(Throwable $e, ?array &$seen = null): string
    {
        BcUtil::triggerMethodDeprecationNotice(
            __METHOD__,
            'format',
            StackTraceFormatter::class
        );

        return StackTraceFormatter::format($e);
    }

    /** @inheritDoc */
    public function getContext(): SpanContextInterface
    {
        return $this->context;
    }

    /** @inheritDoc */
    public function isRecording(): bool
    {
        return !$this->hasEnded;
    }

    /** @inheritDoc */
    public function setAttribute(string $key, $value): self
    {
        if ($this->hasEnded) {
            return $this;
        }

        $this->attributesBuilder[$key] = $value;

        return $this;
    }

    /** @inheritDoc */
    public function setAttributes(iterable $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributesBuilder[$key] = $value;
        }

        return $this;
    }

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

    /** @inheritDoc */
    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        if (++$this->totalRecordedEvents > $this->spanLimits->getEventCountLimit()) {
            return $this;
        }

        $timestamp ??= Clock::getDefault()->now();
        $eventAttributesBuilder = $this->spanLimits->getEventAttributesFactory()->builder($attributes);

        $this->events[] = new Event($name, $timestamp, $eventAttributesBuilder->build());

        return $this;
    }

    /** @inheritDoc */
    public function recordException(Throwable $exception, iterable $attributes = [], ?int $timestamp = null): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        if (++$this->totalRecordedEvents > $this->spanLimits->getEventCountLimit()) {
            return $this;
        }

        $timestamp ??= Clock::getDefault()->now();
        $eventAttributesBuilder = $this->spanLimits->getEventAttributesFactory()->builder([
            'exception.type' => $exception::class,
            'exception.message' => $exception->getMessage(),
            'exception.stacktrace' => StackTraceFormatter::format($exception),
        ]);

        foreach ($attributes as $key => $value) {
            $eventAttributesBuilder[$key] = $value;
        }

        $this->events[] = new Event('exception', $timestamp, $eventAttributesBuilder->build());

        return $this;
    }

    /** @inheritDoc */
    public function updateName(string $name): self
    {
        if ($this->hasEnded) {
            return $this;
        }
        $this->name = $name;

        return $this;
    }

    /** @inheritDoc */
    public function setStatus(string $code, ?string $description = null): self
    {
        if ($this->hasEnded) {
            return $this;
        }

        // An attempt to set value Unset SHOULD be ignored.
        if ($code === StatusCode::STATUS_UNSET) {
            return $this;
        }

        // When span status is set to Ok it SHOULD be considered final and any further attempts to change it SHOULD be ignored.
        if ($this->status->getCode() === StatusCode::STATUS_OK) {
            return $this;
        }
        $this->status = StatusData::create($code, $description);

        return $this;
    }

    /** @inheritDoc */
    public function end(?int $endEpochNanos = null): void
    {
        if ($this->hasEnded) {
            return;
        }

        $this->endEpochNanos = $endEpochNanos ?? Clock::getDefault()->now();
        $this->hasEnded = true;

        $this->spanProcessor->onEnd($this);
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return $this->name;
    }

    public function getParentContext(): SpanContextInterface
    {
        return $this->parentSpanContext;
    }

    public function getInstrumentationScope(): InstrumentationScopeInterface
    {
        return $this->instrumentationScope;
    }

    public function hasEnded(): bool
    {
        return $this->hasEnded;
    }

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
            $this->hasEnded
        );
    }

    /** @inheritDoc */
    public function getDuration(): int
    {
        return ($this->hasEnded ? $this->endEpochNanos : Clock::getDefault()->now()) - $this->startEpochNanos;
    }

    /** @inheritDoc */
    public function getKind(): int
    {
        return $this->kind;
    }

    /** @inheritDoc */
    public function getAttribute(string $key)
    {
        return $this->attributesBuilder[$key];
    }

    public function getStartEpochNanos(): int
    {
        return $this->startEpochNanos;
    }

    public function getTotalRecordedLinks(): int
    {
        return $this->totalRecordedLinks;
    }

    public function getTotalRecordedEvents(): int
    {
        return $this->totalRecordedEvents;
    }

    public function getResource(): ResourceInfo
    {
        return $this->resource;
    }
}
