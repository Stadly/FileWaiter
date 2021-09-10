<?php

declare(strict_types=1);

namespace Stadly\FileWaiter\Exception;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Stadly\FileWaiter\Exception\StreamCouldNotBeOpened
 * @covers ::<protected>
 * @covers ::<private>
 */
final class StreamCouldNotBeOpenedTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanConstructException(): void
    {
        $exception = new StreamCouldNotBeOpened('foo/bar.baz');

        // Force generation of code coverage
        $exceptionConstruct = new StreamCouldNotBeOpened('foo/bar.baz');
        self::assertEquals($exception, $exceptionConstruct);
    }

    /**
     * @covers ::getFilePath
     */
    public function testCanGetFilePath(): void
    {
        $exception = new StreamCouldNotBeOpened('foo/bar.baz');

        self::assertSame('foo/bar.baz', $exception->getFilePath());
    }
}
