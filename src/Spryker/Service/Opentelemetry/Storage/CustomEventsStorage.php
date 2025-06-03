<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Service\Opentelemetry\Storage;

use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables as Env;
use OpenTelemetry\SDK\Trace\SpanLimits;

class CustomEventsStorage implements CustomEventsStorageInterface
{
    /**
     * @var string key for event name. If the class is extended, the exact same key must be used.
     */
    public const EVENT_NAME = 'name';

    /**
     * @var string key for event attributes. If the class is extended, the exact same key must be used.
     */
    public const EVENT_ATTRIBUTES = 'attributes';

    /**
     * @var \Spryker\Service\Opentelemetry\Storage\CustomEventsStorageInterface|null
     */
    protected static ?CustomEventsStorageInterface $instance = null;

    /**
     * @var int The span will not store more than this number, so we should also not store events that will not be processed.
     */
    protected static int $eventsLimit = 128;

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
            static::$eventsLimit = Configuration::getInt(Env::OTEL_SPAN_EVENT_COUNT_LIMIT, SpanLimits::DEFAULT_SPAN_EVENT_COUNT_LIMIT);
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
        if (count($this->events) >= static::$eventsLimit) {
            array_shift($this->events);
        }
        /**
         * Events should be not indexed by name, as it is not guaranteed that the same event will not be added multiple times.
         */
        $this->events[] = [
            static::EVENT_NAME => $name,
            static::EVENT_ATTRIBUTES => $attributes,
        ];
    }

    /**
     * @return array<array<string, string|array<string,mixed>>>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
