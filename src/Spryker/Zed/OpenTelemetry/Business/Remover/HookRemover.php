<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business\Remover;

use Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface;
use Spryker\Zed\OpenTelemetry\OpenTelemetryConfig;

class HookRemover implements HookRemoverInterface
{
    /**
     * @var \Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface
     */
    protected OpenTelemetryToFilesystemInterface $fileSystem;

    /**
     * @var \Spryker\Zed\OpenTelemetry\OpenTelemetryConfig
     */
    protected OpenTelemetryConfig $config;

    /**
     * @param \Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface $fileSystem
     * @param \Spryker\Zed\OpenTelemetry\OpenTelemetryConfig $config
     */
    public function __construct(
        OpenTelemetryToFilesystemInterface $fileSystem,
        OpenTelemetryConfig $config
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
