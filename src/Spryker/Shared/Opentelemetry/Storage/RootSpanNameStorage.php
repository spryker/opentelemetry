<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Opentelemetry\Storage;

class RootSpanNameStorage implements RootSpanNameStorageInterface
{
    /**
     * @var \Spryker\Service\Opentelemetry\Storage\RootSpanNameStorage|null
     */
    private static ?RootSpanNameStorage $instance = null;

    /**
     * @var string|null
     */
    protected ?string $name = null;

    /**
     * @return \Spryker\Service\Opentelemetry\Storage\RootSpanNameStorage
     */
    public static function getInstance(): RootSpanNameStorage
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }
}
