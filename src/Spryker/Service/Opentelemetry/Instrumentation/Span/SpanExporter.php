<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Span;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use Opentelemetry\Proto\Collector\Trace\V1\ExportTraceServiceResponse;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Throwable;

class SpanExporter implements SpanExporterInterface
{
    use LogsMessagesTrait;

    public function __construct(
        protected TransportInterface $transport,
        protected SpanConverter $spanConverter,
    )
    {
    }

    /**
     * @param iterable $batch
     * @param \OpenTelemetry\SDK\Common\Future\CancellationInterface|null $cancellation
     *
     * @return \OpenTelemetry\SDK\Common\Future\FutureInterface
     */
    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        return $this->transport
            ->send($this->spanConverter->convert($batch)->serializeToString(), $cancellation)
            ->map(function (?string $payload): bool {
                if ($payload === null) {
                    return true;
                }

                $serviceResponse = new ExportTraceServiceResponse();
                $serviceResponse->mergeFromString($payload);

                $partialSuccess = $serviceResponse->getPartialSuccess();
                if ($partialSuccess !== null && $partialSuccess->getRejectedSpans()) {
                    self::logError('Export partial success', [
                        'rejected_spans' => $partialSuccess->getRejectedSpans(),
                        'error_message' => $partialSuccess->getErrorMessage(),
                    ]);

                    return false;
                }
                if ($partialSuccess !== null && $partialSuccess->getErrorMessage()) {
                    self::logWarning('Export success with warnings/suggestions', ['error_message' => $partialSuccess->getErrorMessage()]);
                }

                return true;
            })
            ->catch(static function (Throwable $throwable): bool {
                self::logError('Export failure', ['exception' => $throwable]);

                return false;
            });
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->transport->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->transport->forceFlush($cancellation);
    }
}
