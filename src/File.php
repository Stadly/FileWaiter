<?php

declare(strict_types=1);

namespace Stadly\FileWaiter;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Stadly\FileWaiter\Exception\StreamCouldNotBeOpened;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\EntityTag\EntityTag;
use Stadly\Http\Header\Value\MediaType\MediaType;

/**
 * Class for handling files.
 */
final class File
{
    public const NO_INFO = 0;
    public const FILE_NAME = 1;
    public const FILE_SIZE = 2;
    public const MEDIA_TYPE = 4;
    public const LAST_MODIFIED_DATE = 8;
    public const ENTITY_TAG = 16;
    public const ALL_INFO = 32 - 1;

    /**
     * @var Adapter Adapter used to access the file in its file system.
     */
    private $adapter;

    /**
     * @var string|null File name.
     */
    private $fileName = null;

    /**
     * @var int|null File size.
     */
    private $fileSize = null;

    /**
     * @var MediaType|null Media type of the file.
     */
    private $mediaType = null;

    /**
     * @var Date|null Date when the file was last modified.
     */
    private $lastModifiedDate = null;

    /**
     * @var EntityTag|null Entity tag for the file.
     */
    private $entityTag = null;

    /**
     * Constructor.
     *
     * @param Adapter $adapter Adapter used to access the file in its file system.
     * @param int $populateInfo Bit mask specifying which information to populate from the file's adapter.
     */
    public function __construct(Adapter $adapter, int $populateInfo = self::ALL_INFO)
    {
        $this->adapter = $adapter;
        $this->populateInfo($populateInfo);
    }

    /**
     * @return StreamInterface File stream that can be used to read from the file.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    public function getFileStream(): StreamInterface
    {
        return $this->adapter->getFileStream();
    }

    /**
     * Populate information retrieved from the file's adapter.
     *
     * @param int $whichInfo Bit mask specifying which information to populate from the file's adapter.
     */
    public function populateInfo(int $whichInfo = self::ALL_INFO): void
    {
        if ((self::FILE_NAME & $whichInfo) !== 0) {
            $this->setFileName($this->adapter->getFileName());
        }

        if ((self::FILE_SIZE & $whichInfo) !== 0) {
            $this->setFileSize($this->adapter->getFileSize());
        }

        if ((self::MEDIA_TYPE & $whichInfo) !== 0) {
            $this->setMediaType($this->adapter->getMediaType());
        }

        if ((self::LAST_MODIFIED_DATE & $whichInfo) !== 0) {
            $this->setLastModifiedDate($this->adapter->getLastModifiedDate());
        }

        if ((self::ENTITY_TAG & $whichInfo) !== 0) {
            $this->setEntityTag($this->adapter->getEntityTag());
        }
    }

    /**
     * @return string|null File name, or null if not set.
     */
    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    /**
     * @param string|null $fileName File name.
     */
    public function setFileName(?string $fileName): void
    {
        $this->fileName = $fileName;
    }

    /**
     * @return int|null File size, or null if not set.
     */
    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    /**
     * @param int|null $fileSize File size.
     */
    public function setFileSize(?int $fileSize): void
    {
        // File size must be non-negative.
        if ($fileSize < 0) {
            throw new InvalidArgumentException('Invalid file size: ' . $fileSize);
        }

        $this->fileSize = $fileSize;
    }

    /**
     * @return MediaType|null Media type of the file, or null if not set.
     */
    public function getMediaType(): ?MediaType
    {
        return $this->mediaType;
    }

    /**
     * @param MediaType|null $mediaType Media type of the file.
     */
    public function setMediaType(?MediaType $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    /**
     * @return Date|null Date when the file was last modified, or null if not set.
     */
    public function getLastModifiedDate(): ?Date
    {
        return $this->lastModifiedDate;
    }

    /**
     * @param Date|null $date Date when the file was last modified.
     */
    public function setLastModifiedDate(?Date $date): void
    {
        $this->lastModifiedDate = $date;
    }

    /**
     * @return EntityTag|null Entity tag for the file, or null if not set.
     */
    public function getEntityTag(): ?EntityTag
    {
        return $this->entityTag;
    }

    /**
     * @param EntityTag|null $entityTag Entity tag for the file.
     */
    public function setEntityTag(?EntityTag $entityTag): void
    {
        $this->entityTag = $entityTag;
    }
}
