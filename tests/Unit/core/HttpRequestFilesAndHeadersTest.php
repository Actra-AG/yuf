<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Uploaded files, headers, the bearer token, cookies and the body of a request built with the constructor.
 */
final class HttpRequestFilesAndHeadersTest extends TestCase
{
    public function testCookie(): void
    {
        $httpRequest = HttpRequestFactory::create(cookies: ['session' => 'abc', 'theme' => 'dark']);

        $this->assertSame('abc', $httpRequest->getCookie(name: 'session'));
        $this->assertSame('dark', $httpRequest->getCookie(name: 'theme'));
        $this->assertNull($httpRequest->getCookie(name: 'missing'));
        $this->assertSame(['session' => 'abc', 'theme' => 'dark'], $httpRequest->listCookies());
    }

    public function testNoCookies(): void
    {
        $this->assertNull(HttpRequestFactory::create()->getCookie(name: 'session'));
    }

    public function testBody(): void
    {
        $this->assertSame('{"a":1}', HttpRequestFactory::create(body: '{"a":1}')->getBody());
        $this->assertSame('', HttpRequestFactory::create()->getBody());
    }

    public function testHeaderNamesAreCaseInsensitive(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['X-Request-Id' => 'abc', 'accept' => 'text/html']);

        $this->assertSame('abc', $httpRequest->getHeader(name: 'x-request-id'));
        $this->assertSame('abc', $httpRequest->getHeader(name: 'X-REQUEST-ID'));
        $this->assertSame('text/html', $httpRequest->getHeader(name: 'Accept'));
        $this->assertNull($httpRequest->getHeader(name: 'X-Other'));
    }

    /**
     * @return iterable<string, array{array<string, string>, string|null}>
     */
    public static function bearerProvider(): iterable
    {
        yield 'bearer token' => [['Authorization' => 'Bearer abc.def-123'], 'abc.def-123'];
        yield 'token is trimmed' => [['Authorization' => 'Bearer   abc  '], 'abc'];
        yield 'only the scheme with a space' => [['Authorization' => 'Bearer '], null];
        yield 'only the scheme with spaces' => [['Authorization' => 'Bearer    '], null];
        yield 'scheme without token and space' => [['Authorization' => 'Bearer'], null];
        yield 'other scheme' => [['Authorization' => 'Basic dXNlcjpwYXNz'], null];
        yield 'scheme is case-insensitive' => [['Authorization' => 'bearer abc'], 'abc'];
        yield 'scheme in capitals' => [['Authorization' => 'BEARER abc'], 'abc'];
        yield 'scheme as a prefix of another one' => [['Authorization' => 'Bearerx abc'], null];
        yield 'other header only' => [['X-Other' => 'Bearer abc'], null];
        yield 'header name is case-insensitive' => [['authorization' => 'Bearer abc'], 'abc'];
        yield 'token with spaces inside' => [['Authorization' => 'Bearer a b'], 'a b'];
        yield 'no headers' => [[], null];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('bearerProvider')]
    public function testBearerToken(array $headers, ?string $expected): void
    {
        $this->assertSame($expected, HttpRequestFactory::create(headers: $headers)->getBearerToken());
    }

    private const array SINGLE_FILE = [
        'name' => 'a.txt',
        'type' => 'text/plain',
        'tmp_name' => '/tmp/phpA1',
        'error' => 0,
        'size' => 12,
    ];

    private const array MULTI_FILE = [
        'name' => ['a.txt', 'b.png'],
        'type' => ['text/plain', 'image/png'],
        'tmp_name' => ['/tmp/phpA1', '/tmp/phpB2'],
        'error' => [0, 4],
        'size' => [12, 0],
    ];

    private function createWithUpload(mixed $entry): HttpRequest
    {
        return HttpRequestFactory::create(uploadedFiles: ['upload' => $entry]);
    }

    public function testFileOfSingleUpload(): void
    {
        $httpRequest = $this->createWithUpload(entry: HttpRequestFilesAndHeadersTest::SINGLE_FILE);

        $this->assertSame(HttpRequestFilesAndHeadersTest::SINGLE_FILE, $httpRequest->getFile(name: 'upload'));
    }

    public function testFileOfMultiUploadIsNull(): void
    {
        $httpRequest = $this->createWithUpload(entry: HttpRequestFilesAndHeadersTest::MULTI_FILE);

        $this->assertNull($httpRequest->getFile(name: 'upload'));
    }

    public function testMissingFileIsNull(): void
    {
        $this->assertNull(HttpRequestFactory::create()->getFile(name: 'upload'));
    }

    public function testFilesOfSingleUploadIsAListWithOneFile(): void
    {
        $httpRequest = $this->createWithUpload(entry: HttpRequestFilesAndHeadersTest::SINGLE_FILE);

        $this->assertSame([HttpRequestFilesAndHeadersTest::SINGLE_FILE], $httpRequest->getFiles(name: 'upload'));
    }

    public function testFilesOfMultiUploadAreNormalizedPerFile(): void
    {
        $httpRequest = $this->createWithUpload(entry: HttpRequestFilesAndHeadersTest::MULTI_FILE);

        $this->assertSame(
            [
                ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/phpA1', 'error' => 0, 'size' => 12],
                ['name' => 'b.png', 'type' => 'image/png', 'tmp_name' => '/tmp/phpB2', 'error' => 4, 'size' => 0],
            ],
            $httpRequest->getFiles(name: 'upload'),
        );
    }

    public function testFilesOfEmptyMultiUploadIsEmptyList(): void
    {
        $httpRequest = HttpRequestFactory::create(
            uploadedFiles: ['upload' => ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []]],
        );

        $this->assertSame([], $httpRequest->getFiles(name: 'upload'));
    }

    public function testFilesOfMissingFieldIsEmptyList(): void
    {
        $this->assertSame([], HttpRequestFactory::create()->getFiles(name: 'upload'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function malformedFilesProvider(): iterable
    {
        yield 'not an array' => ['a.txt'];
        yield 'empty array' => [[]];
        yield 'single without size' => [['name' => 'a', 'type' => 't', 'tmp_name' => '/tmp/a', 'error' => 0]];
        yield 'single with array name and string type' => [
            ['name' => ['a'], 'type' => 't', 'tmp_name' => ['/tmp/a'], 'error' => [0], 'size' => [1]],
        ];
        yield 'single with string error' => [
            ['name' => 'a', 'type' => 't', 'tmp_name' => '/tmp/a', 'error' => '0', 'size' => 1],
        ];
        yield 'multi with a missing entry' => [
            [
                'name' => ['a', 'b'],
                'type' => ['t'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'error' => [0, 0],
                'size' => [1, 2],
            ],
        ];
        yield 'nested names' => [
            ['name' => [['a']], 'type' => [['t']], 'tmp_name' => [['/tmp/a']], 'error' => [[0]], 'size' => [[1]]],
        ];
    }

    #[DataProvider('malformedFilesProvider')]
    public function testMalformedUploadIsNoFile(mixed $entry): void
    {
        $httpRequest = HttpRequestFactory::create(uploadedFiles: ['upload' => $entry]);

        $this->assertSame([], $httpRequest->getFiles(name: 'upload'));
        $this->assertNull($httpRequest->getFile(name: 'upload'));
    }
}
