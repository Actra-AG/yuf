<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\clock\FixedClock;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use DateTimeImmutable;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: `sendAndExit()` and the 404 / 403 answers for a missing or unreadable file (they send headers and
 * exit), `redirectAndExit()`.
 */
final class HttpResponseTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private FixedClock $clock;
    private string $file;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new FixedClock(now: new DateTimeImmutable(datetime: '@' . HttpResponseTest::NOW));
        $this->file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-response-'
            . bin2hex(string: random_bytes(length: 8)) . '.csv';
        file_put_contents(filename: $this->file, data: "a;b\n");
        touch(filename: $this->file, mtime: 1_700_000_000);
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink(filename: $this->file);
    }

    public function testHtmlResponseHasContentTypeValidatorsAndSecurityHeaders(): void
    {
        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: null,
            nonce: null,
            httpRequest: HttpRequestFactory::create(),
            clock: $this->clock,
        );

        $headers = $httpResponse->listHeaders();
        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_FOUND, $httpResponse->httpStatusCode);
        $this->assertSame('text/html; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertSame(hash(algo: 'sha256', data: '<p>Test</p>'), $httpResponse->getHeader(key: 'Etag'));
        $this->assertSame(gmdate(format: 'r', timestamp: HttpResponseTest::NOW), $httpResponse->getHeader(key: 'Last-Modified'));
        $this->assertSame('private, must-revalidate', $httpResponse->getHeader(key: 'Cache-Control'));
        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);
        $this->assertArrayNotHasKey('Connection', $headers);
    }

    public function testHtmlResponseWithPolicyButWithoutNonceThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('A Content-Security-Policy needs the nonce of the request.');

        HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: new CspPolicySettings(),
            nonce: null,
            httpRequest: HttpRequestFactory::create(),
        );
    }

    public function testHtmlContentTypeIsRejectedByTheStringFactory(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Use HttpResponse::createHtmlResponse() instead');

        HttpResponse::createResponseFromString(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            contentString: '<p>Test</p>',
            contentType: ContentType::createHtml(),
            httpRequest: HttpRequestFactory::create(),
        );
    }

    public function testStringResponseHasTheContentType(): void
    {
        $httpResponse = HttpResponse::createResponseFromString(
            httpStatusCode: HttpStatusCodeEnum::HTTP_BAD_REQUEST,
            contentString: '{"a":1}',
            contentType: ContentType::createJson(),
            httpRequest: HttpRequestFactory::create(),
            clock: $this->clock,
        );

        $headers = $httpResponse->listHeaders();
        $this->assertSame(HttpStatusCodeEnum::HTTP_BAD_REQUEST, $httpResponse->httpStatusCode);
        $this->assertSame('application/json; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertArrayNotHasKey('Content-Language', $headers);
        $this->assertSame('max-age=31536000', $httpResponse->getHeader(key: 'Strict-Transport-Security'));
    }

    public function testMatchingETagIsAnsweredWithNotModifiedWithoutContentHeaders(): void
    {
        $eTag = hash(algo: 'sha256', data: '<p>Test</p>');

        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: null,
            nonce: null,
            httpRequest: HttpRequestFactory::create(headers: ['If-None-Match' => $eTag]),
        );

        $headers = $httpResponse->listHeaders();
        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_MODIFIED, $httpResponse->httpStatusCode);
        $this->assertSame('Close', $httpResponse->getHeader(key: 'Connection'));
        $this->assertSame($eTag, $httpResponse->getHeader(key: 'Etag'));
        $this->assertArrayNotHasKey('Content-Type', $headers);
    }

    public function testFileResponseSendsTheFileAsDownloadByDefaultForCsv(): void
    {
        $httpResponse = HttpResponse::createResponseFromFilePath(
            absolutePathToFile: $this->file,
            forceDownload: null,
            individualFileName: 'export.csv',
            maxAge: 3600,
            httpRequest: HttpRequestFactory::create(),
            clock: $this->clock,
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
        $this->assertSame('text/csv; charset=utf-8', $httpResponse->getHeader(key: 'Content-Type'));
        $this->assertSame('attachment; filename="export.csv"', $httpResponse->getHeader(key: 'Content-Disposition'));
        $this->assertSame('File Transfer', $httpResponse->getHeader(key: 'Content-Description'));
        $this->assertSame('4', $httpResponse->getHeader(key: 'Content-Length'));
        $this->assertSame(gmdate(format: 'r', timestamp: 1_700_000_000), $httpResponse->getHeader(key: 'Last-Modified'));
        $this->assertSame(gmdate(format: 'r', timestamp: HttpResponseTest::NOW + 3600), $httpResponse->getHeader(key: 'Expires'));
    }

    public function testFileResponseWithoutDownloadHasNoDisposition(): void
    {
        $httpResponse = HttpResponse::createResponseFromFilePath(
            absolutePathToFile: $this->file,
            forceDownload: false,
            individualFileName: null,
            maxAge: 0,
            httpRequest: HttpRequestFactory::create(),
            clock: $this->clock,
        );

        $headers = $httpResponse->listHeaders();
        $this->assertArrayNotHasKey('Content-Disposition', $headers);
        $this->assertSame(gmdate(format: 'r', timestamp: HttpResponseTest::NOW), $httpResponse->getHeader(key: 'Expires'));
    }

    public function testFileResponseIsNotModifiedForTheSameModificationTime(): void
    {
        $httpResponse = HttpResponse::createResponseFromFilePath(
            absolutePathToFile: $this->file,
            forceDownload: false,
            individualFileName: null,
            maxAge: 0,
            httpRequest: HttpRequestFactory::create(
                headers: ['If-Modified-Since' => gmdate(format: 'r', timestamp: 1_700_000_000)],
            ),
        );

        $this->assertSame(HttpStatusCodeEnum::HTTP_NOT_MODIFIED, $httpResponse->httpStatusCode);
    }

    public function testHeadersCanBeSetAndRemoved(): void
    {
        $httpResponse = HttpResponse::createResponseFromString(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            contentString: 'x',
            contentType: ContentType::createTxt(),
            httpRequest: HttpRequestFactory::create(),
        );

        $httpResponse->setHeader(key: 'X-Own', val: 'one');

        $this->assertSame('one', $httpResponse->getHeader(key: 'X-Own'));
        $this->assertTrue($httpResponse->removeHeader(key: 'X-Own'));
        $this->assertFalse($httpResponse->removeHeader(key: 'X-Own'));
        $this->assertArrayNotHasKey('X-Own', $httpResponse->listHeaders());
    }
}
