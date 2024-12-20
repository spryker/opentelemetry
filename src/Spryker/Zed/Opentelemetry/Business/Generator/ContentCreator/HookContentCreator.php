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
     * @var string
     */
    protected const METHOD_HOOK_NAME = '%s-%s::%s';

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
     * @param array<string, mixed> $class
     *
     * @return string
     */
    public function createHookContent(array $class): string
    {
        $hooks = [];

        foreach ($class[static::METHODS_KEY] as $method) {
            $methodHookName = $this->buildMethodHookName($class, $method);
            if (in_array($methodHookName, $this->config->getExcludedSpans(), true)) {
                continue;
            }
            $hooks[] = sprintf(
                '
            \\OpenTelemetry\\Instrumentation\\hook(
                class: \\%s\\%s::class,
                function: \'%s\',
                pre: static function ($instance, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                    $context = \\OpenTelemetry\\Context\\Context::getCurrent();

                    $type = $params[1] ?? \\Symfony\\Component\\HttpKernel\\HttpKernelInterface::MAIN_REQUEST;

                    $span = \\Spryker\\Shared\\OpenTelemetry\\Instrumentation\\CachedInstrumentation::getCachedInstrumentation()
                        ->tracer()
                        ->spanBuilder(\'%s\')
                        ->setParent($context)
                        ->setSpanKind(($type === \\Symfony\\Component\\HttpKernel\\HttpKernelInterface::SUB_REQUEST) ? \\OpenTelemetry\\API\\Trace\\SpanKind::KIND_INTERNAL : \\OpenTelemetry\\API\\Trace\\SpanKind::KIND_SERVER)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_FUNCTION, $function)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_NAMESPACE, $class)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_FILEPATH, $filename)
                        ->setAttribute(\\OpenTelemetry\\SemConv\\TraceAttributes::CODE_LINENO, $lineno)
                        ->setAttribute(\\Spryker\\Service\\Opentelemetry\\Instrumentation\\Sampler\\CriticalSpanRatioSampler::IS_CRITICAL_ATTRIBUTE, %s)
                        ->startSpan();

                    \\OpenTelemetry\\Context\\Context::storage()->attach($span->storeInContext($context));
                },

                post: static function ($instance, array $params, $returnValue, ?\Throwable $exception) {
                    $scope = \\OpenTelemetry\\Context\\Context::storage()->scope();

                    if (null === $scope) {
                        return;
                    }

                    $error = error_get_last();

                    if (is_array($error) && in_array($error[\'type\'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
                        $exception = new \Exception(
                            \'Error: \' . $error[\'message\'] . \' in \' . $error[\'file\'] . \' on line \' . $error[\'line\']
                        );
                    }

                    $scope->detach();
                    $span = \\Spryker\\Service\\Opentelemetry\\Instrumentation\\Span\\Span::fromContext($scope->context());

                    if ($exception !== null) {
                        $span->recordException($exception);
                        $span->setAttribute(\'error_message\', $exception->getMessage());
                        $span->setAttribute(\'error_code\', $exception->getCode());
                    }

                    $span->setStatus($exception !== null ? \\OpenTelemetry\\API\\Trace\\StatusCode::STATUS_ERROR : \\OpenTelemetry\\API\\Trace\\StatusCode::STATUS_OK);

                    $span->end();
                }
            );',
                $class[static::NAMESPACE_KEY],
                $class[static::CLASS_NAME_KEY],
                $method,
                $methodHookName,
                $this->isCriticalHook($class) ? 'true' : 'null',
            );
        }

        return PHP_EOL . implode(PHP_EOL, $hooks) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $class
     * @param string $method
     *
     * @return string
     */
    protected function buildMethodHookName(array $class, string $method): string
    {
        return sprintf(
            static::METHOD_HOOK_NAME,
            $class[static::MODULE_KEY],
            $class[static::CLASS_NAME_KEY],
            $method,
        );
    }

    /**
     * @param array<string, mixed> $class
     *
     * @return bool
     */
    protected function isCriticalHook(array $class): bool
    {
        foreach ($this->config->getCriticalClassNamePatterns() as $classNamePattern) {
            if (str_contains($class[static::CLASS_NAME_KEY], $classNamePattern)) {
                return true;
            }
        }
        return false;
    }
}
