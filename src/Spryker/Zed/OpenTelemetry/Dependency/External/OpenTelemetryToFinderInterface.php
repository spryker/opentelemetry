<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Dependency\External;

interface OpentelemetryToFinderInterface
{
    /**
     * @param array<string>|string $dirs
     *
     * @return $this
     */
    public function in($dirs);

    /**
     * @return $this
     */
    public function files();

    /**
     * @param array<string>|string $paths
     *
     * @return $this
     */
    public function path($paths);

    /**
     * @param string $pattern
     *
     * @return $this
     */
    public function name(string $pattern);

    /**
     * @param array<string>|string $patterns
     *
     * @return $this
     */
    public function notName($patterns);

    /**
     * @param array<string>|string $patterns
     *
     * @return $this
     */
    public function exclude($patterns);

    /**
     * @param array<string|int>|string|int $levels
     *
     * @return $this
     */
    public function depth($levels);
}
