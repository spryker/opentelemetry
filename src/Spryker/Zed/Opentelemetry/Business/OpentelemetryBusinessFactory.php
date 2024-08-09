<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\Opentelemetry\Business\Generator\Collector\ClassCollector;
use Spryker\Zed\Opentelemetry\Business\Generator\Collector\ClassCollectorInterface;
use Spryker\Zed\Opentelemetry\Business\Generator\ContentCreator\HookContentCreator;
use Spryker\Zed\Opentelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface;
use Spryker\Zed\Opentelemetry\Business\Generator\HookGenerator;
use Spryker\Zed\Opentelemetry\Business\Generator\HookGeneratorInterface;
use Spryker\Zed\Opentelemetry\Business\Generator\Instrumentation\CachedInstrumentation;
use Spryker\Zed\Opentelemetry\Business\Remover\HookRemover;
use Spryker\Zed\Opentelemetry\Business\Remover\HookRemoverInterface;
use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemInterface;
use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFinderInterface;
use Spryker\Zed\Opentelemetry\OpentelemetryDependencyProvider;

/**
 * @method \Spryker\Zed\Opentelemetry\OpentelemetryConfig getConfig()
 */
class OpentelemetryBusinessFactory extends AbstractBusinessFactory
{
    /**
     * @return \Spryker\Zed\Opentelemetry\Business\Generator\HookGeneratorInterface
     */
    public function createHookGenerator(): HookGeneratorInterface
    {
        return new HookGenerator(
            $this->createClassCollector(),
            $this->createHookContentCreator(),
            $this->getFileSystem(),
            $this->getConfig(),
        );
    }

    /**
     * @return \Spryker\Zed\Opentelemetry\Business\Remover\HookRemoverInterface
     */
    public function createHookRemover(): HookRemoverInterface
    {
        return new HookRemover(
            $this->getFileSystem(),
            $this->getConfig(),
        );
    }

    /**
     * @return \Spryker\Zed\Opentelemetry\Business\Generator\Collector\ClassCollectorInterface
     */
    public function createClassCollector(): ClassCollectorInterface
    {
        return new ClassCollector(
            $this->getConfig(),
            $this->getFinder(),
        );
    }

    /**
     * @return \Spryker\Zed\Opentelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface
     */
    public function createHookContentCreator(): HookContentCreatorInterface
    {
        return new HookContentCreator(
            $this->getConfig(),
        );
    }

    public function createCachedInstrumentation()
    {
        return new CachedInstrumentation();
    }

    /**
     * @return \Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFinderInterface
     */
    public function getFinder(): OpentelemetryToFinderInterface
    {
        return $this->getProvidedDependency(OpentelemetryDependencyProvider::FINDER);
    }

    /**
     * @return \Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemInterface
     */
    public function getFileSystem(): OpentelemetryToFilesystemInterface
    {
        return $this->getProvidedDependency(OpentelemetryDependencyProvider::FILESYSTEM);
    }
}
