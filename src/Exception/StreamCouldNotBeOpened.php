<?php

declare(strict_types=1);

namespace Stadly\FileWaiter\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a file stream could not be opened.
 */
final class StreamCouldNotBeOpened extends RuntimeException
{
    /**
     * @var string Path to file.
     */
    private $filePath;

    /**
     * Constructor.
     *
     * @param string $filePath Path to file.
     * @param Throwable $previous Previous exception, used for exception chaining.
     */
    public function __construct(string $filePath, ?Throwable $previous = null)
    {
        $this->filePath = $filePath;

        parent::__construct('File stream could not be opened: ' . $filePath, /*code*/0, $previous);
    }

    /**
     * @return string Path to file.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
