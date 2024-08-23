<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry;

use Spryker\Zed\Kernel\AbstractBundleConfig;

class OpentelemetryConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - The threshold in nanoseconds for the span to be sampled.
     *
     * @api
     *
     * @var int
     */
    public const THRESHOLD_NANOS = 1000000;

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
            'Opentelemetry',
            'Container',
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
     * - Returns the list of environment variables that should be used for trace propagation.
     *
     * @api
     *
     * @return array<string>
     */
    public function getOtelEnvVars(): array
    {
        return [
            'backoffice_trace_id' => 'OTEL_BACKOFFICE_TRACE_ID',
            'cli_trace_id' => 'OTEL_CLI_TRACE_ID',
            'merchant_portal_trace_id' => 'OTEL_MERCHANT_PORTAL_TRACE_ID',
            'glue_trace_id' => 'OTEL_GLUE_TRACE_ID',
            'yves_trace_id' => 'OTEL_YVES_TRACE_ID',
            'backend_gateway_trace_id' => 'OTEL_BACKEND_GATEWAY_TRACE_ID',
        ];
    }
}
