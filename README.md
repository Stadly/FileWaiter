# FileWaiter

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

File serving made easy. A PHP library for serving files from any file system over HTTP, with support for conditional and ranged requests.

## Install

Via Composer

``` bash
$ composer require stadly/file-waiter
```

## Usage

``` php
use Stadly\FileWaiter\Adapter\Local;
use Stadly\FileWaiter\File;
use Stadly\FileWaiter\Waiter;

$streamFactory = new \GuzzleHttp\Psr7\HttpFactory();                // Any PSR-17 compatible stream factory.
$file = new File(new Local('filename.txt', $streamFactory));        // Or another file adapter. See below.
$responseFactory = new \GuzzleHttp\Psr7\HttpFactory();              // Any PSR-17 compatible response factory.

$waiter = new Waiter($file, $responseFactory);

$request = \GuzzleHttp\Psr7\ServerRequest::fromGlobals();           // Any PSR-7 compatible server request.

$response = $waiter->handle($request);                              // The response is created by the response factory.

$emitter = new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter();    // Any way of emitting PSR-7 responses.
$emitter->emit($response);
die();
```

### Conditional requests

`FileWaiter` automatically handles conditional requests by looking at the the headers `If-Match`, `If-None-Match`, `If-Modified-Since`, `If-Unmodified-Since`, and `If-Range`.

``` php
$request = new \GuzzleHttp\Psr7\ServerRequest('GET', '');           // Any PSR-7 compatible server request.
$request = $request->withHeader('If-None-Match', '"foo"');

$response = $waiter->handle($request);                              // 304 Not Modified if the file's entity tag is "foo".
```

### Ranged requests

`FileWaiter` automatically handles ranged requests by looking at the header `Range`.

``` php
$request = new \GuzzleHttp\Psr7\ServerRequest('GET', '');           // Any PSR-7 compatible server request.
$request = $request->withHeader('Range', 'bytes=10-20');

$response = $waiter->handle($request);                              // Response contains only the requested bytes.
```

### File adapters

By using file adapters, `FileWaiter` can serve files from any file system. `FileWaiter` comes bundled with an adapter for serving files from the local file system. Other adapters can be installed separately. Here is a list of currently available adapters:
- `Local`: For serving files stored in the local file system.
- [`ByteString`](https://github.com/Stadly/FileWaiter-ByteString): For serving files stored in a string.
- [`Flysystem`](https://github.com/Stadly/FileWaiter-Flysystem): For serving files stored in [Flysystem](https://flysystem.thephpleague.com/v2/docs/).

Additional file adapters can easily be created by implementing `Stadly\FileWaiter\Adapter`. By using one of the available adapters for [Flysystem](https://flysystem.thephpleague.com/v2/docs/), most needs should already be covered.

Most times, information about the file, such as file name, file size, media type, last modified date, and entity tag is provided by the file adapter. There are, however, times when is not desired (for instance, if you have already stored the media type or a custom entity tag in a database) or possible (for instance, the [`ByteString`](https://github.com/Stadly/FileWaiter-ByteString) adapter cannot provide file name and last modified date). In such cases, you can overwrite the file information populated by the file adapter. You can also specify which information should be populated by the file adapter to prevent popultating unncecessary information that may be costly to retrieve.

``` php
use Stadly\FileWaiter\File;
use Stadly\FileWaiter\Adapter\Local;
use Stadly\Http\Header\Value\EntityTag\EntityTag;

$populateInfo = File::ALL_INFO ^ File::ENTITY_TAG;                  // Do not populate entity tag.
$file = new File(new Local('filename.txt', $streamFactory), $populateInfo);
$file->setEntityTag(new EntityTag('foo'));                          // Set custom entity tag.
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email magnar@myrtveit.com instead of using the issue tracker.

## Credits

- [Magnar Ovedal Myrtveit][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/stadly/file-waiter.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/Stadly/FileWaiter.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Stadly/FileWaiter.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/stadly/file-waiter.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/stadly/file-waiter
[link-scrutinizer]: https://scrutinizer-ci.com/g/Stadly/FileWaiter/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/Stadly/FileWaiter
[link-downloads]: https://packagist.org/packages/stadly/file-waiter
[link-author]: https://github.com/Stadly
[link-contributors]: https://github.com/Stadly/FileWaiter/contributors
