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
            'Form',
            'Gui',
            'GracefulRunner',
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
            'Navigation',
            'Touch',
            'Collector',
            'NavigationGui',
            'Store',
            'Log',
            'Profiler',
            'Propel',
            'PropelOrm',
            'PropelQueryBuilder',
            'RabbitMq',
            'Redis',
            'Session',
            'SessionFile',
            'SessionRedis',
            'SessionUserValidation',
            'Testify',
            'Validation',
            'Development',
            'ErrorHandler',
            'Form',
            'Glossary',
            'GuiTable',
            'ModuleFinder',
            'Monitoring',
            'Search',
            'SearchElasticsearch',
            'SearchElasticsearchGui',
            'Silex',
            'SearchHttp',
            'Storage',
            'StorageRedis',
            'StorageDatabase',
            'StoreContext',
            'Symfony',
            'Security',
            'SecurityGui',
            'SecurityBlockerBackofficeGui',
            'SecurityBlockerMerchantPortalGui',
            'AgentSecurityMerchantPortalGui',
            'SecurityMerchantPortalGui',
            'Http',
            'SecuritySystemUser',
            'SecurityOauthUser',
            'UserLocale',
            'User',
            'AclMerchantAgent',
            'Merchant',
            'MerchantStock',
            'MerchantProfile',
            'MerchantGui',
            'MerchantUser',
            'MerchantCategory',
            'ZedUi',
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
}
