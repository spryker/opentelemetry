<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business;

use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\OpenTelemetry\Business\Generator\Collector\ClassCollector;
use Spryker\Zed\OpenTelemetry\Business\Generator\Collector\ClassCollectorInterface;
use Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator\HookContentCreator;
use Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface;
use Spryker\Zed\OpenTelemetry\Business\Generator\HookGenerator;
use Spryker\Zed\OpenTelemetry\Business\Generator\HookGeneratorInterface;
use Spryker\Zed\OpenTelemetry\Business\Remover\HookRemover;
use Spryker\Zed\OpenTelemetry\Business\Remover\HookRemoverInterface;
use Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface;
use Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFinderInterface;
use Spryker\Zed\OpenTelemetry\OpenTelemetryDependencyProvider;

/**
 * @method \Spryker\Zed\OpenTelemetry\OpenTelemetryConfig getConfig()
 */
class OpenTelemetryBusinessFactory extends AbstractBusinessFactory
{
    /**
     * @return \Spryker\Zed\OpenTelemetry\Business\Generator\HookGeneratorInterface
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
     * @return \Spryker\Zed\OpenTelemetry\Business\Remover\HookRemoverInterface
     */
    public function createHookRemover(): HookRemoverInterface
    {
        return new HookRemover(
            $this->getFileSystem(),
            $this->getConfig(),
        );
    }

    /**
     * @return \Spryker\Zed\OpenTelemetry\Business\Generator\Collector\ClassCollectorInterface
     */
    public function createClassCollector(): ClassCollectorInterface
    {
        return new ClassCollector(
            $this->getConfig(),
            $this->getFinder(),
        );
    }

    /**
     * @return \Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface
     */
    public function createHookContentCreator(): HookContentCreatorInterface
    {
        return new HookContentCreator();
    }

    /**
     * @return \Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFinderInterface
     */
    public function getFinder(): OpenTelemetryToFinderInterface
    {
        return $this->getProvidedDependency(OpenTelemetryDependencyProvider::FINDER);
    }

    /**
     * @return \Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface
     */
    public function getFileSystem(): OpenTelemetryToFilesystemInterface
    {
        return $this->getProvidedDependency(OpenTelemetryDependencyProvider::FILESYSTEM);
    }
}
