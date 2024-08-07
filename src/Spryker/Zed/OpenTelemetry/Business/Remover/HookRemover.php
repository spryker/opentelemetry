<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Remover;

use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemInterface;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;

class HookRemover implements HookRemoverInterface
{
    /**
     * @var \Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemInterface
     */
    protected OpentelemetryToFilesystemInterface $fileSystem;

    /**
     * @var \Spryker\Zed\Opentelemetry\OpentelemetryConfig
     */
    protected OpentelemetryConfig $config;

    /**
     * @param \Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemInterface $fileSystem
     * @param \Spryker\Zed\Opentelemetry\OpentelemetryConfig $config
     */
    public function __construct(
        OpentelemetryToFilesystemInterface $fileSystem,
        OpentelemetryConfig $config
    ) {
        $this->fileSystem = $fileSystem;
        $this->config = $config;
    }

     /**
      * @return void
      */
    public function remove(): void
    {
        if (!$this->fileSystem->exists($this->config->getOutputDir())) {
            return;
        }

        $this->fileSystem->remove($this->config->getOutputDir());
    }
}
