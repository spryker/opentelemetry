<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

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
     *
     * @var bool
     */
    protected bool $isSampled;

    /**
     * @var bool
     */
    protected bool $isValid = true;

    /**
     * @param string $traceId
     * @param string $spanId
     * @param int $traceFlags
     * @param bool $isRemote
     * @param \OpenTelemetry\API\Trace\TraceStateInterface|null $traceState
     */
    protected function __construct(
        protected string $traceId,
        protected string $spanId,
        protected readonly int $traceFlags,
        protected readonly bool $isRemote,
        protected readonly ?TraceStateInterface $traceState = null,
    ) {
        $this->isSampled = ($traceFlags & TraceFlags::SAMPLED) === TraceFlags::SAMPLED;
    }

    /**
     * @return string
     */
    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /**
     * @return string
     */
    public function getTraceIdBinary(): string
    {
        return hex2bin($this->traceId);
    }

    /**
     * @return string
     */
    public function getSpanId(): string
    {
        return $this->spanId;
    }

    /**
     * @return string
     */
    public function getSpanIdBinary(): string
    {
        return hex2bin($this->spanId);
    }

    /**
     * @return \OpenTelemetry\API\Trace\TraceStateInterface|null
     */
    public function getTraceState(): ?TraceStateInterface
    {
        return $this->traceState;
    }

    /**
     * @return bool
     */
    public function isSampled(): bool
    {
        return $this->isSampled;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @return bool
     */
    public function isRemote(): bool
    {
        return $this->isRemote;
    }

    /**
     * @return int
     */
    public function getTraceFlags(): int
    {
        return $this->traceFlags;
    }

    /**
     * @param string $traceId
     * @param string $spanId
     * @param int $traceFlags
     * @param \OpenTelemetry\API\Trace\TraceStateInterface|null $traceState
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
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

    /**
     * @param string $traceId
     * @param string $spanId
     * @param int $traceFlags
     * @param \OpenTelemetry\API\Trace\TraceStateInterface|null $traceState
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
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

    /**
     * @return \OpenTelemetry\API\Trace\SpanContextInterface
     */
    public static function getInvalid(): SpanContextInterface
    {
        if (null === static::$invalidContext) {
            static::$invalidContext = self::create(SpanContextValidator::INVALID_TRACE, SpanContextValidator::INVALID_SPAN, 0);
        }

        return static::$invalidContext;
    }
}
