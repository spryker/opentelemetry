<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Communication\Plugin\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \Spryker\Zed\Opentelemetry\Business\OpentelemetryFacadeInterface getFacade()
 */
class OpentelemetryGeneratorConsole extends Console
{
    /**
     * @var string
     */
    public const COMMAND_NAME = 'open-telemetry:generate';

    /**
     * @var string
     */
    public const COMMAND_DESCRIPTION = 'Generates Open Telemetry hooks for Spryker classes and methods to be instrumented.';

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName(static::COMMAND_NAME)
            ->setDescription(static::COMMAND_DESCRIPTION)
            ->setHelp('<info>' . static::COMMAND_NAME . ' -h</info>');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->getFacade()->deleteHooks();
        $this->getFacade()->generateHooks();

        return static::CODE_SUCCESS;
    }
}
