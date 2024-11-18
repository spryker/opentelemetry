<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry;

use Spryker\Shared\Config\Config;
use Spryker\Zed\Kernel\AbstractBundleConfig;

class OpentelemetryConfig extends AbstractBundleConfig
{
    /**
     * @var int
     */
    protected const THRESHOLD_NANOS = 1000000;

    /**
     * Specification:
     * - Returns the list of modules that should be excluded from instrumentation.
     *
     * @api
     *
     * @return array<string>
     */
    public function getExcludedDirs(): array
    {
        return [
            'Monitoring',
            'Opentelemetry',
            'OpenTelemetry',
            'Dependency',
            'Acl',
            'AclEntity',
            'Container',
            'Transfer',
            'Kernel',
            'Application',
            'Installer',
            'Console',
            'Config',
            'Queue',
            'PropelReplicationCache',
            'GitHook',
            'ArchitectureSniffer',
            'Router',
            'ZedNavigation',
            'Acl',
            'Twig',
            'WebProfiler',
            'Translator',
            'Money',
            'Locale',
            'Store',
            'Log',
            'templates',
            'config',
            'tests',
        ];
    }

    /**
     * Specification:
     * - Returns the list of paths that should be instrumented.
     *
     * @api
     *
     * @return array<string>
     */
    public function getPathPatterns(): array
    {
        return [
            '#^vendor/spryker/[^/]+/.*/.*/(Zed|Shared)/.*/(?!Persistence|Presentation)[^/]+/.*#',
            '#^vendor/spryker/[^/]+/Glue.*#',
            '#^vendor/spryker(?:/spryker)?-shop/[^/]+/.*#',
            '#^vendor/spryker-eco/[^/]+/.*#',
            '#^src/Pyz/.*#',
        ];
    }

    /**
     * Specification:
     * - Returns the output directory for generated hooks.
     *
     * @api
     *
     * @return string
     */
    public function getOutputDir(): string
    {
        return APPLICATION_SOURCE_DIR . '/Generated/OpenTelemetry/Hooks/';
    }

    /**
     * Specification:
     * - Set to false if you want to instrument all methods, including private and protected ones.
     *
     * @api
     *
     * @return bool
     */
    public function areOnlyPublicMethodsInstrumented(): bool
    {
        return true;
    }

    /**
     * Specification:
     * - The threshold in nanoseconds for the span to be sampled.
     *
     * @api
     *
     * return int
     */
    public static function getSamplerThresholdNano(): int
    {
        $multiplicator = getenv('OTEL_BSP_MIN_SPAN_DURATION_THRESHOLD') ?: 1;

        return $multiplicator * static::THRESHOLD_NANOS;
    }

    /**
     * @api
     *
     * @return string
     */
    public static function getServiceNamespace(): string
    {
        return getenv('OTEL_SERVICE_NAMESPACE') ?: 'spryker';
    }

    /**
     * @api
     *
     * @return string
     */
    public static function getExporterEndpoint(): string
    {
        return getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://collector:4317';
    }

    /**
     * @api
     *
     * @return array<string>
     */
    public static function getExcludedURLs(): array
    {
        $urls = getenv('OTEL_PHP_EXCLUDED_URLS') ?: [];

        return explode(',', $urls);
    }

    /**
     * @api
     *
     * @return array<string>
     */
    public static function getServiceNameMapping(): array
    {
        $mapping = getenv('OTEL_SERVICE_NAME_MAPPING') ?: '{}';
        $mapping = json_decode($mapping, true);

        if ($mapping === []) {
            return [
                '/(?:https?:\/\/)?(yves)\./' => 'Yves',
                '/(?:https?:\/\/)?(backoffice)\./' => 'Backoffice',
                '/(?:https?:\/\/)?(glue)\./' => 'Legacy Glue',
                '/(?:https?:\/\/)?(merchant)\./' => 'Merchant Portal',
                '/(?:https?:\/\/)?(backend-gateway)\./' => 'Backend Gateway',
                '/(?:https?:\/\/)?(glue-storefront)\./' => 'Glue Storefront',
                '/(?:https?:\/\/)?(glue-backend)\./' => 'Glue Backend',
            ];
        }

        return $mapping;
    }

    /**
     * @api
     *
     * @return string
     */
    public static function getDefaultServiceName(): string
    {
        return getenv('OTEL_DEFAULT_SERVICE_NAME') ?: 'Default Service';
    }

    /**
     * @api
     *
     * @return float
     */
    public static function getSamplerProbability(): float
    {
        $probability = getenv('OTEL_TRACES_SAMPLER_ARG') ?: 1.0;

        return (float)$probability;
    }
}
