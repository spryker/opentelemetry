<?php

namespace Spryker\Service\Opentelemetry\Instrumentation\Sampler;

use Spryker\Service\Opentelemetry\OpentelemetryInstrumentationConfig;
use Symfony\Component\HttpFoundation\Request;

class TraceSampleResult
{
    /**
     * @var int
     */
    protected const SAMPLING_RESULT_BLOCK = 1;

    /**
     * @var int
     */
    protected const SAMPLING_RESULT_ALLOW_ROOT_SPAN = 2;

    /**
     * @var int
     */
    protected const SAMPLING_RESULT_ALLOW_ALL = 3;

    /**
     * @var int
     */
    protected static int $result = 0;

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return int
     */
    public static function shouldSample(Request $request): int
    {
        $route = $request->attributes->get('_route');

        if (in_array($route, OpentelemetryInstrumentationConfig::getExcludedRoutes(), true)) {
            static::$result = self::SAMPLING_RESULT_BLOCK;

            return static::$result;
        }

        if (static::decideForRootSpan()) {
            static::$result = static::SAMPLING_RESULT_ALLOW_ROOT_SPAN;

            return static::$result;
        }

        static::$result = static::SAMPLING_RESULT_ALLOW_ALL;

        return static::$result;
    }

    /**
     * @return bool
     */
    public static function shouldSkipTraceBody(): bool
    {
        return in_array(static::$result, [static::SAMPLING_RESULT_BLOCK, static::SAMPLING_RESULT_ALLOW_ROOT_SPAN], true);
    }

    /**
     * @return bool
     */
    public static function shouldSkipRootSpan(): bool
    {
        return static::$result === static::SAMPLING_RESULT_BLOCK;
    }

    /**
     * @return bool
     */
    protected static function decideForRootSpan(): bool
    {
        return !((mt_rand() / mt_getrandmax()) <= OpentelemetryInstrumentationConfig::getTraceSamplerProbability());
    }
}
