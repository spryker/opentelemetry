<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\PromiseInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\ResponseInterface;
use Spryker\Service\Opentelemetry\Instrumentation\Propagation\PsrRequestPropagationSetter;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class GuzzleInstrumentation
{
    /**
     * @var string
     */
    protected const NAME = 'spryker_otel_guzzle';

    /**
     * @var string
     */
    protected const HEADER_USER_AGENT = 'User-Agent';

    /**
     * @return void
     */
    public static function register(): void
    {
        //BC check
        if (class_exists('\OpenTelemetry\Contrib\Instrumentation\Guzzle\GuzzleInstrumentation')) {
            return;
        }

        if (Sdk::isInstrumentationDisabled(static::NAME) === true) {
            return;
        }

        $instrumentation = CachedInstrumentation::getCachedInstrumentation();

        hook(
            Client::class,
            'transfer',
            pre: static function (Client $guzzleClient, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): array {
                $context = Context::getCurrent();
                /** @var \Psr\Http\Message\RequestInterface $request */
                $request = $params[0];
                $uriObject = $request->getUri();
                $method = $request->getMethod();
                $url = (string)$uriObject;

                $span = $instrumentation->tracer()
                    ->spanBuilder(sprintf('Guzzle %s %s', $method, $url))
                    ->setSpanKind(SpanKind::KIND_CLIENT)
                    ->setParent($context)
                    ->setAttribute(CriticalSpanRatioSampler::IS_SYSTEM_ATTRIBUTE, true)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION_NAME, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINE_NUMBER, $lineno)
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, $uriObject->getHost())
                    ->setAttribute(TraceAttributes::SERVER_PORT, $uriObject->getPort() ?: 80)
                    ->setAttribute(TraceAttributes::URL_PATH, $uriObject->getPath())
                    ->setAttribute(TraceAttributes::URL_FULL, $url)
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $method)
                    ->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $request->getProtocolVersion())
                    ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine(static::HEADER_USER_AGENT))
                    ->startSpan();

                $propagator = TraceContextPropagator::getInstance();
                $context = $span->storeInContext($context);
                if (OpentelemetryInstrumentationConfig::getIsDistributedTracingEnabled()) {
                    $propagator->inject($request, new PsrRequestPropagationSetter(), $context);
                }

                Context::storage()->attach($context);

                return [$request];
            },
            post: static function (Client $guzzleClient, array $params, PromiseInterface $promise, ?Throwable $exception): void {
                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();

                $span = Span::fromContext($scope->context());

                if ($exception) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                    $span->setAttribute(TraceAttributes::ERROR_TYPE, get_class($exception));
                    if ($exception instanceof BadResponseException) {
                        $response = $exception->getResponse();
                        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                    }
                    $span->end();

                    return;
                }

                $promise->then(
                    onFulfilled: function (ResponseInterface $response) use ($span) {
                        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());

                        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 600) {
                            $span->setStatus(StatusCode::STATUS_ERROR);
                        } else {
                            $span->setStatus(StatusCode::STATUS_OK);
                        }
                        $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                        $span->end();

                        return $response;
                    },
                    onRejected: function (Throwable $exception) use ($span) {
                        $span->recordException($exception);
                        $span->setStatus(StatusCode::STATUS_ERROR);
                        $span->setAttribute(TraceAttributes::ERROR_TYPE, get_class($exception));
                        if ($exception instanceof BadResponseException) {
                            $response = $exception->getResponse();
                            $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                        }
                        $span->end();

                        throw $exception;
                    }
                );
            },
        );
    }
}
