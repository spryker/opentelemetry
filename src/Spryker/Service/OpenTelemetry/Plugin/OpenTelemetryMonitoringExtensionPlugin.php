<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Spryker\Service\OpenTelemetry\Plugin;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use Spryker\Service\MonitoringExtension\Dependency\Plugin\MonitoringExtensionPluginInterface;

class OpenTelemetryMonitoringExtensionPlugin implements MonitoringExtensionPluginInterface
{
    /**
     * @var string
     */
    protected $application;

    /**
     * @var bool
     */
    protected $isActive;

    protected TracerInterface $tracer;

    protected ?SpanInterface $currentSpan = null;

    public function __construct()
    {
//        $this->tracer = Globals::tracerProvider()->getTracer(
//            'Spryker Opentelemetry Monitoring Plugin',
//            '0.0.1',
//            'https://opentelemetry.io/schemas/1.24.0'
//        );
    }

    /**
     * @param string $message
     * @param \Exception|\Throwable $exception
     *
     * @return void
     */
    public function setError(string $message, $exception): void
    {
    }

    /**
     * @param string|null $application
     * @param string|null $store
     * @param string|null $environment
     *
     * @return void
     */
    public function setApplicationName(?string $application = null, ?string $store = null, ?string $environment = null): void
    {
        $this->applicationName = $application . '-' . $store . ' (' . $environment . ')';
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setTransactionName(string $name): void
    {
    }

    /**
     * @return void
     */
    public function markStartTransaction(): void
    {
    }

    /**
     * @return void
     */
    public function markEndOfTransaction(): void
    {
    }

    /**
     * @return void
     */
    public function markIgnoreTransaction(): void
    {
    }

    /**
     * @return void
     */
    public function markAsConsoleCommand(): void
    {
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function addCustomParameter(string $key, $value): void
    {
    }

    /**
     * @param string $tracer
     *
     * @return void
     */
    public function addCustomTracer(string $tracer): void
    {
    }
}
