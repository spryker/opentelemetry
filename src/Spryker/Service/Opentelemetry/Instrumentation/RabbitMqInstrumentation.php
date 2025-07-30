<?php

/**
 * Copyright 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Instrumentation;

use Generated\Shared\Transfer\EventQueueSendMessageBodyTransfer;
use Generated\Shared\Transfer\QueueSendMessageTransfer;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SemConv\TraceAttributes;
use Spryker\Client\RabbitMq\Model\RabbitMqAdapter;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\CriticalSpanRatioSampler;
use Spryker\Service\Opentelemetry\Instrumentation\Sampler\TraceSampleResult;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;
use Spryker\Shared\Opentelemetry\Instrumentation\CachedInstrumentation;
use Spryker\Shared\Opentelemetry\Request\RequestProcessor;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

class RabbitMqInstrumentation
{
    /**
     * @var string
     */
    protected const NAME = 'spryker_otel_rabbitmq';

    /**
     * @var string
     */
    protected const HEADER_HOST = 'host';

    /**
     * @var string
     */
    protected const ATTRIBUTE_EVENT_LISTENER_CLASS_NAME = 'spr.event.listenerClassName';

    /**
     * @var string
     */
    protected const SPAN_NAME_SEND_MESSAGES = 'rabbitmq-sendMessages';

    /**
     * @var string
     */
    protected const FUNCTION_SEND_MESSAGES = 'sendMessages';

    /**
     * @var string
     */
    protected const SPAN_NAME_SEND_MESSAGE = 'rabbitmq-sendMessage';

    /**
     * @var string
     */
    protected const FUNCTION_SEND_MESSAGE = 'sendMessage';

    /**
     * @var string
     */
    protected const FUNCTION_RECEIVE_MESSAGE = 'receiveMessage';

    /**
     * @var string
     */
    protected const FUNCTION_RECEIVE_MESSAGES = 'receiveMessages';

    /**
     * @var string
     */
    protected const SPAN_NAME_RECEIVE_MESSAGE = 'rabbitmq-receiveMessage';

    /**
     * @var string
     */
    protected const SPAN_NAME_RECEIVE_MESSAGES = 'rabbitmq-receiveMessages';

    /**
     * @var string
     */
    protected const MESSAGING_SYSTEM_VALUE = 'rabbitmq';

    /**
     * @return void
     */
    public static function register(): void
    {
        $functions = [
            static::FUNCTION_SEND_MESSAGE => [
                static::SPAN_NAME_SEND_MESSAGE,
                SpanKind::KIND_PRODUCER,
            ],
            static::FUNCTION_SEND_MESSAGES => [
                static::SPAN_NAME_SEND_MESSAGES,
                SpanKind::KIND_PRODUCER,
            ],
            static::FUNCTION_RECEIVE_MESSAGE => [
                static::SPAN_NAME_RECEIVE_MESSAGE,
                SpanKind::KIND_CONSUMER,
            ],
            static::FUNCTION_RECEIVE_MESSAGES => [
                static::SPAN_NAME_RECEIVE_MESSAGES,
                SpanKind::KIND_CONSUMER,
            ],
        ];

        foreach ($functions as $function => $spanName) {
            static::registerHook($function, $spanName[0], $spanName[1]);
        }
    }

    /**
     * @param string $functionName
     * @param string $spanName
     * @param int $spanKind
     *
     * @return void
     */
    protected static function registerHook(string $functionName, string $spanName, int $spanKind): void
    {
        //BC check
        if (class_exists('\Spryker\Service\OtelRabbitMqInstrumentation\OpenTelemetry\RabbitMqInstrumentation')) {
            return;
        }

        if (Sdk::isInstrumentationDisabled(static::NAME) === true) {
            return;
        }

        $instrumentation = CachedInstrumentation::getCachedInstrumentation();
        $request = (new RequestProcessor())->getRequest();

        $rabbitHost = getenv('SPRYKER_RABBITMQ_HOST') ?: getenv('RABBITMQ_HOST') ?: ($request->headers->get(static::HEADER_HOST) ?: 'localhost');
        $rabbitPort = getenv('SPRYKER_RABBITMQ_PORT') ?: getenv('RABBITMQ_PORT') ?: 5672;
        $rabbitProtocol = 'AMQP';

        hook(
            class: RabbitMqAdapter::class,
            function: $functionName,
            pre: function (RabbitMqAdapter $rabbitMqAdapter, array $params) use ($instrumentation, $spanName, $request, $spanKind, $rabbitHost, $rabbitPort, $rabbitProtocol): void {
                if (TraceSampleResult::shouldSkipTraceBody()) {
                    return;
                }
                $context = Context::getCurrent();
                $span = $instrumentation->tracer()
                    ->spanBuilder($spanName)
                    ->setSpanKind($spanKind)
                    ->setParent($context)
                    ->setAttribute(TraceAttributes::MESSAGING_SYSTEM, static::MESSAGING_SYSTEM_VALUE)
                    ->setAttribute(CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, true)
                    ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
                    ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_NAME, $params[0])
                    // OTEL 1.36.0 attributes:
                    ->setAttribute(TraceAttributes::SERVER_ADDRESS, $rabbitHost)
                    ->setAttribute(TraceAttributes::SERVER_PORT, (int)$rabbitPort)
                    ->setAttribute(TraceAttributes::NETWORK_PEER_ADDRESS, $rabbitHost)
                    ->setAttribute(TraceAttributes::NETWORK_PEER_PORT, (int)$rabbitPort)
                    ->setAttribute(TraceAttributes::MESSAGING_DESTINATION_KIND, 'queue')
                    ->setAttribute(TraceAttributes::MESSAGING_PROTOCOL, $rabbitProtocol)
                    ->setAttribute(TraceAttributes::MESSAGING_URL, sprintf('amqp://%s:%s', $rabbitHost, $rabbitPort));

                if (static::isValidMessage($params)) {
                    $eventQueueSendMessageBodyArray = json_decode($params[1][0]->getBody(), true);
                    if (array_key_exists(EventQueueSendMessageBodyTransfer::EVENT_NAME, $eventQueueSendMessageBodyArray)) {
                        $span->setAttribute(TraceAttributes::EVENT_NAME, $eventQueueSendMessageBodyArray[EventQueueSendMessageBodyTransfer::EVENT_NAME]);
                    }
                    if (array_key_exists(EventQueueSendMessageBodyTransfer::LISTENER_CLASS_NAME, $eventQueueSendMessageBodyArray)) {
                        $span->setAttribute(static::ATTRIBUTE_EVENT_LISTENER_CLASS_NAME, $eventQueueSendMessageBodyArray[EventQueueSendMessageBodyTransfer::LISTENER_CLASS_NAME]);
                    }
                }
                $span = $span->setAttribute(TraceAttributes::URL_DOMAIN, $rabbitHost)
                    ->startSpan();

                Context::storage()->attach($span->storeInContext($context));
            },
            post: function (RabbitMqAdapter $rabbitMqAdapter, array $params, $response, ?Throwable $exception) use ($rabbitHost, $rabbitPort): void {
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
                    $span->setAttribute(TraceAttributes::ERROR_TYPE, get_class($exception));
                } else {
                    $span->setStatus(StatusCode::STATUS_OK);
                }

                $span->end();
            }
        );
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return bool
     */
    protected static function isValidMessage(array $params): bool
    {
        $queueSendMessageTransfer = $params[1][0] ?? null;

        if ($queueSendMessageTransfer === null) {
            return false;
        }

        return $queueSendMessageTransfer instanceof QueueSendMessageTransfer
            && is_string($queueSendMessageTransfer->getBody());
    }
}
