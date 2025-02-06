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
use Redis;
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
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, isset($params[0]) ? 'GET ' . $params[0] : 'undefined')
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
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', $params[0]))
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
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(static::ATTRIBUTE_EVAL_SCRIPT, $params[0] ?? 'undefined')
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
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', array_keys($params[0])))
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
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $params[0] ?? 'undefined')
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
            Redis::class,
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
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $params[0] ?? 'undefined')
                    ->setAttribute(static::PARAM_EXPIRATION, $params[0] ?? 'undefined')
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
}
