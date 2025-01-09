<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Storage;

interface CustomEventsStorageInterface
{
    /**
     * @return \Spryker\Service\Opentelemetry\Storage\CustomEventsStorageInterface
     */
    public static function getInstance(): CustomEventsStorageInterface;

    /**
     * @param string $name
     * @param array $attributes
     *
     * @return void
     */
    public function addEvent(string $name, array $attributes): void;

    /**
     * @return array
     */
    public function getEvents(): array;
}
