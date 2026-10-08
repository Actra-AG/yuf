<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use ErrorException;
use Exception;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Characterization of the static HttpRequest before the redesign (docs/http-request/plan.md, step 1).
 *
 * HttpRequest caches host and protocol (and the languages, see HttpRequestClientTest) statically for the whole process.
 * Every test therefore runs in its own process.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HttpRequestServerTest extends TestCase
{
    /** @var array<mixed> */
    private array $serverBackup = [];

    #[Override]
    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $_SERVER = [];
        // Warnings (e.g. undefined array keys) become exceptions, so the tests can name them
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
        $_SERVER = $this->serverBackup;
    }

    /**
     * @return iterable<string, array{array<string, string|int>, string, bool}>
     */
    public static function protocolProvider(): iterable
    {
        yield 'HTTPS on' => [['HTTPS' => 'on', 'SERVER_PORT' => '80'], 'https', true];
        yield 'HTTPS 1' => [['HTTPS' => '1', 'SERVER_PORT' => '80'], 'https', true];
        yield 'HTTPS off, port 80' => [['HTTPS' => 'off', 'SERVER_PORT' => '80'], 'http', false];
        yield 'HTTPS off, port 443' => [['HTTPS' => 'off', 'SERVER_PORT' => '443'], 'https', true];
        yield 'HTTPS empty, port 80' => [['HTTPS' => '', 'SERVER_PORT' => '80'], 'http', false];
        yield 'HTTPS 0, port 80' => [['HTTPS' => '0', 'SERVER_PORT' => '80'], 'http', false];
        yield 'HTTPS ON uppercase, port 80' => [['HTTPS' => 'ON', 'SERVER_PORT' => '80'], 'http', false];
        yield 'no HTTPS, port 443' => [['SERVER_PORT' => '443'], 'https', true];
        yield 'no HTTPS, port 80' => [['SERVER_PORT' => '80'], 'http', false];
        yield 'no HTTPS, port 8443' => [['SERVER_PORT' => '8443'], 'http', false];
    }

    /**
     * @param array<string, string|int> $server
     */
    #[DataProvider('protocolProvider')]
    public function testProtocolIsDetected(array $server, string $expectedProtocol, bool $expectedSsl): void
    {
        $_SERVER = $server;

        $this->assertSame($expectedProtocol, HttpRequest::getProtocol());
        $this->assertSame($expectedSsl, HttpRequest::isSsl());
    }

    public function testProtocolIsCachedForTheWholeProcess(): void
    {
        $_SERVER['SERVER_PORT'] = '80';
        $this->assertSame('http', HttpRequest::getProtocol());

        $_SERVER['HTTPS'] = 'on';

        $this->assertSame('http', HttpRequest::getProtocol());
    }

    public function testHostIsTakenFromHttpHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'www.example.com:8080';
        $_SERVER['SERVER_NAME'] = 'internal.example.com';

        $this->assertSame('www.example.com:8080', HttpRequest::getHost());
    }

    public function testHostFallsBackToServerName(): void
    {
        $_SERVER['SERVER_NAME'] = 'internal.example.com';

        $this->assertSame('internal.example.com', HttpRequest::getHost());
    }

    public function testMissingHostThrowsGenericException(): void
    {
        // The class is not asserted exactly: expectException() also accepts subclasses
        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs('HTTP_HOST and SERVER_NAME are not defined');

        HttpRequest::getHost();
    }

    public function testHostIsCachedForTheWholeProcess(): void
    {
        $_SERVER['HTTP_HOST'] = 'first.example.com';
        $this->assertSame('first.example.com', HttpRequest::getHost());

        $_SERVER['HTTP_HOST'] = 'second.example.com';

        $this->assertSame('first.example.com', HttpRequest::getHost());
    }

    public function testPortIsCastToInteger(): void
    {
        $_SERVER['SERVER_PORT'] = '8080';

        $this->assertSame(8080, HttpRequest::getPort());
    }

    public function testPortOfIntegerServerValue(): void
    {
        $_SERVER['SERVER_PORT'] = 443;

        $this->assertSame(443, HttpRequest::getPort());
    }

    public function testUriContainsPathAndQuery(): void
    {
        $_SERVER['REQUEST_URI'] = '/de/page?a=1&b=2';

        $this->assertSame('/de/page?a=1&b=2', HttpRequest::getUri());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'without query' => ['/de/page', '/de/page'];
        yield 'with query' => ['/de/page?a=1&b=2', '/de/page'];
        yield 'root' => ['/', '/'];
        yield 'root with query' => ['/?a=1', '/'];
        yield 'empty query' => ['/de/page?', '/de/page'];
        yield 'question mark inside query' => ['/de/page?a=what?&b=2', '/de/page'];
        yield 'encoded question mark in path' => ['/de/what%3Fpage?a=1', '/de/what%3Fpage'];
        yield 'only query' => ['?a=1', ''];
        yield 'empty uri' => ['', ''];
        yield 'umlaut before query' => ['/de/zürich?a=1', '/de/zürich'];
    }

    #[DataProvider('pathProvider')]
    public function testPathEndsBeforeTheFirstQuestionMark(string $requestUri, string $expectedPath): void
    {
        $_SERVER['REQUEST_URI'] = $requestUri;

        $this->assertSame($expectedPath, HttpRequest::getPath());
    }

    public function testMissingRequestUriIsAnUndefinedArrayKeyError(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageIs('Undefined array key "REQUEST_URI"');

        HttpRequest::getUri();
    }

    public function testQueryIsTheRawQueryString(): void
    {
        $_SERVER['QUERY_STRING'] = 'a=1&b=%C3%BC';

        $this->assertSame('a=1&b=%C3%BC', HttpRequest::getQuery());
    }

    public function testQueryCanBeEmpty(): void
    {
        $_SERVER['QUERY_STRING'] = '';

        $this->assertSame('', HttpRequest::getQuery());
    }

    public function testMissingQueryStringIsAnUndefinedArrayKeyError(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageIs('Undefined array key "QUERY_STRING"');

        HttpRequest::getQuery();
    }

    public function testUrlUsesDetectedProtocol(): void
    {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        $_SERVER['REQUEST_URI'] = '/de/page?a=1';

        $this->assertSame('https://www.example.com/de/page?a=1', HttpRequest::getUrl());
    }

    public function testUrlWithExplicitProtocol(): void
    {
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        $_SERVER['REQUEST_URI'] = '/de/page';

        $this->assertSame('https://www.example.com/de/page', HttpRequest::getUrl(protocol: HttpRequest::PROTOCOL_HTTPS));
    }

    public function testUrlAcceptsAnyProtocolString(): void
    {
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        $_SERVER['REQUEST_URI'] = '/';

        $this->assertSame('ftp://www.example.com/', HttpRequest::getUrl(protocol: 'ftp'));
    }

    public function testUrlWithExplicitProtocolDoesNotChangeTheDetectedProtocol(): void
    {
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST'] = 'www.example.com';
        $_SERVER['REQUEST_URI'] = '/';
        HttpRequest::getUrl(protocol: HttpRequest::PROTOCOL_HTTPS);

        $this->assertSame('http', HttpRequest::getProtocol());
    }

    /**
     * @return iterable<string, array{string, RequestMethodEnum}>
     */
    public static function requestMethodProvider(): iterable
    {
        yield 'GET' => ['GET', RequestMethodEnum::GET];
        yield 'POST' => ['POST', RequestMethodEnum::POST];
        yield 'PUT' => ['PUT', RequestMethodEnum::PUT];
        yield 'PATCH' => ['PATCH', RequestMethodEnum::PATCH];
        yield 'DELETE' => ['DELETE', RequestMethodEnum::DELETE];
    }

    #[DataProvider('requestMethodProvider')]
    public function testRequestMethod(string $method, RequestMethodEnum $expected): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;

        $this->assertSame($expected, HttpRequest::getRequestMethod());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRequestMethodProvider(): iterable
    {
        yield 'HEAD' => ['HEAD'];
        yield 'OPTIONS' => ['OPTIONS'];
        yield 'lowercase get' => ['get'];
        yield 'empty' => [''];
    }

    #[DataProvider('invalidRequestMethodProvider')]
    public function testInvalidRequestMethodThrowsValueError(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIs('"' . $method . '" is not a valid backing value for enum ' . RequestMethodEnum::class);

        HttpRequest::getRequestMethod();
    }
}
