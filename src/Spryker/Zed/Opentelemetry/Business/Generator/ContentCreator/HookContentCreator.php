<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\ContentCreator;

use Spryker\Zed\Opentelemetry\OpentelemetryConfig;

class HookContentCreator implements HookContentCreatorInterface
{
    /**
     * @var string
     */
    protected const NAMESPACE_KEY = 'namespace';

    /**
     * @var string
     */
    protected const CLASS_NAME_KEY = 'className';

    /**
     * @var string
     */
    protected const MODULE_KEY = 'module';

    /**
     * @var string
     */
    protected const METHODS_KEY = 'methods';

    /**
     * @var \Spryker\Zed\Opentelemetry\OpentelemetryConfig
     */
    protected OpentelemetryConfig $config;

    /**
     * @param \Spryker\Zed\Opentelemetry\OpentelemetryConfig $config
     */
    public function __construct(OpentelemetryConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @param array<string, array<string>> $class
     *
     * @return string
     */
    public function createHookContent(array $class): string
    {
        $hooks = [];
        $envVars = $this->config->getOtelEnvVars();

        foreach ($class[static::METHODS_KEY] as $method) {
            $hooks[] = '
            \\OpenTelemetry\\Instrumentation\\hook(
                class: \\' . $class[static::NAMESPACE_KEY] . '\\' . $class[static::CLASS_NAME_KEY] . '::class,
                function: \'' . $method . '\',
                pre: static function ($instance, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                    $context = \\OpenTelemetry\\Context\\Context::getCurrent();
                    $envVars = ' . var_export($envVars, true) . ';

                    $extractTraceIdFromEnv = function(array $envVars): array {
                        foreach ($envVars as $key => $envVar) {
                            if (defined($envVar)) {
                                return [$key => constant($envVar)];
                            }
                        }
                        return [];
                    };

                    $traceId = $extractTraceIdFromEnv($envVars);
                    if ($traceId !== []) {
                        $context = \\OpenTelemetry\\API\\Trace\\Propagation\\TraceContextPropagator::getInstance()->extract($traceId);
                    }

                    $type = $params[1] ?? \\Symfony\\Component\\HttpKernel\\HttpKernelInterface::MAIN_REQUEST;

                    $request = \\Spryker\\Zed\\OpenTelemetry\\Business\\Generator\\Request\\RequestProcessor::getRequest();

                    $span = \\Spryker\\Zed\\OpenTelemetry\\Business\\Generator\\Instrumentation\\CachedInstrumentation::getCachedInstrumentation()
                        ->tracer()
                        ->spanBuilder($request->getMethod() . \' ' . $class[static::MODULE_KEY] . '-' . $class[static::CLASS_NAME_KEY] . '::' . $method . '\')
                        ->setParent($context)
                        ->setSpanKind(($type === \\Symfony\\Component\\HttpKernel\\HttpKernelInterface::SUB_REQUEST) ? \\OpenTelemetry\\API\\Trace\\SpanKind::KIND_INTERNAL : \\OpenTelemetry\\API\\Trace\\SpanKind::KIND_SERVER)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_FUNCTION, $function)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_NAMESPACE, $class)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_FILEPATH, $filename)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_LINENO, $lineno)
                        ->startSpan();

                    \\OpenTelemetry\\Context\\Context::storage()->attach($span->storeInContext(\\OpenTelemetry\\Context\\Context::getCurrent()));
                },

                post: static function ($instance, array $params, $returnValue, ?\Throwable $exception) {
                    $scope = \\OpenTelemetry\\Context\\Context::storage()->scope();

                    if (null === $scope) {
                        return;
                    }

                    $error = error_get_last();

                    if (is_array($error) && in_array($error[\'type\'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                        $exception = new \Exception(
                            sprintf(\'Error: %s in %s on line %d\', $error[\'message\'], $error[\'file\'], $error[\'line\'])
                        );
                    }

                    $scope->detach();
                    $span = \\OpenTelemetry\\API\\Trace\\Span::fromContext($scope->context());

                    if (isset($exception)) {
                        $span->recordException($exception);
                    }

                    $span->setAttribute(\'error_message\', isset($exception) ? $exception->getMessage() : \'\');
                    $span->setAttribute(\'error_code\', isset($exception) ? $exception->getCode() : \'\');
                    $span->setStatus(isset($exception) ? \\OpenTelemetry\\API\\Trace\\StatusCode::STATUS_ERROR : \\OpenTelemetry\\API\\Trace\\StatusCode::STATUS_OK);

                    $span->end();
                }
            );';
        }

        return '<?php' . PHP_EOL . implode(PHP_EOL, $hooks) . PHP_EOL;
    }
}
