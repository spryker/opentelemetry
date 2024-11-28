<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Plugin;

use Spryker\Service\Kernel\AbstractPlugin;
use Spryker\Service\MonitoringExtension\Dependency\Plugin\MonitoringExtensionPluginInterface;

/**
 * @method \Spryker\Service\Opentelemetry\OpentelemetryService getService()
 */
class OpentelemetryMonitoringExtensionPlugin extends AbstractPlugin implements MonitoringExtensionPluginInterface
{
    /**
     * Specification:
     * - Will be fixed in stable version. Not in use for now
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
    }

    /**
     * Specification:
     * - Will be fixed in stable version. Not in use for now
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
    }

    /**
     * Specification:
     * - Sets name for the root span. If no name provided, default name will be generated.
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
     * - Will be fixed in stable version. Not in use for now
     *
     * @api
     *
     * @return void
     */
    public function markStartTransaction(): void
    {
    }

    /**
     * Specification:
     * - Will be fixed in stable version. Not in use for now
     *
     * @api
     *
     * @return void
     */
    public function markEndOfTransaction(): void
    {
    }

    /**
     * Specification:
     * - Will be fixed in stable version. Not in use for now
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
     * - Will be fixed in stable version. Not in use for now
     *
     * @api
     *
     * @return void
     */
    public function markAsConsoleCommand(): void
    {
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
     * - Will be fixed in stable version. Not in use for now
     *
     * @api
     *
     * @param string $tracer
     *
     * @return void
     */
    public function addCustomTracer(string $tracer): void
    {
    }
}
