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
use Spryker\Client\Redis\Adapter\RedisAdapterInterface;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\TraceSampleResult;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RedisInstrumentation
{
    /**
     * @var string
     */
    public const ATTRIBUTE_EVAL_SCRIPT = 'spr.script';

    /**
     * @var string
     */
    protected const NAME = 'spryker_otel_redis';

    /**
     * @var string
     */
    protected const PARAM_EXPIRATION = 'expiration';

    /**
     * @var string
     */
    protected const DB_SYSTEM_NAME = 'redis';

    /**
     * @var string
     */
    protected const OPERATION_GET = 'GET';

    /**
     * @var string
     */
    protected const OPERATION_MGET = 'MGET';

    /**
     * @var string
     */
    protected const OPERATION_SET = 'SET';

    /**
     * @var string
     */
    protected const OPERATION_MSET = 'MSET';

    /**
     * @var string
     */
    protected const OPERATION_SETEX = 'SETEX';

    /**
     * @return void
     */
    public static function register(): void
    {
        if (Sdk::isInstrumentationDisabled(static::NAME) === true) {
            return;
        }

        $instrumentation = CachedInstrumentation::getCachedInstrumentation();

        hook(
            RedisAdapterInterface::class,
            'get',
            pre: static function (RedisAdapterInterface $redis, array $params) use ($instrumentation): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::get')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, static::DB_SYSTEM_NAME)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(
                        TraceAttributes::DB_QUERY_TEXT, isset($params[0])
                            ? static::OPERATION_GET . ' ' . $params[0]
                            : 'undefined'
                    )
                    ->setAttributes(static::getQueryAttributes(static::OPERATION_GET, $params))
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, static::getRedisHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, static::getRedisPort())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (RedisAdapterInterface $redis, array $params, $response, ?Throwable $exception) use ($redisHost, $redisPort): void {
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
            },
        );

        hook(
            RedisAdapterInterface::class,
            'mget',
            pre: static function (RedisAdapterInterface $redis, array $params) use ($instrumentation): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::mget')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, static::DB_SYSTEM_NAME)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', $params[0]))
                    ->setAttributes(static::getQueryAttributes(static::OPERATION_MGET, $params))
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, static::getRedisHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, static::getRedisPort())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (RedisAdapterInterface $redis, array $params, mixed $ret, ?Throwable $exception): void {
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
            },
        );

        hook(
            RedisAdapterInterface::class,
            'eval',
            pre: static function (RedisAdapterInterface $redis, array $params) use ($instrumentation): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::eval')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, static::DB_SYSTEM_NAME)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_EVAL_SCRIPT, $params[0] ?? 'undefined')
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, static::getRedisHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, static::getRedisPort())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (RedisAdapterInterface $redis, array $params, mixed $ret, ?Throwable $exception): void {
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
            },
        );

        hook(
            RedisAdapterInterface::class,
            'mset',
            pre: static function (RedisAdapterInterface $redis, array $params) use ($instrumentation): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::mset')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, static::DB_SYSTEM_NAME)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', array_keys($params[0])))
                    ->setAttributes(static::getQueryAttributes(static::OPERATION_MSET, $params))
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, static::getRedisHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, static::getRedisPort())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (RedisAdapterInterface $redis, array $params, mixed $ret, ?Throwable $exception): void {
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
            },
        );

        hook(
            RedisAdapterInterface::class,
            'set',
            pre: static function (RedisAdapterInterface $redis, array $params) use ($instrumentation): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::set')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, static::DB_SYSTEM_NAME)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $params[0] ?? 'undefined')
                    ->setAttributes(static::getQueryAttributes(static::OPERATION_SET, $params))
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, static::getRedisHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, static::getRedisPort())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (RedisAdapterInterface $redis, array $params, mixed $ret, ?Throwable $exception): void {
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
            },
        );

        hook(
            RedisAdapterInterface::class,
            'setex',
            pre: static function (RedisAdapterInterface $redis, array $params) use ($instrumentation): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::set')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_SYSTEM_NAME, static::DB_SYSTEM_NAME)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $params[0] ?? 'undefined')
                    ->setAttribute(static::PARAM_EXPIRATION, $params[0] ?? 'undefined')
                    ->setAttributes(static::getQueryAttributes(static::OPERATION_SETEX, $params))
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, static::getRedisHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, static::getRedisPort())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (RedisAdapterInterface $redis, array $params, mixed $ret, ?Throwable $exception): void {
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
            },
        );
    }

    /**
     * @param string $operation
     * @param array<string> $params
     *
     * @return array
     */
    protected static function getQueryAttributes(string $operation, array $params): array
    {
        $tablename = 'undefined';

        if (isset($params[0]) && is_string($params[0])) {
            $split = explode(':', $params[0]);
            $tablename = $split[0] ?? 'undefined';
        }

        return [
            TraceAttributes::DB_OPERATION_NAME => $operation,
            TraceAttributes::DB_COLLECTION_NAME => $tablename,
            TraceAttributes::DB_QUERY_SUMMARY => $operation . ' ' . $tablename,
        ];
    }

    /**
     * @return string
     */
    protected static function getRedisHost(): string
    {
        return getenv('SPRYKER_KEY_VALUE_STORE_HOST') ?: 'localhost';
    }

    /**
     * @return int
     */
    protected static function getRedisPort(): int
    {
        return getenv('SPRYKER_KEY_VALUE_STORE_PORT') ?: 6379;
    }
}
