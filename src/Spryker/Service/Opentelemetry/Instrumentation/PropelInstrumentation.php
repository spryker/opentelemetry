<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\TraceAttributes;
use Propel\Runtime\Connection\StatementInterface;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\TraceSampleResult;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class PropelInstrumentation
{
    /**
     * @var string
     */
    protected const NAME = 'spryker_otel_propel';

    /**
     * @var string
     */
    protected const SPAN_NAME_PATTERN = '%s...';

    /**
     * @var string
     */
    protected const METHOD_NAME = 'execute';

    /**
     * @return void
     */
    public static function register(): void
    {
        //BC check
        if (class_exists('\Spryker\Service\OtelPropelInstrumentation\OpenTelemetry\PropelInstrumentation')) {
            return;
        }

        if (Sdk::isInstrumentationDisabled(static::NAME) === true) {
            return;
        }

        $dbEngine = strtolower((getenv('SPRYKER_DB_ENGINE') ?: '')) ?: 'mysql';

        hook(
            class: StatementInterface::class,
            function: static::METHOD_NAME,
            pre: function (StatementInterface $statement, array $params) use ($dbEngine): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $context = Context::getCurrent();

                $query = $statement->getStatement()->queryString;
                $criticalAttr = str_contains($query, 'SELECT') ? CriticalSpanRatioSampler::NO_CRITICAL_ATTRIBUTE : CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE;
                $span = CachedInstrumentation::getCachedInstrumentation()
                    ->tracer()
                    ->spanBuilder(sprintf(static::SPAN_NAME_PATTERN, substr($query, 0, 20)))
                    ->setParent($context)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, $dbEngine)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $query)
                    ->setAttribute($criticalAttr, true)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (StatementInterface $statement, array $params, $response, ?Throwable $exception): void {
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
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            }
        );
    }
}
