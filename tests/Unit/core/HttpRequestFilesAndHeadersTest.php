<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\tests\Double\core\StaticRequestHeaders;
use Error;
use ErrorException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Characterization of the static HttpRequest before the redesign (docs/http-request/plan.md, step 1).
 *
 * Cookies and files are read directly from the superglobals (no cache). The bearer token needs getallheaders(), which
 * does not exist in the CLI: tests that use it load a stand-in function into their own process.
 */
final class HttpRequestFilesAndHeadersTest extends TestCase
{
    /** @var array<mixed> */
    private array $cookieBackup = [];
    /** @var array<mixed> */
    private array $filesBackup = [];

    #[Override]
    protected function setUp(): void
    {
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
        $_COOKIE = [];
        $_FILES = [];
        // Warnings (e.g. array access on null) become exceptions, so the tests can name them
        set_error_handler(
            callback: static function (int $severity, string $message, string $file, int $line): never {
                throw new ErrorException(message: $message, code: 0, severity: $severity, filename: $file, line: $line);
            },
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        restore_error_handler();
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
        StaticRequestHeaders::$headers = [];
    }

    public function testCookiesAreTheCookieSuperglobal(): void
    {
        $_COOKIE = ['session' => 'abc', 'theme' => 'dark'];

        $this->assertSame(['session' => 'abc', 'theme' => 'dark'], HttpRequest::getCookies());
    }

    public function testNoCookiesIsEmptyArray(): void
    {
        $this->assertSame([], HttpRequest::getCookies());
    }

    public function testFileOfSingleUpload(): void
    {
        $file = [
            'name' => 'a.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/phpA1',
            'error' => 0,
            'size' => 12,
        ];
        $_FILES['upload'] = $file;

        $this->assertSame($file, HttpRequest::getFile(name: 'upload'));
    }

    public function testFileOfMultiUploadIsReturnedUnnormalized(): void
    {
        $raw = [
            'name' => ['a.txt', 'b.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => ['/tmp/phpA1', '/tmp/phpB2'],
            'error' => [0, 0],
            'size' => [12, 34],
        ];
        $_FILES['upload'] = $raw;

        $this->assertSame($raw, HttpRequest::getFile(name: 'upload'));
    }

    public function testMissingFileIsNull(): void
    {
        $this->assertNull(HttpRequest::getFile(name: 'upload'));
    }

    public function testFilesOfMultiUploadAreNormalizedPerFile(): void
    {
        $_FILES['upload'] = [
            'name' => ['a.txt', 'b.png'],
            'type' => ['text/plain', 'image/png'],
            'tmp_name' => ['/tmp/phpA1', '/tmp/phpB2'],
            'error' => [0, 4],
            'size' => [12, 0],
        ];

        $this->assertSame(
            [
                ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/phpA1', 'error' => 0, 'size' => 12],
                ['name' => 'b.png', 'type' => 'image/png', 'tmp_name' => '/tmp/phpB2', 'error' => 4, 'size' => 0],
            ],
            HttpRequest::getFiles(name: 'upload'),
        );
    }

    public function testFilesOfEmptyMultiUploadIsEmptyList(): void
    {
        $_FILES['upload'] = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];

        $this->assertSame([], HttpRequest::getFiles(name: 'upload'));
    }

    public function testFilesOfSingleUploadThrowsTypeError(): void
    {
        $_FILES['upload'] = ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/phpA1', 'error' => 0, 'size' => 12];
        $this->expectException(TypeError::class);
        $this->expectExceptionMessageIs('count(): Argument #1 ($value) must be of type Countable|array, string given');

        HttpRequest::getFiles(name: 'upload');
    }

    public function testFilesOfMissingFieldFailsWithArrayAccessOnNull(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageIs('Trying to access array offset on null');

        HttpRequest::getFiles(name: 'upload');
    }

    /**
     * @return iterable<string, array{array<string, string>, false|string}>
     */
    public static function bearerProvider(): iterable
    {
        yield 'bearer token' => [['Authorization' => 'Bearer abc.def-123'], 'abc.def-123'];
        yield 'token is trimmed' => [['Authorization' => 'Bearer   abc  '], 'abc'];
        yield 'only the scheme with a space' => [['Authorization' => 'Bearer '], ''];
        yield 'scheme without token and space' => [['Authorization' => 'Bearer'], false];
        yield 'other scheme' => [['Authorization' => 'Basic dXNlcjpwYXNz'], false];
        yield 'scheme is case sensitive' => [['Authorization' => 'bearer abc'], false];
        yield 'other header only' => [['X-Other' => 'Bearer abc'], false];
        yield 'header name is case sensitive' => [['authorization' => 'Bearer abc'], false];
        yield 'no headers' => [[], false];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('bearerProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBearer(array $headers, false|string $expected): void
    {
        require_once __DIR__ . '/../../Fixture/core/getallheaders.php';
        StaticRequestHeaders::$headers = $headers;

        $this->assertSame($expected, HttpRequest::getBearer());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBearerWithoutGetallheadersFunctionIsAnError(): void
    {
        // Without Apache/FPM the function does not exist (CLI, cron scripts)
        $this->expectException(Error::class);
        $this->expectExceptionMessageIs('Call to undefined function actra\\yuf\\core\\getallheaders()');

        HttpRequest::getBearer();
    }
}
