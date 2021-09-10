<?php

declare(strict_types=1);

namespace Stadly\FileWaiter;

use Psr\Http\Message\StreamInterface;
use Stadly\FileWaiter\Exception\StreamCouldNotBeOpened;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\EntityTag\EntityTag;
use Stadly\Http\Header\Value\MediaType\MediaType;

/**
 * Interface for file adapters.
 */
interface Adapter
{
    /**
     * @return StreamInterface File stream that can be used to read from the file.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    public function getFileStream(): StreamInterface;

    /**
     * @return string|null File name, or null if not known.
     */
    public function getFileName(): ?string;

    /**
     * @return int|null File size, or null if not known.
     */
    public function getFileSize(): ?int;

    /**
     * @return MediaType|null Media type of the file, or null if not known.
     */
    public function getMediaType(): ?MediaType;

    /**
     * @return Date|null Date when the file was last modified, or null if not known.
     */
    public function getLastModifiedDate(): ?Date;

    /**
     * @return EntityTag|null Entity tag for the file, or null if not known.
     */
    public function getEntityTag(): ?EntityTag;
}
