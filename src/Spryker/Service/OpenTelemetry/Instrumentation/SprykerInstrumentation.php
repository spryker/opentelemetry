<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\OpenTelemetry\Instrumentation;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
//use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SDK\Registry;
use OpenTelemetry\SemConv\TraceAttributes;
use Pyz\Glue\GlueApplication\Bootstrap\GlueBackendApiBootstrap;
use Pyz\Glue\GlueApplication\Bootstrap\GlueStorefrontApiBootstrap;
use Spryker\Glue\GlueApplication\Bootstrap\GlueBootstrap;
use Spryker\Service\OpenTelemetry\Instrumentation\ResourceDetector\CloudResourceDetector;
use Spryker\Shared\Application\Application;
use Spryker\Zed\Application\Communication\Bootstrap\BackofficeBootstrap;
use Spryker\Zed\MerchantPortalApplication\Communication\Bootstrap\MerchantPortalBootstrap;
use Spryker\Zed\OpenTelemetry\Business\Generator\Instrumentation\CachedInstrumentation;
use SprykerShop\Yves\ShopApplication\Bootstrap\YvesBootstrap;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

/**
 * placeholder for potential autoloader-time instrumentation of Spryker specific classes
 *
 * @see vendor/open-telemetry/opentelemetry-auto-symfony/_register.php for reference
 */
class SprykerInstrumentation
{
    /**
     * @var string
     */
    public const NAME = 'spryker';

    /**
     * @return void
     */
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation();
        Registry::registerResourceDetector('instance.id', new CloudResourceDetector());

        static::runInstrumentation($instrumentation);
    }

    /**
     * @return void
     */
    public static function runInstrumentation(CachedInstrumentation $instrumentation): void
    {
        /** @TO-DO here run stack of plugins for Application entry points instead of native hooks */

        static::addInstrumentation($instrumentation, Request::createFromGlobals(), Application::class, 'run');
        static::addInstrumentation($instrumentation, Request::createFromGlobals(), YvesBootstrap::class, 'boot');
        static::addInstrumentation($instrumentation, Request::createFromGlobals(), BackofficeBootstrap::class, 'boot');
        static::addInstrumentation($instrumentation, Request::createFromGlobals(), MerchantPortalBootstrap::class, 'boot');
        static::addInstrumentation($instrumentation, Request::createFromGlobals(), GlueBootstrap::class, 'boot');
        static::addInstrumentation($instrumentation, Request::createFromGlobals(), GlueStorefrontApiBootstrap::class, 'boot');
        static::addInstrumentation($instrumentation, Request::createFromGlobals(), GlueBackendApiBootstrap::class, 'boot');
    }

    /**
     * @return void
     */
    protected static function addInstrumentation(
        CachedInstrumentation $instrumentation,
        Request $request,
        string $class,
        string $function
    ): void {
        hook(
            class: $class,
            function: $function,
            pre: static function ($instance, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $request): void {
                $relativeUriWithoutQueryString = str_replace('?' . $request->getQueryString(), '', $request->getUri());

                $span = $instrumentation::getCachedInstrumentation()
                    ->tracer()
                    ->spanBuilder(sprintf(
                        '%s %s',
                        $request->getMethod(),
                        $relativeUriWithoutQueryString,
                    ))
                    ->setAttribute('memory_start', memory_get_peak_usage(true) / 1024)
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno)
                    ->setAttribute(TraceAttributes::URL_QUERY, $request->getQueryString())
                    ->startSpan();

                Context::storage()->attach($span->storeInContext(Context::getCurrent()));
            },
            post: static function ($instance, array $params, $returnValue, Throwable $exception): void {
                $scope = Context::storage()->scope();

                if ($scope === null) {
                    return;
                }

                $scope->detach();
                $span = Span::fromContext($scope->context());

                if ($exception) {
                    $span->recordException($exception);
                    $span->setStatus(StatusCode::STATUS_ERROR);
                }

                $span->setAttribute('memory_end', memory_get_peak_usage(true) / 1024);
                $span->setStatus(StatusCode::STATUS_OK);
                $span->end();
            },
        );
    }
}
