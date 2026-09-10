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
    public function testInvokeReturnsNewLogRecordWithEnrichedContextWhenOtelIsEnabled(): void
    {
        // Arrange
        putenv('OTEL_SDK_DISABLED=false');
        $processor = $this->createOpentelemetryLogProcessor();
        $record = $this->createLogRecord(['foo' => 'bar']);

        // Act
        $result = $processor($record);

        // Assert
        $this->assertNotSame($record, $result);
        $this->assertSame('bar', $result->context['foo']);
        $this->assertArrayHasKey('trace_id', $result->context);
        $this->assertArrayHasKey('span_id', $result->context);
        $this->assertSame(static::SERVICE_NAME, $result->context['service.name']);
        $this->assertSame($record->message, $result->message);
        $this->assertSame($record->extra, $result->extra);
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
     * @return void
     */
    public function testInvokeReturnsLogRecordUnchangedWhenOtelIsDisabled(): void
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
     * @param array<string, mixed> $context
     *
     * @return \Monolog\LogRecord
     */
    protected function createLogRecord(array $context): LogRecord
    {
        return new LogRecord(
            new DateTimeImmutable(),
            'test',
            Level::Info,
            'test message',
            $context,
        );
    }
}
