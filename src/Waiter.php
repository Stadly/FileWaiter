<?php

declare(strict_types=1);

namespace Stadly\FileWaiter;

use GuzzleHttp\Psr7\AppendStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Stadly\FileWaiter\Exception\StreamCouldNotBeOpened;
use Stadly\Http\Exception\InvalidHeader;
use Stadly\Http\Header\Request\IfMatch;
use Stadly\Http\Header\Request\IfModifiedSince;
use Stadly\Http\Header\Request\IfNoneMatch;
use Stadly\Http\Header\Request\IfRange;
use Stadly\Http\Header\Request\IfUnmodifiedSince;
use Stadly\Http\Header\Request\Range;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\Range\ByteRange;
use Stadly\Http\Header\Value\Range\ByteRangeSet;

/**
 * Class for handling file orders.
 */
final class Waiter implements RequestHandlerInterface
{
    /**
     * @var File The file to serve.
     */
    private $file;

    /**
     * @var ResponseFactoryInterface Factory for creating responses.
     */
    private $responseFactory;

    /**
     * @param File $file The file to serve.
     * @param ResponseFactoryInterface $responseFactory Factory for creating responses.
     */
    public function __construct(File $file, ResponseFactoryInterface $responseFactory)
    {
        $this->file = $file;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Serve the file according to the request.
     * Generates a response with the appropriate HTTP response code, headers and body.
     *
     * @param ServerRequestInterface $request Request to handle.
     * @return ResponseInterface Response populated with data according to the request.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response = $this->populateHeaders($response);

        if (!$this->checkIfMatch($request)) {
            return $response->withStatus(412);
        }

        if (!$this->checkIfUnmodifiedSince($request)) {
            return $response->withStatus(412);
        }

        if (!$this->checkIfNoneMatch($request)) {
            switch ($request->getMethod()) {
                case 'GET':
                case 'HEAD':
                    // 304 (Not modified) if request method is GET or HEAD
                    return $response->withStatus(304);
                default:
                    // 412 (Precondition Failed) otherwise.
                    return $response->withStatus(412);
            }
        }

        if (!$this->checkIfModifiedSince($request)) {
            return $response->withStatus(304);
        }

        $rangeSet = $this->getRangeSet($request);
        if ($rangeSet !== null) {
            return $this->serveRangeSet($request, $response, $rangeSet);
        }

        return $this->serveFile($response);
    }

    /**
     * Populate the response with the HTTP headers common for all responses.
     *
     * @param ResponseInterface $response Response to populate.
     * @return ResponseInterface Response populated with common headers.
     */
    private function populateHeaders(ResponseInterface $response): ResponseInterface
    {
        $response = $response->withHeader('Accept-Ranges', 'bytes');
        $response = $response->withHeader('Date', (string)Date::fromTimestamp(time()));
        if ($this->file->getEntityTag() !== null) {
            $response = $response->withHeader('ETag', (string)$this->file->getEntityTag());
        }
        if ($this->file->getLastModifiedDate() !== null) {
            $response = $response->withHeader('Last-Modified', (string)$this->file->getLastModifiedDate());
        }

        return $response;
    }

    /**
     * @param RequestInterface $request The request.
     * @return ByteRangeSet|null Set of byte ranges to serve.
     */
    private function getRangeSet(RequestInterface $request): ?ByteRangeSet
    {
        // Ignore if request method is other than GET.
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        if (!$request->hasHeader('Range')) {
            return null;
        }

        if (!$this->checkIfRange($request)) {
            return null;
        }

        try {
            $range = Range::fromValue($request->getHeaderLine('Range'));
        } catch (InvalidHeader $exception) {
            // Ignore invalid Range header.
            return null;
        }

        // Only byte ranges are supported. Other range types are ignored.
        if ($range->getRangeSet() instanceof ByteRangeSet) {
            return $range->getRangeSet();
        }

        return null;
    }

    /**
     * Serve the file, without taking preconditions and ranges into consideration.
     * Set Content-Type and Content-Length headers, and populate with file contents.
     *
     * @param ResponseInterface $response Preliminary response.
     * @return ResponseInterface Populated response.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    private function serveFile(ResponseInterface $response): ResponseInterface
    {
        $response = $response->withStatus(200);

        if ($this->file->getFileSize() !== null) {
            $response = $response->withHeader('Content-Length', (string)$this->file->getFileSize());
        }

        if ($this->file->getMediaType() !== null) {
            $response = $response->withHeader('Content-Type', (string)$this->file->getMediaType());
        }

        return $response->withBody($this->file->getFileStream());
    }

    /**
     * Serve any satisfiable byte ranges from a set of ranges.
     * Set HTTP response code and headers, and populate with file contents.
     *
     * @param RequestInterface $request The request.
     * @param ResponseInterface $response Preliminary response.
     * @param ByteRangeSet $rangeSet Set of ranges to serve.
     * @return ResponseInterface Populated response.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    private function serveRangeSet(
        RequestInterface $request,
        ResponseInterface $response,
        ByteRangeSet $rangeSet
    ): ResponseInterface {
        $fileSize = $this->file->getFileSize();

        $ranges = [];
        foreach ($rangeSet as $range) {
            if ($range->coversFile($fileSize)) {
                return $this->serveFile($response);
            }
            if ($range->isSatisfiable($fileSize)) {
                $ranges[] = $range;
            }
        }

        switch (count($ranges)) {
            case 0:
                // A Content-Range header indicating the file size cannot be sent when the file size is unknown,
                // even though this SHOULD be done when generating a 416 response to a byte-range request.
                if ($fileSize !== null) {
                    $response = $response->withHeader('Content-Range', 'bytes */' . $fileSize);
                }
                return $response->withStatus(416);

            case 1:
                return $this->serveRange($request, $response, ...$ranges);

            default:
                return $this->serveRanges($request, $response, ...$ranges);
        }
    }

    /**
     * Serve a single byte range.
     * Set HTTP response code and headers, and populate with file contents.
     *
     * @param RequestInterface $request The request.
     * @param ResponseInterface $response Preliminary response.
     * @param ByteRange $range Range to serve.
     * @return ResponseInterface Populated response.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    private function serveRange(
        RequestInterface $request,
        ResponseInterface $response,
        ByteRange $range
    ): ResponseInterface {
        $response = $response->withStatus(206);
        if ($request->hasHeader('If-Range')) {
            $response = $response->withoutHeader('Content-Type');
            $response = $response->withoutHeader('Content-Encoding');
            $response = $response->withoutHeader('Content-Language');
        }

        if ($this->file->getMediaType() !== null) {
            $response = $response->withHeader('Content-Type', (string)$this->file->getMediaType());
        }

        $response = $response->withHeader(
            'Content-Range',
            sprintf(
                'bytes %d-%d/%s',
                $range->getFirstBytePos($this->file->getFileSize()),
                $range->getLastBytePos($this->file->getFileSize()),
                $this->file->getFileSize() ?? '*'
            )
        );

        $response = $response->withHeader('Content-Length', (string)$range->getLength($this->file->getFileSize()));

        return $response->withBody($this->getRangeStream($range));
    }

    /**
     * Serve multiple byte ranges.
     * Set HTTP response code and headers, and populate with file contents.
     *
     * @param RequestInterface $request The request.
     * @param ResponseInterface $response Preliminary response.
     * @param ByteRange ...$ranges Ranges to serve.
     * @return ResponseInterface Populated response.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    private function serveRanges(
        RequestInterface $request,
        ResponseInterface $response,
        ByteRange ...$ranges
    ): ResponseInterface {
        $boundary = md5(uniqid(/*prefix*/(string)rand(), /*more_entropy*/true));

        $fileStream = new AppendStream();
        foreach ($ranges as $range) {
            $fileStream->addStream(Utils::streamFor($this->getRangeHeader($range, $boundary)));
            $fileStream->addStream($this->getRangeStream($range));
        }
        $fileStream->addStream(Utils::streamFor("\r\n--" . $boundary . "--\r\n"));

        $response = $response->withStatus(206);
        $response = $response->withHeader('Content-Type', 'multipart/byteranges; boundary=' . $boundary);
        $response = $response->withHeader('Content-Length', (string)$fileStream->getSize());
        $response = $response->withoutHeader('Content-Range');
        if ($request->hasHeader('If-Range')) {
            $response = $response->withoutHeader('Content-Encoding');
            $response = $response->withoutHeader('Content-Language');
        }

        return $response->withBody($fileStream);
    }

    /**
     * Get header for range to be used when serving multiple ranges.
     *
     * @param ByteRange $range Range to serve.
     * @param string $boundary Boundary used to separate the parts of the multi-part message.
     * @return string Header for the range.
     */
    private function getRangeHeader(ByteRange $range, string $boundary): string
    {
        $header = "\r\n--" . $boundary . "\r\n";
        if ($this->file->getMediaType() !== null) {
            $header .= 'Content-Type: ' . $this->file->getMediaType() . "\r\n";
        }
        $header .= sprintf(
            "Content-Range: bytes %d-%d/%s\r\n\r\n",
            $range->getFirstBytePos($this->file->getFileSize()),
            $range->getLastBytePos($this->file->getFileSize()),
            $this->file->getFileSize() ?? '*'
        );

        return $header;
    }

    /**
     * Serve a byte range.
     *
     * @param ByteRange $range Range to serve.
     * @return StreamInterface Stream serving the range.
     * @throws StreamCouldNotBeOpened If the file stream could not be opened.
     */
    private function getRangeStream(ByteRange $range): StreamInterface
    {
        $fileStream = $this->file->getFileStream();
        return new LimitStream(
            $fileStream,
            $range->getLength($this->file->getFileSize()),
            $range->getFirstBytePos($this->file->getFileSize())
        );
    }

    /**
     * @param RequestInterface $request The request.
     * @return bool Whether the if-match-condition is satisfied, invalid or not set.
     */
    private function checkIfMatch(RequestInterface $request): bool
    {
        if (!$request->hasHeader('If-Match')) {
            return true;
        }

        try {
            $ifMatch = IfMatch::fromValue($request->getHeaderLine('If-Match'));
        } catch (InvalidHeader $exception) {
            // Ignore invalid If-Match header.
            return true;
        }

        return $ifMatch->evaluate($this->file->getEntityTag());
    }

    /**
     * @param RequestInterface $request The request.
     * @return bool Whether the if-unmodified-since-condition is satisfied, invalid or not set.
     */
    private function checkIfUnmodifiedSince(RequestInterface $request): bool
    {
        // Ignore if the if-match-condition is set.
        if ($request->hasHeader('If-Match')) {
            return true;
        }

        if (!$request->hasHeader('If-Unmodified-Since')) {
            return true;
        }

        try {
            $ifUnmodifiedSince = IfUnmodifiedSince::fromValue($request->getHeaderLine('If-Unmodified-Since'));
        } catch (InvalidHeader $exception) {
            // Ignore invalid If-Unmodified-Since header.
            return true;
        }

        return $ifUnmodifiedSince->evaluate($this->file->getLastModifiedDate());
    }

    /**
     * @param RequestInterface $request The request.
     * @return bool Whether the if-none-match-condition is satisfied, invalid or not set.
     */
    private function checkIfNoneMatch(RequestInterface $request): bool
    {
        if (!$request->hasHeader('If-None-Match')) {
            return true;
        }

        try {
            $ifNoneMatch = IfNoneMatch::fromValue($request->getHeaderLine('If-None-Match'));
        } catch (InvalidHeader $exception) {
            // Ignore invalid If-None-Match header.
            return true;
        }

        return $ifNoneMatch->evaluate($this->file->getEntityTag());
    }

    /**
     * @param RequestInterface $request The request.
     * @return bool Whether the if-modified-since-condition is satisfied, invalid or not set.
     */
    private function checkIfModifiedSince(RequestInterface $request): bool
    {
        // Ignore if the if-none-match-condition is set.
        if ($request->hasHeader('If-None-Match')) {
            return true;
        }

        // Ignore if request method is other than GET or HEAD.
        if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'HEAD') {
            return true;
        }

        if (!$request->hasHeader('If-Modified-Since')) {
            return true;
        }

        try {
            $ifModifiedSince = IfModifiedSince::fromValue($request->getHeaderLine('If-Modified-Since'));
        } catch (InvalidHeader $exception) {
            // Ignore invalid If-Modified-Since header.
            return true;
        }

        return $ifModifiedSince->evaluate($this->file->getLastModifiedDate());
    }

    /**
     * @param RequestInterface $request The request.
     * @return bool Whether the if-range-condition is satisfied, invalid or not set.
     */
    private function checkIfRange(RequestInterface $request): bool
    {
        if (!$request->hasHeader('If-Range')) {
            return true;
        }

        try {
            $ifRange = IfRange::fromValue($request->getHeaderLine('If-Range'));
        } catch (InvalidHeader $exception) {
            // Ignore invalid If-Range header.
            return true;
        }

        $entityTag = $this->file->getEntityTag();
        $lastModifiedDate = $this->file->getLastModifiedDate();
        return $ifRange->evaluate($entityTag) || $ifRange->evaluate($lastModifiedDate);
    }
}
