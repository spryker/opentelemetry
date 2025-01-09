<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Storage;

class CustomEventsStorage implements CustomEventsStorageInterface
{

    /**
     * @var \Spryker\Service\Opentelemetry\Storage\CustomEventsStorageInterface|null
     */
    private static ?CustomEventsStorageInterface $instance = null;

    /**
     * @var array<string, array>
     */
    protected array $events = [];

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\CustomEventsStorageInterface
     */
    public static function getInstance(): CustomEventsStorageInterface
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $name
     * @param array $attributes
     *
     * @return void
     */
    public function addEvent(string $name, array $attributes): void
    {
        $this->events[$name] = $attributes;
    }

    /**
     * @return array<string, array>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
