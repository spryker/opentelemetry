<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Log\Processor;

use Monolog\Processor\ProcessorInterface;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\SDK\Sdk;
use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Spryker\Shared\Opentelemetry\Reader\ResourceNameReaderInterface;

class OpentelemetryLogProcessor implements ProcessorInterface
{
    protected const string RECORD_CONTEXT = 'context';

    protected const string FIELD_TRACE_ID = 'trace_id';

    protected const string FIELD_SPAN_ID = 'span_id';

    protected const string FIELD_SERVICE_NAME = 'service.name';

    protected const string FIELD_SERVICE_NAMESPACE = 'service.namespace';

    public function __construct(protected ResourceNameReaderInterface $resourceNameReader)
    {
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    public function __invoke(array $record): array
    {
        if ($this->isOtelDisabled()) {
            return $record;
        }

        $context = $record[static::RECORD_CONTEXT] ?? [];
        $spanContext = Span::getCurrent()->getContext();

        $context[static::FIELD_TRACE_ID] = $spanContext->getTraceId();
        $context[static::FIELD_SPAN_ID] = $spanContext->getSpanId();
        $context[static::FIELD_SERVICE_NAME] = $this->resolveServiceName();
        $context[static::FIELD_SERVICE_NAMESPACE] = $this->resolveServiceNamespace();

        $record[static::RECORD_CONTEXT] = $context;

        return $record;
    }

    protected function isOtelDisabled(): bool
    {
        if (!class_exists(Sdk::class)) {
            return true;
        }

        if (getenv('OTEL_SDK_DISABLED') === false) {
            return true;
        }

        if (Sdk::isDisabled()) {
            return true;
        }

        return false;
    }

    protected function resolveServiceName(): string
    {
        return $this->resourceNameReader->readName();
    }

    protected function resolveServiceNamespace(): string
    {
        return OpentelemetryInstrumentationConfig::getServiceNamespace();
    }
}
