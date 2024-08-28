<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\Collector;

interface ClassCollectorInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectClasses(): array;
}
