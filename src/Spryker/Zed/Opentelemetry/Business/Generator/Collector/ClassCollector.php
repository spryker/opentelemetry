<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Opentelemetry\Business\Generator\Collector;

use Spryker\Zed\Opentelemetry\Business\Exception\ProcessingFileException;
use Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFinderInterface;
use Spryker\Zed\Opentelemetry\OpentelemetryConfig;
use Symfony\Component\Finder\Finder;
use Throwable;

class ClassCollector implements ClassCollectorInterface
{
    /**
     * @var string
     */
    protected const FILE_PATTERN = '*.php';

    /**
     * @var string
     */
    protected const ERROR_MESSAGE = 'Error processing file %s: %s';

    /**
     * @var string
     */
    protected const NAMESPACE_KEY = 'namespace';

    /**
     * @var string
     */
    protected const CLASS_NAME_KEY = 'className';

    /**
     * @var string
     */
    protected const MODULE_KEY = 'module';

    /**
     * @var string
     */
    protected const METHODS_KEY = 'methods';

    /**
     * @var \Spryker\Zed\Opentelemetry\OpentelemetryConfig
     */
    protected OpentelemetryConfig $config;

    /**
     * @var \Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFinderInterface
     */
    protected OpentelemetryToFinderInterface $finder;

    /**
     * @param \Spryker\Zed\Opentelemetry\OpentelemetryConfig $config
     * @param \Spryker\Zed\Opentelemetry\Dependency\External\OpentelemetryToFinderInterface $finder
     */
    public function __construct(
        OpentelemetryConfig $config,
        OpentelemetryToFinderInterface $finder
    ) {
        $this->config = $config;
        $this->finder = $finder;
    }

    /**
     * @return array<string, array<string>>
     *@throws \Spryker\Zed\Opentelemetry\Business\Exception\ProcessingFileException
     *
     */
    public function collectClasses(): array
    {
        /** @TO-DO fix */
        $finder = Finder::create()
            ->files()
            ->in(APPLICATION_ROOT_DIR)
            ->path($this->config->getPathPatterns())
            ->name(static::FILE_PATTERN)
            ->notName($this->getExcludedClasses())
            ->exclude($this->config->getExcludedDirs());

        $collectedClasses = [];

        foreach ($finder as $file) {
            try {
                $classInfo = $this->extractClassDetailsFromFile($file->getRealPath());
            } catch (Throwable $e) {
                throw new ProcessingFileException(sprintf(static::ERROR_MESSAGE, $file->getRealPath(), $e->getMessage()), $e->getCode(), $e);
            }

            if ($classInfo === []) {
                continue;
            }

            $collectedClasses[] = $classInfo;
        }

        return $collectedClasses;
    }

    /**
     * @param string $filePath
     *
     * @return array<string>
     */
    protected function extractClassDetailsFromFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $tokens = token_get_all($content);
        $namespace = $this->parseNamespace($tokens);
        $class = $this->parseClassName($tokens);
        $methods = $this->parseMethods($tokens);

        if ($class && $methods !== []) {
            return [
                static::NAMESPACE_KEY => $namespace,
                static::CLASS_NAME_KEY => $class,
                static::MODULE_KEY => explode('\\', $namespace)[2],
                static::METHODS_KEY => array_unique($methods),
            ];
        }

        return [];
    }

    /**
     * @param array<mixed> $tokens
     *
     * @return string
     */
    protected function parseNamespace(array $tokens): string
    {
        $namespace = '';
        $tokensCount = count($tokens);

        for ($i = 0; $i < $tokensCount; $i++) {
            if ($tokens[$i][0] === T_NAMESPACE) {
                for ($j = $i + 2; $j < $tokensCount; $j++) {
                    if (isset($tokens[$j][1]) && $tokens[$j][1] === T_STRING || $tokens[$j][0] === T_NAME_QUALIFIED) {
                        $namespace .= $tokens[$j][1];
                    } else {
                        break;
                    }
                }

                break;
            }
        }

        return $namespace;
    }

    /**
     * @param array<mixed> $tokens
     *
     * @return string
     */
    protected function parseClassName(array $tokens): string
    {
        $class = '';
        $tokensCount = count($tokens);

        for ($i = 0; $i < $tokensCount; $i++) {
            if ($tokens[$i][0] === T_CLASS && $tokens[$i - 1][0] !== T_DOUBLE_COLON) {
                for ($j = $i + 2; $j < $tokensCount; $j++) {
                    if ($tokens[$j][0] === T_STRING) {
                        $class = $tokens[$j][1];

                        break;
                    }
                }

                break;
            }
        }

        return $class;
    }

    /**
     * @param array<mixed> $tokens
     *
     * @return array<string>
     */
    protected function parseMethods(array $tokens): array
    {
        $methods = [];
        $functionLevel = 0;
        $currentFunction = '';
        $tokensCount = count($tokens);

        for ($i = 0; $i < $tokensCount; $i++) {
            if ($tokens[$i][0] === T_FUNCTION) {
                $isAnonymousFunction = false;
                for ($j = $i + 1; $j < $tokensCount; $j++) {
                    if ($tokens[$j] === '(') {
                        break;
                    }
                    if ($tokens[$j][0] === T_STRING) {
                        $currentFunction = $tokens[$j][1];

                        break;
                    }
                    if ($tokens[$j] === '{' || $tokens[$j][0] === T_CURLY_OPEN) {
                        $isAnonymousFunction = true;

                        break;
                    }
                }

                if (!$isAnonymousFunction) {
                    if (!str_starts_with($currentFunction, '__')) {
                        $methods[] = $currentFunction;
                    }
                }
            }

            if ($tokens[$i] === '{') {
                $functionLevel++;
            }

            if ($tokens[$i] === '}') {
                $functionLevel--;
                if ($functionLevel == 0) {
                    $currentFunction = '';
                }
            }
        }

        return $methods;
    }

    /**
     * @return array<string>
     */
    protected function getExcludedClasses(): array
    {
        return [
            '*Factory.php',
            '*DependencyProvider.php',
            '*Config.php',
            '*Bridge.php',
            '*Adapter.php',
            '*Constants.php',
            '*Exception.php',
            '*ConsoleBootstrap.php',
            'AbstractSpy*.php',
            '*Interface.php',
        ];
    }
}
