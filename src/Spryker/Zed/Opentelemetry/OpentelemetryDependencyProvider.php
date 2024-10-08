<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry;

use Spryker\Zed\Kernel\AbstractBundleDependencyProvider;
use Spryker\Zed\Kernel\Container;
use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemAdapter;
use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFinderAdapter;

/**
 * @method \Spryker\Zed\Opentelemetry\OpentelemetryConfig getConfig()
 */
class OpentelemetryDependencyProvider extends AbstractBundleDependencyProvider
{
    /**
     * @var string
     */
    public const FINDER = 'FINDER';

    /**
     * @var string
     */
    public const FILESYSTEM = 'FILESYSTEM';

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    public function provideBusinessLayerDependencies(Container $container): Container
    {
        $container = parent::provideBusinessLayerDependencies($container);

        $container = $this->addFinder($container);
        $container = $this->addFileSystem($container);

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addFinder(Container $container): Container
    {
        $container->set(static::FINDER, function () {
            return new OpentelemetryToFinderAdapter();
        });

        return $container;
    }

    /**
     * @param \Spryker\Zed\Kernel\Container $container
     *
     * @return \Spryker\Zed\Kernel\Container
     */
    protected function addFileSystem(Container $container): Container
    {
        $container->set(static::FILESYSTEM, function () {
            return new OpentelemetryToFilesystemAdapter();
        });

        return $container;
    }
}
