<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Dependency\External;

use Spryker\Glue\DocumentationGeneratorApi\Exception\IOException;
use Symfony\Component\Filesystem\Exception\IOException as SymfonyIOException;
use Symfony\Component\Filesystem\Filesystem;

class OpenTelemetryToFilesystemAdapter implements OpenTelemetryToFilesystemInterface
{
    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param string $filename
     *
     * @throws \Spryker\Glue\DocumentationGeneratorApi\Exception\IOException
     *
     * @return void
     */
    public function remove(string $filename): void
    {
        try {
            $this->filesystem->remove($filename);
        } catch (SymfonyIOException $e) {
            throw new IOException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * @param string $filename
     *
     * @throws \Spryker\Glue\DocumentationGeneratorApi\Exception\IOException
     *
     * @return bool
     */
    public function exists(string $filename): bool
    {
        try {
            return $this->filesystem->exists($filename);
        } catch (SymfonyIOException $e) {
            throw new IOException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * @param iterable<string>|string $dirs
     * @param int $mode
     *
     * @throws \Spryker\Glue\DocumentationGeneratorApi\Exception\IOException
     *
     * @return void
     */
    public function mkdir($dirs, int $mode = self::PERMISSION_ALL): void
    {
        try {
            $this->filesystem->mkdir($dirs, $mode);
        } catch (SymfonyIOException $e) {
            throw new IOException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }
}
