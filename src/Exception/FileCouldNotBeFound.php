<?php

declare(strict_types=1);

namespace Stadly\FileWaiter\Exception;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when a file could not be found.
 */
final class FileCouldNotBeFound extends RuntimeException
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

        parent::__construct('File could not be found: ' . $filePath, /*code*/0, $previous);
    }

    /**
     * @return string Path to file.
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }
}
