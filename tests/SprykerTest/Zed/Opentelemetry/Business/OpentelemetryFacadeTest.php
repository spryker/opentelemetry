<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Zed\Opentelemetry\Business;

use Codeception\Test\Unit;
use Spryker\Zed\Opentelemetry\Business\Generator\Collector\ClassCollectorInterface;
use Spryker\Zed\Opentelemetry\Business\Generator\ContentCreator\HookContentCreatorInterface;
use Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory;
use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFilesystemAdapter;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;
use SprykerTest\Zed\Opentelemetry\OpentelemetryBusinessTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group Opentelemetry
 * @group Business
 * @group Facade
 * @group OpentelemetryFacadeTest
 * Add your own group annotations below this line
 */
class OpentelemetryFacadeTest extends Unit
{
    /**
     * @var \SprykerTest\Zed\Opentelemetry\OpentelemetryBusinessTester
     */
    protected OpentelemetryBusinessTester $tester;

    /**
     * @var string
     */
    protected const TEST_HOOKS_DIRECTORY_NAME = 'Hooks';

    /**
     * @var string
     */
    protected const TEST_HOOK_FILENAME = 'Spryker-Zed-Opentelemetry-Business-Generator-HookGeneratorHook.php';

    /**
     * @var string
     */
    protected string $outputDirPath;

    /**
     * @return void
     */
    public function testGenerateHooksGeneratesSuccessfully(): void
    {
        //Arrange
        $openTelemetryFacadeMock = $this->tester->getFacade();
        $openTelemetryFacadeMock->setFactory($this->createMockFactory());

        //Assert
        $this->assertFalse(is_dir($this->outputDirPath));

        //Act
        $openTelemetryFacadeMock->generateHooks();

        $this->assertTrue(is_dir($this->outputDirPath));
        $this->assertTrue(file_exists($this->outputDirPath . static::TEST_HOOK_FILENAME));
    }

    /**
     * @return void
     */
    public function testDeleteHooksRemovesHooksSuccessfully(): void
    {
        //Arrange
        $openTelemetryFacadeMock = $this->tester->getFacade();
        $openTelemetryFacadeMock->setFactory($this->createMockFactory());
        $openTelemetryFacadeMock->generateHooks();

        //Assert
        $this->assertTrue(is_dir($this->outputDirPath));

        //Act
        $openTelemetryFacadeMock->deleteHooks();

        $this->assertFalse(is_dir($this->outputDirPath));
        $this->assertFalse(file_exists($this->outputDirPath . static::TEST_HOOK_FILENAME));
    }

    /**
     * @return \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory
     */
    protected function createMockFactory(): OpentelemetryBusinessFactory
    {
        $mockFactory = $this->createPartialMock(
            OpentelemetryBusinessFactory::class,
            [
                'getConfig',
                'getFileSystem',
                'createClassCollector',
                'createHookContentCreator',
            ],
        );

        $mockFactory = $this->addConfigMock($mockFactory);
        $mockFactory = $this->addFilesystemMock($mockFactory);
        $mockFactory = $this->addHookContentCreatorMock($mockFactory);
        $mockFactory = $this->addClassCollectorMock($mockFactory);

        return $mockFactory;
    }

    /**
     * @param \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory $mockFactory
     *
     * @return \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory
     */
    protected function addConfigMock(OpentelemetryBusinessFactory $mockFactory): OpentelemetryBusinessFactory
    {
        $mockConfig = $this->createPartialMock(
            OpentelemetryConfig::class,
            [
                'getPathPatterns',
                'getOutputDir',
            ],
        );
        $mockConfig->method('getPathPatterns')
            ->willReturn(['#^vendor/spryker/opentelemetry/.*#']);
        $mockConfig->method('getOutputDir')
            ->willReturn($this->outputDirPath);

        $mockFactory
            ->method('getConfig')
            ->willReturn($mockConfig);

        return $mockFactory;
    }

    /**
     * @param \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory $mockFactory
     *
     * @return \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory
     */
    protected function addFilesystemMock(OpentelemetryBusinessFactory $mockFactory): OpentelemetryBusinessFactory
    {
        $filesystemMock = $this->createMock(Filesystem::class);

        $mockFactory
            ->method('getFilesystem')
            ->willReturn(
                new OpentelemetryToFilesystemAdapter($filesystemMock),
            );

        return $mockFactory;
    }

    /**
     * @param \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory $mockFactory
     *
     * @return \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory
     */
    protected function addHookContentCreatorMock(OpentelemetryBusinessFactory $mockFactory): OpentelemetryBusinessFactory
    {
        $contentCreatorMock = $this->createMock(HookContentCreatorInterface::class);
        $contentCreatorMock->method('createHookContent')->willReturn('FOO');

        $mockFactory
            ->method('createHookContentCreator')
            ->willReturn(
                $contentCreatorMock,
            );

        return $mockFactory;
    }

    /**
     * @param \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory $mockFactory
     *
     * @return \Spryker\Zed\Opentelemetry\Business\OpentelemetryBusinessFactory
     */
    protected function addClassCollectorMock(OpentelemetryBusinessFactory $mockFactory): OpentelemetryBusinessFactory
    {
        $classCollectorMock = $this->createMock(ClassCollectorInterface::class);
        $classCollectorMock->method('collectClasses')->willReturn([
            [
                'namespace' => 'Spryker\\Zed\\Opentelemetry\\Business\\Generator',
                'className' => 'HookGenerator',
                'module' => 'Opentelemetry',
                'methods' => ['generate'],
            ],
        ]);

        $mockFactory
            ->method('createClassCollector')
            ->willReturn(
                $classCollectorMock,
            );

        return $mockFactory;
    }

    /**
     * @return void
     */
    protected function _setUp(): void
    {
        parent::_setUp();

        $this->outputDirPath = codecept_output_dir() . static::TEST_HOOKS_DIRECTORY_NAME . DIRECTORY_SEPARATOR;
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->outputDirPath)) {
            $this->tester->deleteDirectory($this->outputDirPath);
        }
    }
}
