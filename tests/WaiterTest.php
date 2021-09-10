<?php

declare(strict_types=1);

namespace Stadly\FileWaiter;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Stadly\FileWaiter\Adapter\ByteString;
use Stadly\Http\Header\Value\Date;
use Stadly\Http\Header\Value\EntityTag\EntityTag;
use Stadly\Http\Header\Value\MediaType\MediaType;

/**
 * @coversDefaultClass \Stadly\FileWaiter\Waiter
 * @covers ::<protected>
 * @covers ::<private>
 */
final class WaiterTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testCanConstructWaiter(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $waiter = new Waiter($file, new HttpFactory());

        // Force generation of code coverage
        $waiterConstruct = new Waiter($file, new HttpFactory());
        self::assertEquals($waiter, $waiterConstruct);
    }

    /**
     * @covers ::handle
     */
    public function testServingFileEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileName('foo');
        $file->setFileSize(26);
        $file->setMediaType(new MediaType('foo', 'bar'));
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Content-Type' => ['foo/bar'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingEmptyFileEmitsOk(): void
    {
        $file = new File(new ByteString('', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(0);

        $request = new ServerRequest('GET', '');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['0'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchMathcesUniversallyEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Match', '*');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchMatchesStrongEntityTagEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Match', '"foo", W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchMatchesWeakEntityTagEmitsPreconditionFailed(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('bar', /*isWeak*/true));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Match', '"foo", W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['W/"bar"'],
        ];

        self::assertSame(412, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchDoesNotMatchEmitsPreconditionFailed(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Match', '"foo", W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(412, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchIsInvalidEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Match', 'foo');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfUnmodifiedSinceIsLaterThanLastModifiedDateEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfUnmodifiedSinceIsEqualToLastModifiedDateEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfUnmodifiedSinceIsEarlierThanLastModifiedDateEmitsPreconditionFailed(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(412, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfUnmodifiedSinceIsInvalidEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', '2001-02-03 04:05:06');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfUnmodifiedSinceIsValidAndLastModifiedDateIsUnknownEmitsPrecondFailed(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(412, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchIsTrueAndIfUnmodifiedSinceIsTrueEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));
        $file->setEntityTag(new EntityTag('bar'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');
        $request = $request->withHeader('If-Match', '"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"bar"'],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfMatchIsTrueAndIfUnmodifiedSinceIsFalseEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT'))));
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Unmodified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');
        $request = $request->withHeader('If-Match', '"foo"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchIsFalseAndRequestMethodIsGetEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-None-Match', '*');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchIsFalseAndRequestMethodIsHeadEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('HEAD', '');
        $request = $request->withHeader('If-None-Match', '*');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchIsFalseAndRequestMethodIsNotGetNorHeadEmitsPreconditionFailed(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('PUT', '');
        $request = $request->withHeader('If-None-Match', '*');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(412, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchMatchesUniversallyEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-None-Match', '*');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchMatchesStrongEntityTagEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-None-Match', '"foo", W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchMatchesWeakEntityTagEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setEntityTag(new EntityTag('bar', /*isWeak*/true));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-None-Match', '"foo", W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['W/"bar"'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchDoesNotMatchEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-None-Match', '"foo", W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchIsInvalidEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-None-Match', 'foo');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsFalseAndRequestMethodIsGetEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsFalseAndRequestMethodIsHeadEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('HEAD', '');
        $request = $request->withHeader('If-Modified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsFalseAndRequestMethodIsNotGetNorHeadEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('PUT', '');
        $request = $request->withHeader('If-Modified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsLaterThanLastModifiedDateEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsEqualToLastModifiedDateEmitsNotModified(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(304, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsEarlierThanLastModifiedDateEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsInvalidEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', '2001-02-03 04:05:06');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfModifiedSinceIsValidAndLastModifiedDateIsUnknownEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchIsTrueAndIfModifiedSinceIsTrueEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT'))));
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Sat, 03 Feb 2001 04:05:06 GMT');
        $request = $request->withHeader('If-None-Match', '"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingFileWhenIfNoneMatchIsTrueAndIfModifiedSinceIsFalseEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('If-Modified-Since', 'Mon, 04 Mar 2002 05:06:07 GMT');
        $request = $request->withHeader('If-None-Match', '"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringPastEndOfFileEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['16'],
            'Content-Range' => ['bytes 10-25/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromStartToPastEndOfFileEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromPastEndOfFileEmitsRangeNotSatisfiable(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=26-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Range' => ['bytes */26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(416, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeWhenFileSizeIsOneEmitsOk(): void
    {
        $file = new File(new ByteString('a', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(1);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-0');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('a', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeFromStartToPastEndOfFileWhenFileSizeIsOneEmitsOk(): void
    {
        $file = new File(new ByteString('a', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(1);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('a', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeWhenFileSizeIsZeroEmitsOk(): void
    {
        $file = new File(new ByteString('', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(0);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-0');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['0'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeFromStartToPastEndOfFileWhenFileSizeIsZeroEmitsOk(): void
    {
        $file = new File(new ByteString('', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(0);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['0'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeWhenFileSizeIsUnknownEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/*'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringPastEndOfFileWhenFileSizeIsUnknownEmitsErroneousHeaders(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['91'], // Content length should have been 16.
            'Content-Range' => ['bytes 10-100/*'], // Content range should have been bytes 10-25/*
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromStartToPastEndOfFileWhenFileSizeIsUnknownEmitsErroneousHeaders(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['101'], // Content length should have been 26.
            'Content-Range' => ['bytes 0-100/*'], // Content range should have been bytes 0-25/*
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromPastEndOfFileWhenFileSizeIsUnknownEmitsErroneousHeaders(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=26-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['75'], // Content length should not have been included.
            'Content-Range' => ['bytes 26-100/*'], // Content range should have been bytes */0
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode()); // Status code should have been 416.
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringToEndOfFileEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['16'],
            'Content-Range' => ['bytes 10-25/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromStartToEndOfFileEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromPastEndOfFileToEndOfFileEmitsRangeNotSatisfiable(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=26-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Range' => ['bytes */26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(416, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromStartToEndOfFileWhenFileSizeIsOneEmitsOk(): void
    {
        $file = new File(new ByteString('a', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(1);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('a', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromStartToEndOfFileWhenFileSizeIsZeroEmitsOk(): void
    {
        $file = new File(new ByteString('', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(0);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['0'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringToEndOfFileWhenFileSizeIsUnknownEmitsRangeNotSatisfiable(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(416, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromStartToEndOfFileWhenFileSizeIsUnknownEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=0-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['20'],
            'Content-Range' => ['bytes 6-25/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('ghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenNumberOfBytesIsGreaterThanFileSizeEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenNumberOfBytesIsZeroEmitsRangeNotSatisfiable(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-0');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Range' => ['bytes */26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(416, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenFileSizeIsOneEmitsOk(): void
    {
        $file = new File(new ByteString('a', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(1);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-1');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('a', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenFileSizeIsOneAndNumberOfBytesIsGreaterEmitsOk(): void
    {
        $file = new File(new ByteString('a', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(1);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['1'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('a', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenFileSizeIsZeroEmitsOk(): void
    {
        $file = new File(new ByteString('', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(0);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-0');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['0'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenFileSizeIsZeroAndNumberOfBytesIsGreaterEmitsOk(): void
    {
        $file = new File(new ByteString('', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(0);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-100');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['0'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeCoveringFromEndOfFileWhenFileSizeIsUnknownEmitsRangeNotSatisfiable(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(416, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=3-3, 10-20, -5, 18-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $boundary = substr($response->getHeaderLine('Content-Type'), 31);

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => [187 + 5 * strlen($boundary)],
            'Content-Type' => ['multipart/byteranges; boundary=' . $boundary],
            'Date' => [$response->getHeaderLine('Date')],
        ];
        $expectedOutput = "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 3-3/26\r\n"
            . "\r\n"
            . "d\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 10-20/26\r\n"
            . "\r\n"
            . "klmnopqrstu\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 21-25/26\r\n"
            . "\r\n"
            . "vwxyz\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 18-25/26\r\n"
            . "\r\n"
            . "stuvwxyz\r\n"
            . '--' . $boundary . "--\r\n";

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame($expectedOutput, (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesDoesNotEmitContentRangeHeader(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=3-3, 10-20, -5, 18-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $boundary = substr($response->getHeaderLine('Content-Type'), 31);

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => [187 + 5 * strlen($boundary)],
            'Content-Type' => ['multipart/byteranges; boundary=' . $boundary],
            'Date' => [$response->getHeaderLine('Date')],
        ];
        $expectedOutput = "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 3-3/26\r\n"
            . "\r\n"
            . "d\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 10-20/26\r\n"
            . "\r\n"
            . "klmnopqrstu\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 21-25/26\r\n"
            . "\r\n"
            . "vwxyz\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 18-25/26\r\n"
            . "\r\n"
            . "stuvwxyz\r\n"
            . '--' . $boundary . "--\r\n";

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame($expectedOutput, (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesWhereNoneAreSatisfiableEmitsRangeNotSatisfiable(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=50-100, 50-, 50-50');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Range' => ['bytes */26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(416, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesWhereOneIsSatisfiableEmits206AndSingleRangeResponse(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=50-100, 10-20, 50-, 50-50');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesWhereSeveralAreSatisfiableEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=3-3, 50-100, 10-20, -5, 50-, 18-, 50-50');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $boundary = substr($response->getHeaderLine('Content-Type'), 31);

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => [187 + 5 * strlen($boundary)],
            'Content-Type' => ['multipart/byteranges; boundary=' . $boundary],
            'Date' => [$response->getHeaderLine('Date')],
        ];
        $expectedOutput = "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 3-3/26\r\n"
            . "\r\n"
            . "d\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 10-20/26\r\n"
            . "\r\n"
            . "klmnopqrstu\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 21-25/26\r\n"
            . "\r\n"
            . "vwxyz\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 18-25/26\r\n"
            . "\r\n"
            . "stuvwxyz\r\n"
            . '--' . $boundary . "--\r\n";

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame($expectedOutput, (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangeForFileWithMediaTypeEmitsContentTypeHeader(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setMediaType(new MediaType('foo', 'bar'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Content-Type' => ['foo/bar'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesForFileWithMediaTypeEmitsContentTypeHeader(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setMediaType(new MediaType('foo', 'bar'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=3-3, 10-20, -5, 18-');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $boundary = substr($response->getHeaderLine('Content-Type'), 31);

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => [279 + 5 * strlen($boundary)],
            'Content-Type' => ['multipart/byteranges; boundary=' . $boundary],
            'Date' => [$response->getHeaderLine('Date')],
        ];
        $expectedOutput = "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: foo/bar\r\n"
            . "Content-Range: bytes 3-3/26\r\n"
            . "\r\n"
            . "d\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: foo/bar\r\n"
            . "Content-Range: bytes 10-20/26\r\n"
            . "\r\n"
            . "klmnopqrstu\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: foo/bar\r\n"
            . "Content-Range: bytes 21-25/26\r\n"
            . "\r\n"
            . "vwxyz\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: foo/bar\r\n"
            . "Content-Range: bytes 18-25/26\r\n"
            . "\r\n"
            . "stuvwxyz\r\n"
            . '--' . $boundary . "--\r\n";

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame($expectedOutput, (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenRangesSpecifierIsNotSupportedEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'foo=bar');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenRangesSpecifierIsInvalidEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=foo bar');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenRequestMethodIsGetEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenRequestMethodIsNotGetEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('HEAD', '');
        $request = $request->withHeader('Range', 'bytes=10-20');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeIsInvalidEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', '2001-02-03 04:05:06');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeMatchesStrongEntityTagEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', '"foo"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeMatchesWeakEntityTagEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setEntityTag(new EntityTag('bar', /*isWeak*/true));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', 'W/"bar"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['W/"bar"'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeDoesNotMatchEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', '"foo"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeUnmodifiedSinceIsLaterThanLastModifiedDateEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setLastModifiedDate(
            new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT')), /*isWeak*/false)
        );

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeUnmodifiedSinceIsEqualToLastModifiedDateEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setLastModifiedDate(
            new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT')), /*isWeak*/false)
        );

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());
        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeUnmodifiedSinceIsEarlierThanLastModifiedDateEmitsPartialContent(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setLastModifiedDate(
            new Date(new DateTime('2002-03-04 05:06:07', new DateTimeZone('GMT')), /*isWeak*/false)
        );

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Mon, 04 Mar 2002 05:06:07 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeUnmodifiedSinceIsValidAndLastModifiedDateIsUnknownEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', 'Mon, 04 Mar 2002 05:06:07 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingRangesWhenIfRangeUnmodifiedSinceIsValidAndLastModifiedDateIsWeakEmitsOk(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setLastModifiedDate(new Date(new DateTime('2001-02-03 04:05:06', new DateTimeZone('GMT'))));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', 'Sat, 03 Feb 2001 04:05:06 GMT');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());
        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['26'],
            'Date' => [$response->getHeaderLine('Date')],
            'Last-Modified' => ['Sat, 03 Feb 2001 04:05:06 GMT'],
        ];

        self::assertSame(200, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('abcdefghijklmnopqrstuvwxyz', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingSingleRangeWhenIfRangeIsTrueDoesNotEmitContentTypeOrEncodingOrLanguageHeader(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=10-20');
        $request = $request->withHeader('If-Range', '"foo"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => ['11'],
            'Content-Range' => ['bytes 10-20/26'],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame('klmnopqrstu', (string)$response->getBody());
    }

    /**
     * @covers ::handle
     */
    public function testServingMultipleRangesWhenIfRangeIsTrueDoesNotEmitContentEncodingOrContentLanguageHeader(): void
    {
        $file = new File(new ByteString('abcdefghijklmnopqrstuvwxyz', new HttpFactory()), File::NO_INFO);
        $file->setFileSize(26);
        $file->setEntityTag(new EntityTag('foo'));

        $request = new ServerRequest('GET', '');
        $request = $request->withHeader('Range', 'bytes=3-3, 10-20, -5, 18-');
        $request = $request->withHeader('If-Range', '"foo"');

        $waiter = new Waiter($file, new HttpFactory());

        $dateMin = time();
        $response = $waiter->handle($request);
        $dateMax = time();

        $boundary = substr($response->getHeaderLine('Content-Type'), 31);

        $date = new DateTime($response->getHeaderLine('Date'));
        self::assertGreaterThanOrEqual($dateMin, $date->getTimestamp());
        self::assertLessThanOrEqual($dateMax, $date->getTimestamp());

        $expectedHeaders = [
            'Accept-Ranges' => ['bytes'],
            'Content-Length' => [187 + 5 * strlen($boundary)],
            'Content-Type' => ['multipart/byteranges; boundary=' . $boundary],
            'Date' => [$response->getHeaderLine('Date')],
            'ETag' => ['"foo"'],
        ];
        $expectedOutput = "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 3-3/26\r\n"
            . "\r\n"
            . "d\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 10-20/26\r\n"
            . "\r\n"
            . "klmnopqrstu\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 21-25/26\r\n"
            . "\r\n"
            . "vwxyz\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Range: bytes 18-25/26\r\n"
            . "\r\n"
            . "stuvwxyz\r\n"
            . '--' . $boundary . "--\r\n";

        self::assertSame(206, $response->getStatusCode());
        self::assertEquals($expectedHeaders, $response->getHeaders());
        self::assertSame($expectedOutput, (string)$response->getBody());
    }
}
