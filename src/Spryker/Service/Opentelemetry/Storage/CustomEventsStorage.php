<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Storage;

class CustomEventsStorage implements CustomEventsStorageInterface
{
    /**
     * @var string
     */
    public const EVENT_NAME = 'name';

    /**
     * @var string
     */
    public const EVENT_ATTRIBUTES = 'attributes';
    /**
     * @var \Spryker\Service\Opentelemetry\Storage\CustomEventsStorageInterface|null
     */
    private static ?CustomEventsStorageInterface $instance = null;

    /**
     * @var array<array<string, string|array<string,mixed>>>
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
        $this->events[] = [static::EVENT_NAME=> $name, static::EVENT_ATTRIBUTES => $attributes];
    }

    /**
     * @return array<array<string, string|array<string,mixed>>>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
