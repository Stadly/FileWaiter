<?php

declare(strict_types=1);

namespace Stadly\FileWaiter\Adapter;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Stadly\FileWaiter\Adapter;
use Stadly\FileWaiter\Exception\FileCouldNotBeFound;
use Stadly\FileWaiter\Exception\StreamCouldNotBeOpened;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\EntityTag\EntityTag;
use Stadly\Http\Header\Value\MediaType\MediaType;

/**
 * Adapter for handling files stored in the local file system.
 */
final class Local implements Adapter
{
    /**
     * @var string Path to the file in the local file system.
     */
    private $filePath;

    /**
     * @var StreamFactoryInterface Factory for creating streams.
     */
    private $streamFactory;

    /**
     * Constructor.
     *
     * @param string $filePath Path to the file in the local file system.
     * @param StreamFactoryInterface $streamFactory Factory for creating streams.
     * @throws FileCouldNotBeFound If the file does not exist.
     */
    public function __construct(string $filePath, StreamFactoryInterface $streamFactory)
    {
        if (!is_file($filePath)) {
            throw new FileCouldNotBeFound($filePath);
        }

        $this->filePath = $filePath;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @inheritdoc
     */
    public function getFileStream(): StreamInterface
    {
        try {
            return $this->streamFactory->createStreamFromFile($this->filePath);
        } catch (RuntimeException $exception) {
            throw new StreamCouldNotBeOpened($this->filePath, $exception);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFileName(): string
    {
        return basename($this->filePath);
    }

    /**
     * @inheritdoc
     */
    public function getFileSize(): ?int
    {
        // phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
        $fileSize = @filesize($this->filePath);
        // phpcs:enable

        if ($fileSize === false) {
            return null;
        }

        return $fileSize;
    }

    /**
     * @inheritdoc
     */
    public function getMediaType(): ?MediaType
    {
        // phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
        $mediaTypeString = @mime_content_type($this->filePath);
        // phpcs:enable

        if ($mediaTypeString === false || $mediaTypeString === 'directory') {
            return null;
        }

        return MediaType::fromString($mediaTypeString);
    }

    /**
     * @inheritdoc
     */
    public function getLastModifiedDate(): ?Date
    {
        // phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
        $timestamp = @filemtime($this->filePath);
        // phpcs:enable

        if ($timestamp === false) {
            return null;
        }

        return Date::fromTimestamp($timestamp);
    }

    /**
     * @inheritdoc
     */
    public function getEntityTag(): ?EntityTag
    {
        // phpcs:disable Generic.PHP.NoSilencedErrors.Discouraged
        $entityTagString = @md5_file($this->filePath);
        // phpcs:enable

        if ($entityTagString === false) {
            return null;
        }

        return new EntityTag($entityTagString);
    }
}
