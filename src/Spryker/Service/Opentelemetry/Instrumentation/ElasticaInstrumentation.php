<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use Elastica\Client;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\TraceSampleResult;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class ElasticaInstrumentation
{
    /**
     * @var string
     */
    public const ATTRIBUTE_QUERY_TIME = 'spr.search.queryTime';

    /**
     * @var string
     */
    public const ATTRIBUTE_SEARCH_INDEX = 'spr.search.index';

    /**
     * @var string
     */
    public const ATTRIBUTE_SEARCH_INDEXES = 'spr.search.indexes';

    /**
     * @var string
     */
    public const ATTRIBUTE_SEARCH_ID = 'spr.search.id';

    /**
     * @var string
     */
    public const ATTRIBUTE_SEARCH_IDS = 'spr.search.ids';

    /**
     * @var string
     */
    protected const NAME = 'spryker_otel_elastica';

    /**
     * @var string
     */
    protected const METHOD_NAME_REQUEST = 'request';

    /**
     * @var string
     */
    protected const METHOD_NAME_UPDATE_DOCUMENTS = 'updateDocuments';

    /**
     * @var string
     */
    protected const METHOD_NAME_UPDATE_DOCUMENT = 'updateDocument';

    /**
     * @var string
     */
    protected const METHOD_NAME_ADD_DOCUMENTS = 'addDocuments';

    /**
     * @var string
     */
    protected const METHOD_NAME_DELETE_DOCUMENTS = 'deleteDocuments';

    /**
     * @var string
     */
    protected const METHOD_NAME_DELETE_IDS = 'deleteIds';

    /**
     * @var string
     */
    protected const SPAN_NAME_REQUEST = 'elasticsearch-request';

    /**
     * @var string
     */
    protected const SPAN_NAME_UPDATE_DOCUMENTS = 'elasticsearch-update-documents';

    /**
     * @var string
     */
    protected const SPAN_NAME_UPDATE_DOCUMENT = 'elasticsearch-update-document';

    /**
     * @var string
     */
    protected const SPAN_NAME_ADD_DOCUMENTS = 'elasticsearch-add-document';

    /**
     * @var string
     */
    protected const SPAN_NAME_DELETE_DOCUMENTS = 'elasticsearch-delete-documents';

    /**
     * @var string
     */
    protected const SPAN_NAME_DELETE_IDS = 'elasticsearch-delete-ids';

    /**
     * @var string
     */
    protected const HEADER_HOST = 'host';

    /**
     * @return void
     */
    public static function register(): void
    {
        //BC check
        if (class_exists('\Spryker\Service\OtelElasticaInstrumentation\OpenTelemetry\ElasticaInstrumentation')) {
            return;
        }

        if (Sdk::isInstrumentationDisabled(static::NAME) === true) {
            return;
        }

        hook(
            class: Client::class,
            function: static::METHOD_NAME_REQUEST,
            pre: function (Client $client, array $params): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $instrumentation = CachedInstrumentation::getCachedInstrumentation();
                $request = new RequestProcessor();
                $context = Context::getCurrent();

                $span = $instrumentation->tracer()
                    ->spanBuilder(static::SPAN_NAME_REQUEST)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_SEARCH_INDEX, $params[0])
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, serialize($params[2]))
                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get(static::HEADER_HOST))
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (Client $client, array $params, $response, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute(static::ATTRIBUTE_QUERY_TIME, $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );

        hook(
            class: Client::class,
            function: static::METHOD_NAME_UPDATE_DOCUMENTS,
            pre: function (Client $client, array $params): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $instrumentation = CachedInstrumentation::getCachedInstrumentation();
                $request = new RequestProcessor();
                $context = Context::getCurrent();

                $indexes = static::getIndexesIndexedByIdsFromDocuments($params[0]);

                $span = $instrumentation->tracer()
                    ->spanBuilder(static::SPAN_NAME_UPDATE_DOCUMENTS)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_SEARCH_INDEXES, implode(',', $indexes))
                    ->setAttribute(static::ATTRIBUTE_SEARCH_IDS, implode(',', array_keys($indexes)))
                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get(static::HEADER_HOST))
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (Client $client, array $params, $response, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute(static::ATTRIBUTE_QUERY_TIME, $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );

        hook(
            class: Client::class,
            function: static::METHOD_NAME_ADD_DOCUMENTS,
            pre: function (Client $client, array $params): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $instrumentation = CachedInstrumentation::getCachedInstrumentation();
                $request = new RequestProcessor();
                $context = Context::getCurrent();

                $indexes = static::getIndexesIndexedByIdsFromDocuments($params[0]);

                $span = $instrumentation->tracer()
                    ->spanBuilder(static::SPAN_NAME_ADD_DOCUMENTS)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_SEARCH_INDEXES, implode(',', $indexes))
                    ->setAttribute(static::ATTRIBUTE_SEARCH_IDS, implode(',', array_keys($indexes)))
                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get(static::HEADER_HOST))
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (Client $client, array $params, $response, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute(static::ATTRIBUTE_QUERY_TIME, $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );

        hook(
            class: Client::class,
            function: static::METHOD_NAME_UPDATE_DOCUMENT,
            pre: function (Client $client, array $params): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $instrumentation = CachedInstrumentation::getCachedInstrumentation();
                $request = new RequestProcessor();
                $context = Context::getCurrent();

                $span = $instrumentation->tracer()
                    ->spanBuilder(static::SPAN_NAME_UPDATE_DOCUMENT)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_SEARCH_ID, $params[0])
                    ->setAttribute(static::ATTRIBUTE_SEARCH_INDEX, $params[2])
                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get(static::HEADER_HOST))
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (Client $client, array $params, $response, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute(static::ATTRIBUTE_QUERY_TIME, $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );

        hook(
            class: Client::class,
            function: static::METHOD_NAME_DELETE_DOCUMENTS,
            pre: function (Client $client, array $params): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $instrumentation = CachedInstrumentation::getCachedInstrumentation();
                $request = new RequestProcessor();
                $context = Context::getCurrent();

                $indexes = static::getIndexesIndexedByIdsFromDocuments($params[0]);

                $span = $instrumentation->tracer()
                    ->spanBuilder(static::SPAN_NAME_DELETE_DOCUMENTS)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_SEARCH_INDEXES, implode(',', $indexes))
                    ->setAttribute(static::ATTRIBUTE_SEARCH_IDS, implode(',', array_keys($indexes)))
                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get(static::HEADER_HOST))
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (Client $client, array $params, $response, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute(static::ATTRIBUTE_QUERY_TIME, $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );

        hook(
            class: Client::class,
            function: static::METHOD_NAME_DELETE_IDS,
            pre: function (Client $client, array $params): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $instrumentation = CachedInstrumentation::getCachedInstrumentation();
                $request = new RequestProcessor();
                $context = Context::getCurrent();

                $indexes = static::getIndexesIndexedByIdsFromDocuments($params[0]);

                $span = $instrumentation->tracer()
                    ->spanBuilder(static::SPAN_NAME_DELETE_IDS)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_SEARCH_INDEXES, implode(',', $indexes))
                    ->setAttribute(static::ATTRIBUTE_SEARCH_IDS, implode(',', array_keys($indexes)))
                    ->setAttribute(TraceAttributes::URL_FULL, $request->getRequest()->getUri())
                    ->setAttribute(TraceAttributes::URL_DOMAIN, $request->getRequest()->headers->get(static::HEADER_HOST))
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (Client $client, array $params, $response, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception !== null) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setAttribute(static::ATTRIBUTE_QUERY_TIME, $response->getQueryTime());
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );
    }

    /**
     * @param array<\Elastica\Document> $documents
     *
     * @return array<string, string>
     */
    protected static function getIndexesIndexedByIdsFromDocuments(array $documents): array
    {
        $indexes = [];
        foreach ($documents as $document) {
            $indexes[$document->getId()] = $document->getIndex();
        }

        return $indexes;
    }
}
