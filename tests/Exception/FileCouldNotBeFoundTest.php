<?php

declare(strict_types=1);

namespace Stadly\FileWaiter\Exception;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Stadly\FileWaiter\Exception\FileCouldNotBeFound
 * @covers ::<protected>
 * @covers ::<private>
 */
final class FileCouldNotBeFoundTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanConstructException(): void
    {
        $exception = new FileCouldNotBeFound('foo/bar.baz');

        // Force generation of code coverage
        $exceptionConstruct = new FileCouldNotBeFound('foo/bar.baz');
        self::assertEquals($exception, $exceptionConstruct);
    }

    /**
     * @covers ::getFilePath
     */
    public function testCanGetFilePath(): void
    {
        $exception = new FileCouldNotBeFound('foo/bar.baz');

        self::assertSame('foo/bar.baz', $exception->getFilePath());
    }
}
