<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Storage;

interface CustomParameterStorageInterface
{
    /**
     * @return \Spryker\Service\Opentelemetry\Storage\CustomParameterStorage
     */
    public static function getInstance(): CustomParameterStorage;

    /**
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setAttribute(string $key, $value): void;

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    public function getAttribute(string $key);

    /**
     * @return array
     */
    public function getAttributes(): array;
}
