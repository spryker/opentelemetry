<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Trace\SpanContextInterface;
use OpenTelemetry\Contrib\Otlp\AttributesConverter;
use Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceRequest;
use Opentelemetry\Proto\Common\V1\InstrumentationScope;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Resource\V1\Resource as Resource_;
use Opentelemetry\Proto\Trace\V1\ResourceSpans;
use Opentelemetry\Proto\Trace\V1\ScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\Span\Event;
use Opentelemetry\Proto\Trace\V1\Span\Link;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind;
use Opentelemetry\Proto\Trace\V1\SpanFlags;
use Opentelemetry\Proto\Trace\V1\Status;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\API\Trace\SpanKind as TraceSpanKind;
use OpenTelemetry\API\Trace\StatusCode as TraceStatusCode;

class SpanConverter
{
    /**
     * @param iterable<Span> $spans
     *
     * @return \Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceRequest
     */
    public function convert(iterable $spans): ExportTraceServiceRequest
    {
        $pExportTraceServiceRequest = new ExportTraceServiceRequest();

        $resourceSpans = [];
        $resourceCache = [];
        $scopeSpans = [];
        $scopeCache = [];
        /** @var SpanDataInterface $span */
        foreach ($spans as $span) {
            $resource = $span->getResource();
            $instrumentationScope = $span->getInstrumentationScope();

            $resourceId = $resourceCache[spl_object_id($resource)] ??= serialize([
                $resource->getSchemaUrl(),
                $resource->getAttributes()->toArray(),
                $resource->getAttributes()->getDroppedAttributesCount(),
            ]);
            $instrumentationScopeId = $scopeCache[spl_object_id($instrumentationScope)] ??= serialize([
                $instrumentationScope->getName(),
                $instrumentationScope->getVersion(),
                $instrumentationScope->getSchemaUrl(),
                $instrumentationScope->getAttributes()->toArray(),
                $instrumentationScope->getAttributes()->getDroppedAttributesCount(),
            ]);

            if (($pResourceSpans = $resourceSpans[$resourceId] ?? null) === null) {
                /** @psalm-suppress InvalidArgument */
                $pExportTraceServiceRequest->getResourceSpans()[]
                    = $resourceSpans[$resourceId]
                    = $pResourceSpans
                    = $this->convertResourceSpans($resource);
            }

            if (($pScopeSpans = $scopeSpans[$resourceId][$instrumentationScopeId] ?? null) === null) {
                /** @psalm-suppress InvalidArgument */
                $pResourceSpans->getScopeSpans()[]
                    = $scopeSpans[$resourceId][$instrumentationScopeId]
                    = $pScopeSpans
                    = $this->convertScopeSpans($instrumentationScope);
            }

            /** @psalm-suppress InvalidArgument */
            $pScopeSpans->getSpans()[] = $this->convertSpan($span);
        }

        return $pExportTraceServiceRequest;
    }

    protected function convertResourceSpans(ResourceInfo $resource): ResourceSpans
    {
        $pResourceSpans = new ResourceSpans();
        $pResource = new Resource_();
        $this->setAttributes($pResource, $resource->getAttributes());
        $pResourceSpans->setResource($pResource);
        $pResourceSpans->setSchemaUrl((string) $resource->getSchemaUrl());

        return $pResourceSpans;
    }

    protected function convertScopeSpans(InstrumentationScopeInterface $instrumentationScope): ScopeSpans
    {
        $pScopeSpans = new ScopeSpans();
        $pInstrumentationScope = new InstrumentationScope();
        $pInstrumentationScope->setName($instrumentationScope->getName());
        $pInstrumentationScope->setVersion((string) $instrumentationScope->getVersion());
        $this->setAttributes($pInstrumentationScope, $instrumentationScope->getAttributes());
        $pScopeSpans->setScope($pInstrumentationScope);
        $pScopeSpans->setSchemaUrl((string) $instrumentationScope->getSchemaUrl());

        return $pScopeSpans;
    }

    /**
     * @param Resource_|Span|Event|Link|InstrumentationScope $pElement
     */
    protected function setAttributes($pElement, AttributesInterface $attributes): void
    {
        foreach ($attributes as $key => $value) {
            /** @psalm-suppress InvalidArgument */
            $pElement->getAttributes()[] = (new KeyValue())
                ->setKey($key)
                ->setValue(AttributesConverter::convertAnyValue($value));
        }
        $pElement->setDroppedAttributesCount($attributes->getDroppedAttributesCount());
    }

    protected function convertSpanKind(int $kind): int
    {
        return match ($kind) {
            TraceSpanKind::KIND_INTERNAL => SpanKind::SPAN_KIND_INTERNAL,
            TraceSpanKind::KIND_CLIENT => SpanKind::SPAN_KIND_CLIENT,
            TraceSpanKind::KIND_SERVER => SpanKind::SPAN_KIND_SERVER,
            TraceSpanKind::KIND_PRODUCER => SpanKind::SPAN_KIND_PRODUCER,
            TraceSpanKind::KIND_CONSUMER => SpanKind::SPAN_KIND_CONSUMER,
            default => SpanKind::SPAN_KIND_UNSPECIFIED,
        };
    }

    protected function convertStatusCode(string $status): int
    {
        switch ($status) {
            case TraceStatusCode::STATUS_UNSET: return StatusCode::STATUS_CODE_UNSET;
            case TraceStatusCode::STATUS_OK: return StatusCode::STATUS_CODE_OK;
            case TraceStatusCode::STATUS_ERROR: return StatusCode::STATUS_CODE_ERROR;
        }

        return StatusCode::STATUS_CODE_UNSET;
    }

    protected function convertSpan(SpanDataInterface $span): Span
    {
        $pSpan = new Span();
        $pSpan->setTraceId($span->getContext()->getTraceIdBinary());
        $pSpan->setSpanId($span->getContext()->getSpanIdBinary());
        $pSpan->setFlags(static::traceFlags($span->getContext()));
        $pSpan->setTraceState((string)$span->getContext()->getTraceState());
        if ($span->getParentContext()->isValid()) {
            $pSpan->setParentSpanId($span->getParentContext()->getSpanIdBinary());
        }
        $pSpan->setName($span->getName());
        $pSpan->setKind($this->convertSpanKind($span->getKind()));
        $pSpan->setStartTimeUnixNano($span->getStartEpochNanos());
        $pSpan->setEndTimeUnixNano($span->getEndEpochNanos());
        $this->setAttributes($pSpan, $span->getAttributes());

        foreach ($span->getEvents() as $event) {
            /** @psalm-suppress InvalidArgument */
            $pSpan->getEvents()[] = $pEvent = new Event();
            $pEvent->setTimeUnixNano($event->getEpochNanos());
            $pEvent->setName($event->getName());
            $this->setAttributes($pEvent, $event->getAttributes());
        }
        $pSpan->setDroppedEventsCount($span->getTotalDroppedEvents());

        foreach ($span->getLinks() as $link) {
            /** @psalm-suppress InvalidArgument */
            $pSpan->getLinks()[] = $pLink = new Link();
            $pLink->setTraceId($link->getSpanContext()->getTraceIdBinary());
            $pLink->setSpanId($link->getSpanContext()->getSpanIdBinary());
            $pLink->setFlags(static::traceFlags($link->getSpanContext()));
            $pLink->setTraceState((string)$link->getSpanContext()->getTraceState());
            $this->setAttributes($pLink, $link->getAttributes());
        }
        $pSpan->setDroppedLinksCount($span->getTotalDroppedLinks());

        $pStatus = new Status();
        $pStatus->setMessage($span->getStatus()->getDescription());
        $pStatus->setCode($this->convertStatusCode($span->getStatus()->getCode()));
        $pSpan->setStatus($pStatus);

        return $pSpan;
    }

    protected static function traceFlags(SpanContextInterface $spanContext): int
    {
        $flags = $spanContext->getTraceFlags();
        $flags |= SpanFlags::SPAN_FLAGS_CONTEXT_HAS_IS_REMOTE_MASK;
        if ($spanContext->isRemote()) {
            $flags |= SpanFlags::SPAN_FLAGS_CONTEXT_IS_REMOTE_MASK;
        }

        return $flags;
    }
}
