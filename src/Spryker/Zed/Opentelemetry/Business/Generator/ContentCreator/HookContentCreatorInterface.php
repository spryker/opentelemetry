<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\ContentCreator;

interface HookContentCreatorInterface
{
    /**
     * @param array<string, array<string>> $class
     *
     * @return string
     */
    public function createHookContent(array $class): string;
}
