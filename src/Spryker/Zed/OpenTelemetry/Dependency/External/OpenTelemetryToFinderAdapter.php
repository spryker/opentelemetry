<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\OpenTelemetry\Dependency\External;

use Symfony\Component\Finder\Finder;

class OpenTelemetryToFinderAdapter implements OpenTelemetryToFinderInterface
{
    /**
     * @var \Symfony\Component\Finder\Finder
     */
    protected Finder $finder;

    public function __construct()
    {
        $this->finder = Finder::create();
    }

    /**
     * @return $this
     */
    public function files()
    {
        $this->finder->files();

        return $this;
    }

    /**
     * @param array<mixed>|string $dirs
     *
     * @return $this
     */
    public function in($dirs)
    {
        $this->finder->in($dirs);

        return $this;
    }

    /**
     * @param array<string>|string $paths
     *
     * @return $this
     */
    public function path($paths)
    {
        $this->finder->path($paths);

        return $this;
    }

    /**
     * @param string $pattern
     *
     * @return $this
     */
    public function name(string $pattern)
    {
        $this->finder->name($pattern);

        return $this;
    }

    /**
     * @param array<string>|string $patterns
     *
     * @return $this
     */
    public function notName($patterns)
    {
        $this->finder->notName($patterns);

        return $this;
    }

    /**
     * @param array<string>|string $patterns
     *
     * @return $this
     */
    public function exclude($patterns)
    {
        $this->finder->exclude($patterns);

        return $this;
    }

    /**
     * @param array<string|int>|string|int $levels
     *
     * @return $this
     */
    public function depth($levels)
    {
        $this->finder->depth($levels);

        return $this;
    }
}
