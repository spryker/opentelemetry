<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Dependency\External;

interface OpentelemetryToFilesystemInterface
{
    /**
     * @var int
     */
    public const PERMISSION_ALL = 0777;

    /**
     * @param string $filename
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     *
     * @return void
     */
    public function remove(string $filename): void;

    /**
     * @param string $filename
     *
     * @throws \Spryker\Glue\DocumentationGeneratorApi\Exception\IOException
     *
     * @return bool
     */
    public function exists(string $filename): bool;

    /**
     * @param iterable<string>|string $dirs
     * @param int $mode
     *
     * @throws \Symfony\Component\Filesystem\Exception\IOException
     *
     * @return void
     */
    public function mkdir($dirs, int $mode = self::PERMISSION_ALL): void;
}
