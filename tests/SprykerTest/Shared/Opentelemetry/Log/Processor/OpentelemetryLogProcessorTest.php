<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Shared\Opentelemetry\Log\Processor;

use Codeception\Test\Unit;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use Spryker\Shared\Opentelemetry\Log\Processor\OpentelemetryLogProcessor;
use Spryker\Shared\Opentelemetry\Reader\ResourceNameReaderInterface;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Shared
 * @group Opentelemetry
 * @group Log
 * @group Processor
 * @group OpentelemetryLogProcessorTest
 * Add your own group annotations below this line
 */
class OpentelemetryLogProcessorTest extends Unit
{
    /**
     * @var string
     */
    protected const SERVICE_NAME = 'spryker-test-service';

    /**
     * @var string|false
     */
    protected $originalOtelSdkDisabled;

    /**
     * @return void
     */
    protected function _before(): void
    {
        parent::_before();

        $this->originalOtelSdkDisabled = getenv('OTEL_SDK_DISABLED');
    }

    /**
     * @return void
     */
    protected function _after(): void
    {
        if ($this->originalOtelSdkDisabled === false) {
            putenv('OTEL_SDK_DISABLED');
        } else {
            putenv(sprintf('OTEL_SDK_DISABLED=%s', $this->originalOtelSdkDisabled));
        }

        parent::_after();
    }

    /**
     * @return void
     */
    public function testInvokeEnrichesArrayRecordContextWhenOtelIsEnabled(): void
    {
        // Arrange
        putenv('OTEL_SDK_DISABLED=false');
        $processor = $this->createOpentelemetryLogProcessor();

        // Act
        $result = $processor(['context' => ['foo' => 'bar']]);

        // Assert
        $this->assertSame('bar', $result['context']['foo']);
        $this->assertArrayHasKey('trace_id', $result['context']);
        $this->assertArrayHasKey('span_id', $result['context']);
        $this->assertSame(static::SERVICE_NAME, $result['context']['service.name']);
    }

    /**
     * @return void
     */
    public function testInvokeReturnsArrayRecordUnchangedWhenOtelIsDisabled(): void
    {
        // Arrange
        putenv('OTEL_SDK_DISABLED=true');
        $processor = $this->createOpentelemetryLogProcessor();
        $record = ['context' => ['foo' => 'bar']];

        // Act
        $result = $processor($record);

        // Assert
        $this->assertSame($record, $result);
    }

    /**
     * Uses whatever record shape the installed Monolog version actually produces:
     * a \Monolog\LogRecord on Monolog 3 (Symfony 6/7), a plain array on Monolog 2 (Symfony 5).
     *
     * @return void
     */
    public function testInvokeEnrichesNativeRecordContextWhenOtelIsEnabled(): void
    {
        // Arrange
        putenv('OTEL_SDK_DISABLED=false');
        $processor = $this->createOpentelemetryLogProcessor();
        $record = $this->createLogRecord(['foo' => 'bar']);

        // Act
        $result = $processor($record);

        // Assert
        $this->assertNotSame($record, $result);

        $context = $this->extractContext($result);
        $this->assertSame('bar', $context['foo']);
        $this->assertArrayHasKey('trace_id', $context);
        $this->assertArrayHasKey('span_id', $context);
        $this->assertSame(static::SERVICE_NAME, $context['service.name']);
        $this->assertSame($this->extractMessage($record), $this->extractMessage($result));
        $this->assertSame($this->extractExtra($record), $this->extractExtra($result));
    }

    /**
     * @return void
     */
    public function testInvokeReturnsNativeRecordUnchangedWhenOtelIsDisabled(): void
    {
        // Arrange
        putenv('OTEL_SDK_DISABLED=true');
        $processor = $this->createOpentelemetryLogProcessor();
        $record = $this->createLogRecord(['foo' => 'bar']);

        // Act
        $result = $processor($record);

        // Assert
        $this->assertSame($record, $result);
    }

    /**
     * @return \Spryker\Shared\Opentelemetry\Log\Processor\OpentelemetryLogProcessor
     */
    protected function createOpentelemetryLogProcessor(): OpentelemetryLogProcessor
    {
        $resourceNameReaderMock = $this->createMock(ResourceNameReaderInterface::class);
        $resourceNameReaderMock->method('readName')->willReturn(static::SERVICE_NAME);

        return new OpentelemetryLogProcessor($resourceNameReaderMock);
    }

    /**
     * Builds a real \Monolog\LogRecord when the installed Monolog ships an instantiable one
     * (Monolog 3), or the equivalent array shape when it only ships the forward-compatibility
     * interface of the same name (Monolog 2.4+, no concrete class to instantiate).
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|\Monolog\LogRecord
     */
    protected function createLogRecord(array $context): array|LogRecord
    {
        if (!class_exists(LogRecord::class)) {
            return [
                'message' => 'test message',
                'context' => $context,
                'level' => 200,
                'level_name' => 'INFO',
                'channel' => 'test',
                'datetime' => new DateTimeImmutable(),
                'extra' => [],
            ];
        }

        return new LogRecord(new DateTimeImmutable(), 'test', Level::Info, 'test message', $context);
    }

    /**
     * @param array<string, mixed>|\Monolog\LogRecord $record
     *
     * @return array<string, mixed>
     */
    protected function extractContext(array|LogRecord $record): array
    {
        if ($record instanceof LogRecord) {
            return $record->context;
        }

        return $record['context'];
    }

    /**
     * @param array<string, mixed>|\Monolog\LogRecord $record
     *
     * @return string
     */
    protected function extractMessage(array|LogRecord $record): string
    {
        if ($record instanceof LogRecord) {
            return $record->message;
        }

        return $record['message'];
    }

    /**
     * @param array<string, mixed>|\Monolog\LogRecord $record
     *
     * @return array<string, mixed>
     */
    protected function extractExtra(array|LogRecord $record): array
    {
        if ($record instanceof LogRecord) {
            return $record->extra;
        }

        return $record['extra'];
    }
}
