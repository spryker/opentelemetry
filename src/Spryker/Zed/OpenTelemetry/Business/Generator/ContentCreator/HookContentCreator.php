<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator;

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
     * @param array<string, array<string>> $class
     *
     * @return string
     */
    public function createHookContent(array $class): string
    {
        $hooks = [];

        foreach ($class[static::METHODS_KEY] as $method) {
            $hooks[] = '
            \\OpenTelemetry\\Instrumentation\\hook(
                class: \\' . $class[static::NAMESPACE_KEY] . '\\' . $class[static::CLASS_NAME_KEY] . '::class,
                function: \'' . $method . '\',
                pre: static function ($instance, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                    $type = $params[1] ?? \\Symfony\\Component\\HttpKernel\\HttpKernelInterface::MAIN_REQUEST;

                    $request = \\Spryker\\Zed\\OpenTelemetry\\Business\\Generator\\Request\\RequestProcessor::getRequest();

                    $span = \\Spryker\\Zed\\OpenTelemetry\\Business\\Generator\\Instrumentation\\CachedInstrumentation::getCachedInstrumentation()
                        ->tracer()
                        ->spanBuilder($request->getMethod() . \' ' . $class[static::MODULE_KEY] . '-' . $class[static::CLASS_NAME_KEY] . '::' . $method . '\')
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
                    $scope->detach();
                    $span = \\OpenTelemetry\\API\\Trace\\Span::fromContext($scope->context());
                    if ($exception) {
                        $span->recordException($exception);
                        $span->setStatus(\\OpenTelemetry\\API\\Trace\\StatusCode::STATUS_ERROR);
                    }

                    $span->setStatus(\\OpenTelemetry\\API\\Trace\\StatusCode::STATUS_OK);
                    $span->end();
                }
            );';
        }

        return '<?php' . PHP_EOL . implode(PHP_EOL, $hooks) . PHP_EOL;
    }
}
