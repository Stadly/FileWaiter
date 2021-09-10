<?php

declare(strict_types=1);

namespace Stadly\FileWaiter;

use DateTimeImmutable;
use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Stadly\FileWaiter\Adapter\ByteString;
use Stadly\FileWaiter\Adapter\Local;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\EntityTag\EntityTag;
use Stadly\Http\Header\Value\MediaType\MediaType;

/**
 * @coversDefaultClass \Stadly\FileWaiter\File
 * @covers ::<protected>
 * @covers ::<private>
 */
final class FileTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanConstructFileWithoutPopulatingInfo(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName(null);
        $file->setFileSize(null);
        $file->setMediaType(null);
        $file->setLastModifiedDate(null);
        $file->setEntityTag(null);

        $fileConstruct = new File($fileAdapter, File::NO_INFO);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructFilePopulatingFileName(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName($fileAdapter->getFileName());
        $file->setFileSize(null);
        $file->setMediaType(null);
        $file->setLastModifiedDate(null);
        $file->setEntityTag(null);

        $fileConstruct = new File($fileAdapter, File::FILE_NAME);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructFilePopulatingFileSize(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName(null);
        $file->setFileSize($fileAdapter->getFileSize());
        $file->setMediaType(null);
        $file->setLastModifiedDate(null);
        $file->setEntityTag(null);

        $fileConstruct = new File($fileAdapter, File::FILE_SIZE);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructFilePopulatingMediaType(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName(null);
        $file->setFileSize(null);
        $file->setMediaType($fileAdapter->getMediaType());
        $file->setLastModifiedDate(null);
        $file->setEntityTag(null);

        $fileConstruct = new File($fileAdapter, File::MEDIA_TYPE);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructFilePopulatingLastModifiedDate(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName(null);
        $file->setFileSize(null);
        $file->setMediaType(null);
        $file->setLastModifiedDate($fileAdapter->getLastModifiedDate());
        $file->setEntityTag(null);

        $fileConstruct = new File($fileAdapter, File::LAST_MODIFIED_DATE);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructFilePopulatingEntityTag(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName(null);
        $file->setFileSize(null);
        $file->setMediaType(null);
        $file->setLastModifiedDate(null);
        $file->setEntityTag($fileAdapter->getEntityTag());

        $fileConstruct = new File($fileAdapter, File::ENTITY_TAG);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::__construct
     */
    public function testCanConstructFilePopulatingAllInfo(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);
        $file->setFileName($fileAdapter->getFileName());
        $file->setFileSize($fileAdapter->getFileSize());
        $file->setMediaType($fileAdapter->getMediaType());
        $file->setLastModifiedDate($fileAdapter->getLastModifiedDate());
        $file->setEntityTag($fileAdapter->getEntityTag());

        $fileConstruct = new File($fileAdapter, File::ALL_INFO);

        self::assertEquals($file, $fileConstruct);
    }

    /**
     * @covers ::getFileStream
     */
    public function testFileStreamEmitsFileContents(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$file->getFileStream());
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateNoInfo(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::NO_INFO);

        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::NO_INFO);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateFileName(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::FILE_NAME);

        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::FILE_NAME);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateFileSize(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::FILE_SIZE);

        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::FILE_SIZE);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateMediaType(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::MEDIA_TYPE);

        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::MEDIA_TYPE);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateLastModifiedDate(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::LAST_MODIFIED_DATE);

        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::LAST_MODIFIED_DATE);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateEntityTag(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::ENTITY_TAG);
        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::ENTITY_TAG);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::populateInfo
     */
    public function testCanPopulateAllInfo(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());

        $file = new File($fileAdapter, File::ALL_INFO);

        $filePopulateInfo = new File($fileAdapter, File::NO_INFO);
        $filePopulateInfo->populateInfo(File::ALL_INFO);

        self::assertEquals($file, $filePopulateInfo);
    }

    /**
     * @covers ::getFileName
     */
    public function testCanGetFileName(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());
        $file = new File($fileAdapter, File::FILE_NAME);

        self::assertSame($fileAdapter->getFileName(), $file->getFileName());
    }

    /**
     * @covers ::setFileName
     */
    public function testCanSetFileName(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::FILE_NAME);
        $file->setFileName('foo');

        self::assertSame('foo', $file->getFileName());
    }

    /**
     * @covers ::setFileName
     */
    public function testCanSetFileNameToNull(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::FILE_NAME);
        $file->setFileName(null);

        self::assertNull($file->getFileName());
    }

    /**
     * @covers ::getFileSize
     */
    public function testCanGetFileSize(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());
        $file = new File($fileAdapter, File::FILE_SIZE);

        self::assertSame($fileAdapter->getFileSize(), $file->getFileSize());
    }

    /**
     * @covers ::setFileSize
     */
    public function testCanSetFileSize(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::FILE_SIZE);
        $file->setFileSize(1234);

        self::assertSame(1234, $file->getFileSize());
    }

    /**
     * @covers ::setFileSize
     */
    public function testCanSetFileSizeToNull(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::FILE_SIZE);
        $file->setFileSize(null);

        self::assertNull($file->getFileSize());
    }

    /**
     * @covers ::setFileSize
     */
    public function testCanSetFileSizeToZero(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::FILE_SIZE);
        $file->setFileSize(0);

        self::assertSame(0, $file->getFileSize());
    }

    /**
     * @covers ::setFileSize
     */
    public function testCannotSetNegativeFileSize(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::FILE_SIZE);

        $this->expectException(InvalidArgumentException::class);
        $file->setFileSize(-1);
    }

    /**
     * @covers ::getMediaType
     */
    public function testCanGetMediaType(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());
        $file = new File($fileAdapter, File::MEDIA_TYPE);

        self::assertEquals($fileAdapter->getMediaType(), $file->getMediaType());
    }

    /**
     * @covers ::setMediaType
     */
    public function testCanSetMediaType(): void
    {
        $mediaType = new MediaType('foo', 'bar');

        $file = new File(new Local(__FILE__, new HttpFactory()), File::MEDIA_TYPE);
        $file->setMediaType($mediaType);

        self::assertSame($mediaType, $file->getMediaType());
    }

    /**
     * @covers ::setMediaType
     */
    public function testCanSetMediaTypeToNull(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::MEDIA_TYPE);
        $file->setMediaType(null);

        self::assertNull($file->getMediaType());
    }

    /**
     * @covers ::getLastModifiedDate
     */
    public function testCanGetLastModifiedDate(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());
        $file = new File($fileAdapter, File::LAST_MODIFIED_DATE);

        self::assertEquals($fileAdapter->getLastModifiedDate(), $file->getLastModifiedDate());
    }

    /**
     * @covers ::setLastModifiedDate
     */
    public function testCanSetLastModifiedDate(): void
    {
        $lastModifiedDate = new Date(new DateTimeImmutable('2001-02-03 04:05:06'));

        $file = new File(new Local(__FILE__, new HttpFactory()), File::LAST_MODIFIED_DATE);
        $file->setLastModifiedDate($lastModifiedDate);

        self::assertSame($lastModifiedDate, $file->getLastModifiedDate());
    }

    /**
     * @covers ::setLastModifiedDate
     */
    public function testCanSetLastModifiedDateToNull(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::LAST_MODIFIED_DATE);
        $file->setLastModifiedDate(null);

        self::assertNull($file->getLastModifiedDate());
    }

    /**
     * @covers ::getEntityTag
     */
    public function testCanGetEntityTag(): void
    {
        $fileAdapter = new Local(__FILE__, new HttpFactory());
        $file = new File($fileAdapter, File::ENTITY_TAG);

        self::assertEquals($fileAdapter->getEntityTag(), $file->getEntityTag());
    }

    /**
     * @covers ::setEntityTag
     */
    public function testCanSetEntityTag(): void
    {
        $entityTag = new EntityTag('foo');

        $file = new File(new Local(__FILE__, new HttpFactory()), File::ENTITY_TAG);
        $file->setEntityTag($entityTag);

        self::assertSame($entityTag, $file->getEntityTag());
    }

    /**
     * @covers ::setEntityTag
     */
    public function testCanSetEntityTagToNull(): void
    {
        $file = new File(new Local(__FILE__, new HttpFactory()), File::ENTITY_TAG);
        $file->setEntityTag(null);

        self::assertNull($file->getEntityTag());
    }
}
