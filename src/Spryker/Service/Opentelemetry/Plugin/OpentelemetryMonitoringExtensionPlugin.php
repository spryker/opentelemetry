<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Plugin;

use OpenTelemetry\Context\Context;
use Spryker\Service\Kernel\AbstractPlugin;
use Spryker\Service\MonitoringExtension\Dependency\Plugin\MonitoringExtensionPluginInterface;
use Spryker\Service\Opentelemetry\Instrumentation\Span\Span;

/**
 * @method \Spryker\Service\Opentelemetry\OpentelemetryService getService()
 */
class OpentelemetryMonitoringExtensionPlugin extends AbstractPlugin implements MonitoringExtensionPluginInterface
{
    /**
     * Specification:
     * - Adds error to the current active span.
     *
     * @api
     *
     * @param string $message
     * @param \Exception|\Throwable $exception
     *
     * @return void
     */
    public function setError(string $message, $exception): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }

        $span = Span::fromContext($scope->context());
        $span->recordException($exception);
    }

    /**
     * Specification:
     * - Sets Service name for traces. If no application name was provided, default one will be used.
     *
     * @api
     *
     * @param string|null $application
     * @param string|null $store
     * @param string|null $environment
     *
     * @return void
     */
    public function setApplicationName(?string $application = null, ?string $store = null, ?string $environment = null): void
    {
        $this->getService()->setResourceName(sprintf('%s-%s (%s)', $application ?? '', $store ?? '', $environment ?? ''));
    }

    /**
     * Specification:
     * - Sets name for the root span. If no name was provided, default name will be generated.
     * - This will affect only root span as it will not update the current possible span name.
     *
     * @api
     *
     * @param string $name
     *
     * @return void
     */
    public function setTransactionName(string $name): void
    {
        $this->getService()->setRootSpanName($name);
    }

    /**
     * Specification:
     * - Opentelemetry instrumentation doesn't require additional calls to start ot end transaction methods.
     *
     * @api
     *
     * @return void
     */
    public function markStartTransaction(): void
    {
        return;
    }

    /**
     * Specification:
     * - Opentelemetry instrumentation doesn't require additional calls to start ot end transaction methods.
     *
     * @api
     *
     * @return void
     */
    public function markEndOfTransaction(): void
    {
        return;
    }

    /**
     * Specification:
     * - Transaction should be ignored before it was started in Opentelemetery in order to not generate additional load to the system.
     *
     * @api
     *
     * @return void
     */
    public function markIgnoreTransaction(): void
    {
    }

    /**
     * Specification:
     * - Opentelemetry has no attributes to specify background jobs. Service name already defined for CLI commands.
     *
     * @api
     *
     * @return void
     */
    public function markAsConsoleCommand(): void
    {
        return;
    }

    /**
     * Specification:
     * - Adds a custom parameter to the monitoring.
     *
     * @api
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function addCustomParameter(string $key, $value): void
    {
        $this->getService()->setCustomParameter($key, $value);
    }

    /**
     * Specification:
     * - Opentelemetry instrumentation is completely custom.
     *
     * @api
     *
     * @param string $tracer
     *
     * @return void
     */
    public function addCustomTracer(string $tracer): void
    {
        return;
    }
}
