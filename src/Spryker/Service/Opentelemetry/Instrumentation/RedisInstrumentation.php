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
    protected const NAME = 'spryker_otel_redis';

    /**
     * @var string
     */
    protected const PARAM_EVAL_SCRIPT = 'script';

    /**
     * @var string
     */
    protected const PARAM_EXPIRATION = 'expiration';

    /**
     * @return void
     */
    public static function register(): void
    {
        if (!class_exists(Redis::class) || Sdk::isInstrumentationDisabled(static::NAME) === true) {
            return;
        }

        $instrumentation = CachedInstrumentation::getCachedInstrumentation();

        hook(
            Redis::class,
            'select',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::select')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $params[0])
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            'get',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::get')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, isset($params[0]) ? 'GET ' . $params[0] : 'undefined')
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            'eval',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::eval')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(static::PARAM_EVAL_SCRIPT, $params[0] ?? 'undefined')
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            'exists',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::exists')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', $params))
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            'mset',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::mset')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', array_keys($params[0])))
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            'msetnx',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::msetnx')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, implode(' ', array_keys($params[0])))
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            'set',
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::set')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $params[0] ?? 'undefined')
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
            pre: static function (Redis $redis, array $params) use ($instrumentation): void {

                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder('Redis::set')
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::DB_QUERY_TEXT, $params[0] ?? 'undefined')
                    ->setAttribute(static::PARAM_EXPIRATION, $params[0] ?? 'undefined')
                    ->setAttribute(TraceAttributes::DB_NAMESPACE, $redis->getDBNum())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: static function (Redis $redis, array $params, mixed $ret, ?Throwable $exception): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $span = Span::fromContext(Context::getCurrent());

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
