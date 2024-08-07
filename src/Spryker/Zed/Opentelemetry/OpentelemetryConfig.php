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
     * - Returns the list of modules that should be excluded from instrumentation.
     *
     * @api
     *
     * @return array
     */
    public function getExcludedDirs(): array
    {
        return [
            'OpenTelemetry',
            'Container',
            'Kernel',
            'Application',
            'Installer',
            'Console',
            'Config',
            'Queue',
            'PropelReplicationCache',
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
        /** @TO-DO fix paths for demo-shops */
        return [
            '#^vendor/spryker/spryker/Bundles/.*/.*/.*/.*/.*/(Business|Communication/Controller)#',
            '#^vendor/spryker/spryker/Bundles/Glue.*#',
            '#^vendor/spryker/spryker-shop/Bundles/.*#',
            '#^src/Pyz/.*#',
        ];
    }

    /**
     * Specification:
     * - Returns the output directory for generated hooks.
     *
     * @api
     *
     * @return array<string>
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
            'cli_trace_id' => 'OTEL_CLI_TRACE_ID',
            'merchant_portal_trace_id' => 'OTEL_MERCHANT_PORTAL_TRACE_ID',
            'glue_trace_id' => 'OTEL_GLUE_TRACE_ID',
            'application_trace_id' => 'OTEL_APPLICATION_TRACE_ID',
            'backoffice_trace_id' => 'OTEL_BACKOFFICE_TRACE_ID',
            'yves_trace_id' => 'OTEL_YVES_TRACE_ID',
        ];
    }
}
