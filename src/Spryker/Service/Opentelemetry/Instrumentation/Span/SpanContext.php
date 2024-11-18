<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\API\Trace\SpanContextValidator;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\API\Trace\TraceStateInterface;
use function hex2bin;

class SpanContext implements SpanContextInterface
{
    protected static ?SpanContextInterface $invalidContext = null;

    /**
     * @see https://www.w3.org/TR/trace-context/#trace-flags
     * @see https://www.w3.org/TR/trace-context/#sampled-flag
     */
    protected readonly bool $isSampled;
    protected bool $isValid = true;

    protected function __construct(
        protected string $traceId,
        protected string $spanId,
        protected readonly int $traceFlags,
        protected readonly bool $isRemote,
        protected readonly ?TraceStateInterface $traceState = null,
    ) {
        $this->isSampled = ($traceFlags & TraceFlags::SAMPLED) === TraceFlags::SAMPLED;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getTraceIdBinary(): string
    {
        return hex2bin($this->traceId);
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getSpanIdBinary(): string
    {
        return hex2bin($this->spanId);
    }

    public function getTraceState(): ?TraceStateInterface
    {
        return $this->traceState;
    }

    public function isSampled(): bool
    {
        return $this->isSampled;
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function isRemote(): bool
    {
        return $this->isRemote;
    }

    public function getTraceFlags(): int
    {
        return $this->traceFlags;
    }

    /** @inheritDoc */
    public static function createFromRemoteParent(string $traceId, string $spanId, int $traceFlags = TraceFlags::DEFAULT, ?TraceStateInterface $traceState = null): SpanContextInterface
    {
        return new static(
            $traceId,
            $spanId,
            $traceFlags,
            true,
            $traceState,
        );
    }

    /** @inheritDoc */
    public static function create(string $traceId, string $spanId, int $traceFlags = TraceFlags::DEFAULT, ?TraceStateInterface $traceState = null): SpanContextInterface
    {
        return new static(
            $traceId,
            $spanId,
            $traceFlags,
            false,
            $traceState,
        );
    }

    /** @inheritDoc */
    public static function getInvalid(): SpanContextInterface
    {
        if (null === static::$invalidContext) {
            static::$invalidContext = self::create(SpanContextValidator::INVALID_TRACE, SpanContextValidator::INVALID_SPAN, 0);
        }

        return static::$invalidContext;
    }
}
