<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Business\Generator;

use Spryker\Zed\OpenTelemetry\Business\Generator\Collector\ClassCollectorInterface;
use Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface;
use Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface;
use Spryker\Zed\OpenTelemetry\OpenTelemetryConfig;

class HookGenerator implements HookGeneratorInterface
{
    /**
     * @var int
     */
    protected const DIRECTORY_PERMISSIONS = 0755;

    /**
     * @var string
     */
    protected const OUTPUT_FILE_PATH_PLACEHOLDER = '%s/%s-%sHook.php';

    /**
     * @var string
     */
    protected const NAMESPACE_KEY = 'namespace';

    /**
     * @var string
     */
    protected const CLASS_NAME_KEY = 'className';

    /**
     * @var \Spryker\Zed\OpenTelemetry\Business\Generator\Collector\ClassCollectorInterface
     */
    protected ClassCollectorInterface $collector;

    /**
     * @var \Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface
     */
    protected HookContentCreatorInterface $contentCreator;

    /**
     * @var \Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface
     */
    protected OpenTelemetryToFilesystemInterface $fileSystem;

    /**
     * @var \Spryker\Zed\OpenTelemetry\OpenTelemetryConfig
     */
    protected OpenTelemetryConfig $config;

    /**
     * @param \Spryker\Zed\OpenTelemetry\Business\Generator\Collector\ClassCollectorInterface $collector
     * @param \Spryker\Zed\OpenTelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface $contentCreator
     * @param \Spryker\Zed\OpenTelemetry\Dependency\External\OpenTelemetryToFilesystemInterface $fileSystem
     * @param \Spryker\Zed\OpenTelemetry\OpenTelemetryConfig $config
     */
    public function __construct(
        ClassCollectorInterface $collector,
        HookContentCreatorInterface $contentCreator,
        OpenTelemetryToFilesystemInterface $fileSystem,
        OpenTelemetryConfig $config
    ) {
        $this->collector = $collector;
        $this->contentCreator = $contentCreator;
        $this->fileSystem = $fileSystem;
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function generate(): void
    {
        $collectedClasses = $this->collector->collectClasses();

        foreach ($collectedClasses as $class) {
            $this->ensureDirectoryExists();

            file_put_contents(
                $this->getOutputFilepath($class),
                $this->contentCreator->createHookContent($class),
            );
        }
    }

    /**
     * @return void
     */
    protected function ensureDirectoryExists(): void
    {
        if ($this->fileSystem->exists($this->config->getOutputDir()) === true) {
            return;
        }

        $this->fileSystem->mkdir($this->config->getOutputDir(), static::DIRECTORY_PERMISSIONS);
    }

    /**
     * @param array<string> $class
     *
     * @return string
     */
    protected function getOutputFilepath(array $class): string
    {
        return sprintf(
            static::OUTPUT_FILE_PATH_PLACEHOLDER,
            $this->config->getOutputDir(),
            str_replace('\\', '-', $class[static::NAMESPACE_KEY]),
            $class[static::CLASS_NAME_KEY],
        );
    }
}
