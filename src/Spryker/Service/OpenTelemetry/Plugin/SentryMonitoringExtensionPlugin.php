<?php

declare(strict_types = 1);

namespace ValanticSpryker\Service\Sentry\Plugin\Monitoring;

use Sentry\Tracing\TransactionContext;
use Spryker\Service\Kernel\AbstractPlugin;
use Spryker\Service\MonitoringExtension\Dependency\Plugin\MonitoringExtensionPluginInterface;

/**
 * @method \ValanticSpryker\Service\Sentry\SentryService getService()
 */
class SentryMonitoringExtensionPlugin extends AbstractPlugin implements MonitoringExtensionPluginInterface
{
    /**
     * @var string
     */
    protected $transactionName = '';

    /**
     * @param string $message
     * @param \Exception|\Throwable $exception
     *
     * @return void
     */
    public function setError(string $message, $exception): void
    {
        $this->getService()->captureException($exception);
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
        if ($application) {
            $this->getService()->setTag('application', $application);
        }

        if ($store) {
            $this->getService()->setTag('store', $store);
        }
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setTransactionName(string $name): void
    {
        $this->transactionName = $name;
    }

    /**
     * @return void
     */
    public function markStartTransaction(): void
    {
        // TODO: Add sentry distributed tracing
        $context = TransactionContext::fromSentryTrace('');

        $context->setOp('http.server');
        $context->setName($this->transactionName);

        $transaction = $this->getService()->startTransaction($context);
        $this->getService()->setSpan($transaction);
    }

    /**
     * @return void
     */
    public function markEndOfTransaction(): void
    {
        $this->getService()->finishCurrentTransaction();
    }

    /**
     * @return void
     */
    public function markIgnoreTransaction(): void
    {
        // TODO: Implement
    }

    /**
     * @return void
     */
    public function markAsConsoleCommand(): void
    {
        $this->getService()->setTag('console', 'true');
    }

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function addCustomParameter(string $key, $value): void
    {
        if (is_string($value)) {
            $this->getService()->setTag($key, $value);
        }
    }

    /**
     * @param string $tracer
     *
     * @return void
     */
    public function addCustomTracer(string $tracer): void
    {
        // TODO: Implement
    }
}
