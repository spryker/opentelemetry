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
use Propel\Runtime\Connection\StatementWrapper;
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
     * @var string
     */
    protected const UNDEFINED = 'undefined';

    /**
     * @var string
     */
    protected const TABLE_NAME_REGEX = '/(?<=\b%s\s)([\w-]+)/is';

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

        $dbHost = getenv('SPRYKER_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
        $dbPort = getenv('SPRYKER_DB_PORT') ?: getenv('DB_PORT') ?: 3306;
        $dbName = getenv('SPRYKER_DB_DATABASE') ?: getenv('DB_DATABASE') ?: static::UNDEFINED;
        $dbEngine = strtolower((getenv('SPRYKER_DB_ENGINE') ?: getenv('DB_ENGINE')) ?: 'mysql');

        hook(
            class: StatementInterface::class,
            function: static::METHOD_NAME,
            pre: function (StatementInterface $statement, array $params) use ($dbEngine, $dbHost, $dbPort, $dbName): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }

                $context = Context::getCurrent();

                $query = $statement->getStatement()->queryString;

                $span = CachedInstrumentation::getCachedInstrumentation()
                    ->tracer()
                    ->spanBuilder(sprintf(static::SPAN_NAME_PATTERN, substr($query, 0, 20)))
                    ->setParent($context)
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, $dbEngine)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $query)
                    ->setAttributes(static::getQueryAttributes($statement))
                    // OTEL 1.36.0 attributes:
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $dbName)
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, $dbHost)
                    ->setAttribute(TraceAttributes::SERVER_PORT, (int)$dbPort)
                    ->setAttribute(TraceAttributes::NETWORK_PEER_ADDRESS, $dbHost)
                    ->setAttribute(TraceAttributes::NETWORK_PEER_PORT, (int)$dbPort)
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

                $span->setAttribute(
                    TraceAttributes::DB_RESPONSE_RETURNED_ROWS,
                    $statement->rowCount(),
                );

                if ($exception !== null) {
                    $errorCode = static::extractDatabaseErrorCode($exception);
                    if ($errorCode !== null) {
                        $span->setAttribute(TraceAttributes::DB_RESPONSE_STATUS_CODE, $errorCode);
                    }
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                } else {
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            },
        );
    }

    /**
     * @param \Propel\Runtime\Connection\StatementWrapper $statement
     *
     * @return array
     */
    protected static function getQueryAttributes(StatementWrapper $statement): array
    {
        $operations = [
            'INSERT' => 'INTO',
            'DELETE' => 'FROM',
            'SELECT' => 'FROM',
            'UPDATE' => 'UPDATE',
        ];

        $matchedOperation = static::UNDEFINED;
        $tableName = static::UNDEFINED;
        $query = str_replace('`', '', $statement->getStatement()->queryString);

        foreach ($operations as $operation => $searchTerm) {
            if (str_contains($query, $operation)) {
                preg_match(
                    sprintf(self::TABLE_NAME_REGEX, $searchTerm),
                    $query,
                    $matches,
                );
                $tableName = $matches[0] ?? static::UNDEFINED;
                $matchedOperation = $operation;

                break;
            }
        }

        $criticalAttr = $matchedOperation === 'SELECT'
            ? CriticalSpanRatioSampler::NO_CRITICAL_ATTRIBUTE : CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE;

        return [
            TraceAttributes::DB_OPERATION_NAME => $matchedOperation,
            TraceAttributes::DB_COLLECTION_NAME => $tableName,
            TraceAttributes::DB_QUERY_SUMMARY => $matchedOperation . ' ' . $tableName,
            $criticalAttr => true,
        ];
    }

    /**
     * @param \Throwable $exception
     * @return string|null
     */
    protected static function extractDatabaseErrorCode(Throwable $exception): ?string
    {
        $message = $exception->getMessage();
        // MySQL SQLSTATE
        if (preg_match('/SQLSTATE\\[([^\\]]+)\\]/', $message, $matches)) {
            return $matches[1];
        }
        // MySQL numeric code
        if (preg_match('/: (\d{4})/', $message, $matches)) {
            return $matches[1];
        }
        // PostgreSQL code
        if (preg_match('/ERROR:\\s*([A-Z0-9]{5})/', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
