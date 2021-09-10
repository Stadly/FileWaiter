<?php

declare(strict_types=1);

namespace Stadly\FileWaiter\Adapter;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Stadly\FileWaiter\Exception\FileCouldNotBeFound;
use Stadly\FileWaiter\Exception\StreamCouldNotBeOpened;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\EntityTag\EntityTag;
use Stadly\Http\Header\Value\MediaType\MediaType;

/**
 * @coversDefaultClass \Stadly\FileWaiter\Adapter\Local
 * @covers ::<protected>
 * @covers ::<private>
 */
final class LocalTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanConstructLocalAdapter(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        // Force generation of code coverage
        $fileConstruct = new Local(__FILE__, new HttpFactory());
        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCannotConstructLocalAdapterToNonExistingFile(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);
        unlink($filePath);

        $this->expectException(FileCouldNotBeFound::class);
        new Local($filePath, new HttpFactory());
    }

    /**
     * @covers ::__construct
     */
    public function testCannotConstructLocalAdapterToDirectory(): void
    {
        $this->expectException(FileCouldNotBeFound::class);
        new Local(dirname(__DIR__), new HttpFactory());
    }

    /**
     * @covers ::getFileStream
     */
    public function testFileStreamEmitsFileContents(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        self::assertStringEqualsFile(__FILE__, (string)$file->getFileStream());
    }

    /**
     * @covers ::getFileStream
     */
    public function testCannotOpenFileStreamToNonExistingFile(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);

        $this->expectException(StreamCouldNotBeOpened::class);
        $file->getFileStream();
    }

    /**
     * @covers ::getFileName
     */
    public function testCanGetFileName(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        self::assertSame(basename(__FILE__), $file->getFileName());
    }

    /**
     * @covers ::getFileName
     */
    public function testCanGetFileNameOfNonExistingFile(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);

        self::assertSame(basename($filePath), $file->getFileName());
    }

    /**
     * @covers ::getFileName
     */
    public function testCanGetFileNameOfDirectory(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);
        mkdir($filePath);

        self::assertSame(basename($filePath), $file->getFileName());

        rmdir($filePath);
    }

    /**
     * @covers ::getFileSize
     */
    public function testCanGetFileSize(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        self::assertSame(filesize(__FILE__), $file->getFileSize());
    }

    /**
     * @covers ::getFileSize
     */
    public function testFileSizeOfNonExistingFileIsNull(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);

        self::assertNull($file->getFileSize());
    }

    /**
     * @covers ::getMediaType
     */
    public function testCanGetMediaType(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        $mimeType = mime_content_type(__FILE__);
        assert($mimeType !== false);

        self::assertEquals(MediaType::fromString($mimeType), $file->getMediaType());
    }

    /**
     * @covers ::getMediaType
     */
    public function testMediaTypeOfNonExistingFileIsNull(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);

        self::assertNull($file->getMediaType());
    }

    /**
     * @covers ::getMediaType
     */
    public function testMediaTypeOfDirectoryIsNull(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);
        mkdir($filePath);

        self::assertNull($file->getMediaType());

        rmdir($filePath);
    }

    /**
     * @covers ::getLastModifiedDate
     */
    public function testCanGetLastModifiedDate(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        $timestamp = filemtime(__FILE__);
        assert($timestamp !== false);

        self::assertEquals(Date::fromTimestamp($timestamp), $file->getLastModifiedDate());
    }

    /**
     * @covers ::getLastModifiedDate
     */
    public function testLastModifiedDateOfNonExistingFileIsNull(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);

        self::assertNull($file->getLastModifiedDate());
    }

    /**
     * @covers ::getLastModifiedDate
     */
    public function testCanGetLastModifiedDateOfDirectory(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);
        mkdir($filePath);

        $timestamp = filemtime($filePath);
        assert($timestamp !== false);

        self::assertEquals(Date::fromTimestamp($timestamp), $file->getLastModifiedDate());

        rmdir($filePath);
    }

    /**
     * @covers ::getEntityTag
     */
    public function testCanGetEntityTag(): void
    {
        $file = new Local(__FILE__, new HttpFactory());

        $md5 = md5_file(__FILE__);
        assert($md5 !== false);

        self::assertEquals(new EntityTag($md5), $file->getEntityTag());
    }

    /**
     * @covers ::getEntityTag
     */
    public function testEntityTagOfNonExistingFileIsNull(): void
    {
        $filePath = tempnam(__DIR__, 'tmp');
        assert($filePath !== false);

        $file = new Local($filePath, new HttpFactory());
        unlink($filePath);

        self::assertNull($file->getEntityTag());
    }
}
