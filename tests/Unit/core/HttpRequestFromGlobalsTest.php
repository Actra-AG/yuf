<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\UnsupportedRequestMethodException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * `HttpRequest::fromGlobals()` with prepared superglobals, restored in `tearDown()`. The request is a snapshot, so no
 * cache has to be reset between the tests. Not covered: the request body (`php://input` cannot be fed in a test, it
 * is empty in the CLI) and `getallheaders()` with a value (see HttpRequestGetallheadersTest).
 */
final class HttpRequestFromGlobalsTest extends TestCase
{
    /** @var array<mixed> */
    private array $serverBackup = [];
    /** @var array<mixed> */
    private array $getBackup = [];
    /** @var array<mixed> */
    private array $postBackup = [];
    /** @var array<mixed> */
    private array $cookieBackup = [];
    /** @var array<mixed> */
    private array $filesBackup = [];

    #[Override]
    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/de/page?a=1',
            'HTTP_HOST' => 'www.example.com',
            'SERVER_PORT' => '443',
        ];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
    }

    public function testRequestLine(): void
    {
        $_SERVER['QUERY_STRING'] = 'a=1';

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame(RequestMethodEnum::GET, $httpRequest->getMethod());
        $this->assertSame('/de/page?a=1', $httpRequest->getUri());
        $this->assertSame('/de/page', $httpRequest->getPath());
        $this->assertSame('a=1', $httpRequest->getQuery());
        $this->assertSame('https://www.example.com/de/page?a=1', $httpRequest->getUrl());
    }

    /**
     * @return iterable<string, array{array<string, string>, ProtocolEnum}>
     */
    public static function protocolProvider(): iterable
    {
        yield 'HTTPS on' => [['HTTPS' => 'on', 'SERVER_PORT' => '80'], ProtocolEnum::HTTPS];
        yield 'HTTPS 1' => [['HTTPS' => '1', 'SERVER_PORT' => '80'], ProtocolEnum::HTTPS];
        yield 'HTTPS ON uppercase' => [['HTTPS' => 'ON', 'SERVER_PORT' => '80'], ProtocolEnum::HTTPS];
        yield 'HTTPS off, port 80' => [['HTTPS' => 'off', 'SERVER_PORT' => '80'], ProtocolEnum::HTTP];
        yield 'HTTPS off decides, even on port 443' => [['HTTPS' => 'off', 'SERVER_PORT' => '443'], ProtocolEnum::HTTP];
        yield 'HTTPS empty' => [['HTTPS' => '', 'SERVER_PORT' => '80'], ProtocolEnum::HTTP];
        yield 'HTTPS 0' => [['HTTPS' => '0', 'SERVER_PORT' => '443'], ProtocolEnum::HTTP];
        yield 'no HTTPS, port 443' => [['SERVER_PORT' => '443'], ProtocolEnum::HTTPS];
        yield 'no HTTPS, port 80' => [['SERVER_PORT' => '80'], ProtocolEnum::HTTP];
        yield 'no HTTPS, port 8443' => [['SERVER_PORT' => '8443'], ProtocolEnum::HTTP];
        yield 'no HTTPS, no port' => [[], ProtocolEnum::HTTP];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('protocolProvider')]
    public function testProtocolIsDetected(array $server, ProtocolEnum $expectedProtocol): void
    {
        unset($_SERVER['SERVER_PORT']);
        $_SERVER = [...$_SERVER, ...$server];

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame($expectedProtocol, $httpRequest->getProtocol());
        $this->assertSame($expectedProtocol === ProtocolEnum::HTTPS, $httpRequest->isSsl());
    }

    public function testEachRequestIsASnapshot(): void
    {
        $_SERVER['SERVER_PORT'] = '80';
        $first = HttpRequest::fromGlobals();

        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'other.example.com';
        $second = HttpRequest::fromGlobals();

        $this->assertSame(ProtocolEnum::HTTP, $first->getProtocol());
        $this->assertSame('www.example.com', $first->getHost());
        $this->assertSame(ProtocolEnum::HTTPS, $second->getProtocol());
        $this->assertSame('other.example.com', $second->getHost());
    }

    public function testHostIsTakenFromHttpHostWithItsPort(): void
    {
        $_SERVER['HTTP_HOST'] = 'www.example.com:8080';
        $_SERVER['SERVER_NAME'] = 'internal.example.com';

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame('www.example.com:8080', $httpRequest->getHost());
        $this->assertSame('internal.example.com', $httpRequest->getServerName());
    }

    public function testHostFallsBackToServerName(): void
    {
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['SERVER_NAME'] = 'internal.example.com';

        $this->assertSame('internal.example.com', HttpRequest::fromGlobals()->getHost());
    }

    public function testEmptyHttpHostFallsBackToServerName(): void
    {
        $_SERVER['HTTP_HOST'] = '';
        $_SERVER['SERVER_NAME'] = 'internal.example.com';

        $this->assertSame('internal.example.com', HttpRequest::fromGlobals()->getHost());
    }

    public function testMissingHostThrows(): void
    {
        unset($_SERVER['HTTP_HOST']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('HTTP_HOST and SERVER_NAME are not defined: this is no web request.');

        HttpRequest::fromGlobals();
    }

    public function testMissingRequestUriThrows(): void
    {
        unset($_SERVER['REQUEST_URI']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The server variable REQUEST_URI is not defined: this is no web request.');

        HttpRequest::fromGlobals();
    }

    public function testMissingRequestMethodThrows(): void
    {
        unset($_SERVER['REQUEST_METHOD']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIs('The server variable REQUEST_METHOD is not defined: this is no web request.');

        HttpRequest::fromGlobals();
    }

    public function testMissingQueryStringIsEmpty(): void
    {
        $this->assertSame('', HttpRequest::fromGlobals()->getQuery());
    }

    public function testPort(): void
    {
        $_SERVER['SERVER_PORT'] = '8080';

        $this->assertSame(8080, HttpRequest::fromGlobals()->getPort());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function invalidPortProvider(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'not a number' => ['abc'];
        yield 'negative' => ['-1'];
        yield 'too long' => ['1234567'];
    }

    #[DataProvider('invalidPortProvider')]
    public function testMissingOrInvalidPortIsZero(?string $port): void
    {
        unset($_SERVER['SERVER_PORT']);
        if ($port !== null) {
            $_SERVER['SERVER_PORT'] = $port;
        }

        $this->assertSame(0, HttpRequest::fromGlobals()->getPort());
    }

    /**
     * @return iterable<string, array{string, RequestMethodEnum}>
     */
    public static function requestMethodProvider(): iterable
    {
        foreach (RequestMethodEnum::cases() as $method) {
            yield $method->value => [$method->value, $method];
        }
    }

    #[DataProvider('requestMethodProvider')]
    public function testRequestMethod(string $method, RequestMethodEnum $expected): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;

        $this->assertSame($expected, HttpRequest::fromGlobals()->getMethod());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedRequestMethodProvider(): iterable
    {
        yield 'TRACE' => ['TRACE'];
        yield 'CONNECT' => ['CONNECT'];
        yield 'lowercase get' => ['get'];
        yield 'empty' => [''];
    }

    #[DataProvider('unsupportedRequestMethodProvider')]
    public function testUnsupportedRequestMethodThrowsASpecificException(string $method): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;

        $this->expectException(UnsupportedRequestMethodException::class);
        $this->expectExceptionMessageIs('Unsupported request method: ' . $method);

        HttpRequest::fromGlobals();
    }

    public function testClientData(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        $_SERVER['SERVER_ADDR'] = '192.0.2.10';
        $_SERVER['HTTP_USER_AGENT'] = 'ExampleBrowser/1.0';
        $_SERVER['HTTP_REFERER'] = 'https://www.example.com/previous';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-CH,de;q=0.9,en;q=0.8';

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame('192.0.2.1', $httpRequest->getRemoteAddress());
        $this->assertSame('192.0.2.10', $httpRequest->getServerAddress());
        $this->assertSame('ExampleBrowser/1.0', $httpRequest->getUserAgent());
        $this->assertSame('https://www.example.com/previous', $httpRequest->getReferrer());
        $this->assertSame(['de', 'en'], $httpRequest->listBrowserLanguagesByQuality());
    }

    public function testMissingClientDataIsEmpty(): void
    {
        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame('', $httpRequest->getRemoteAddress());
        $this->assertSame('', $httpRequest->getServerAddress());
        $this->assertSame('', $httpRequest->getServerName());
        $this->assertSame('', $httpRequest->getUserAgent());
        $this->assertSame('', $httpRequest->getReferrer());
        $this->assertSame([], $httpRequest->listBrowserLanguagesByQuality());
    }

    public function testHeadersComeFromTheServerVariables(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'abc';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"tag"';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['CONTENT_LENGTH'] = '12';

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame('abc', $httpRequest->getHeader(name: 'X-Request-Id'));
        $this->assertSame('"tag"', $httpRequest->getHeader(name: 'If-None-Match'));
        $this->assertSame('application/json', $httpRequest->getHeader(name: 'Content-Type'));
        $this->assertSame('12', $httpRequest->getHeader(name: 'Content-Length'));
        $this->assertSame('www.example.com', $httpRequest->getHeader(name: 'Host'));
        $this->assertNull($httpRequest->getHeader(name: 'X-Other'));
    }

    public function testBearerTokenWithoutGetallheaders(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc';

        $this->assertSame('abc', HttpRequest::fromGlobals()->getBearerToken());
    }

    public function testBearerTokenOfARedirectedAuthorizationHeader(): void
    {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirected';

        $this->assertSame('redirected', HttpRequest::fromGlobals()->getBearerToken());
    }

    public function testAuthorizationHeaderWinsOverTheRedirectedOne(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer direct';
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirected';

        $this->assertSame('direct', HttpRequest::fromGlobals()->getBearerToken());
    }

    public function testNoBearerTokenWithoutHeader(): void
    {
        $this->assertNull(HttpRequest::fromGlobals()->getBearerToken());
    }

    public function testInputCookiesAndFiles(): void
    {
        $_GET = ['a' => '1', 'list' => ['x']];
        $_POST = ['b' => ' 2 '];
        $_COOKIE = ['session' => 'abc', 'array' => ['ignored']];
        $_FILES = [
            'f' => ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/phpA1', 'error' => 0, 'size' => 1],
        ];

        $httpRequest = HttpRequest::fromGlobals();

        $this->assertSame(1, $httpRequest->getQueryInteger(name: 'a'));
        $this->assertSame(['x'], $httpRequest->getQueryArray(name: 'list'));
        $this->assertSame('2', $httpRequest->getPostString(name: 'b'));
        $this->assertSame('abc', $httpRequest->getCookie(name: 'session'));
        $this->assertNull($httpRequest->getCookie(name: 'array'));
        $file = $httpRequest->getFile(name: 'f');
        $this->assertNotNull($file);
        $this->assertSame('a.txt', $file['name']);
    }

    public function testRequestIsNotAffectedByLaterChangesOfTheSuperglobals(): void
    {
        $_GET = ['a' => 'first'];
        $httpRequest = HttpRequest::fromGlobals();

        $_GET['a'] = 'second';
        $_GET['b'] = 'new';

        $this->assertSame('first', $httpRequest->getQueryString(name: 'a'));
        $this->assertNull($httpRequest->getQueryString(name: 'b'));
    }

    public function testBodyOfACliRequestIsEmpty(): void
    {
        $this->assertSame('', HttpRequest::fromGlobals()->getBody());
    }

    public function testServerVariablesOnlyKeepStrings(): void
    {
        $_SERVER['SERVER_NAME'] = 'internal.example.com';
        $_SERVER['argv'] = ['script.php'];

        $variables = HttpRequest::fromGlobals()->getServerVariables();

        $this->assertArrayHasKey('SERVER_NAME', $variables);
        $this->assertSame('internal.example.com', $variables['SERVER_NAME']);
        $this->assertArrayNotHasKey('argv', $variables);
    }
}
