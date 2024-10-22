<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\SpanProcessor;

use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\ReadableSpanInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;

class PostFilterBatchSpanProcessor extends BatchSpanProcessor
{
    /**
     * @param \OpenTelemetry\SDK\Trace\ReadableSpanInterface $span
     *
     * @return void
     */
    public function onEnd(ReadableSpanInterface $span): void
    {
        if ($span->getDuration() < OpentelemetryConfig::getSamplerThresholdNano()
            && $span->toSpanData()->getParentSpanId()
            && $span->toSpanData()->getStatus()->getCode() === StatusCode::STATUS_OK
        ) {
            return;
        }
        parent::onEnd($span);
    }
}
