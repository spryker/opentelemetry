<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

interface SpanIdUpdateAwareSpanContextInterface
{
    /**
     * @param string $spanId
     * @return void
     */
    public function updateSpanId(string $spanId): void;
}
