<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\LoginTarget;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoginTargetTest extends TestCase
{
    public function testLocalTarget(): void
    {
        $loginTarget = LoginTarget::local(path: '/de/login.html?lang=de');

        $this->assertSame('/de/login.html?lang=de', $loginTarget->uri);
        $this->assertNull($loginTarget->host);
        $this->assertSame('/de/login.html', $loginTarget->path);
        $this->assertFalse($loginTarget->isRemote());
    }

    public function testRemoteTarget(): void
    {
        $loginTarget = LoginTarget::remote(url: 'https://Login.Example.net/login.html?lang=de');

        $this->assertSame('https://Login.Example.net/login.html?lang=de', $loginTarget->uri);
        $this->assertSame('login.example.net', $loginTarget->host);
        $this->assertSame('/login.html', $loginTarget->path);
        $this->assertTrue($loginTarget->isRemote());
    }

    #[DataProvider('invalidLocalPaths')]
    public function testInvalidLocalPathThrows(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The login path must be a local path starting with "/"');

        LoginTarget::local(path: $path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLocalPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['login/'];
        yield 'absolute URL' => ['https://example.com/login/'];
        yield 'protocol relative' => ['//example.com/login/'];
    }

    #[DataProvider('invalidRemoteUrls')]
    public function testInvalidRemoteUrlThrows(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoginTarget::remote(url: $url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRemoteUrls(): iterable
    {
        yield 'http' => ['http://login.example.net/login.html'];
        yield 'uppercase scheme' => ['HTTPS://login.example.net/login.html'];
        yield 'no host' => ['https:///login.html'];
        yield 'no path' => ['https://login.example.net'];
        yield 'local path' => ['/login.html'];
        yield 'protocol relative' => ['//login.example.net/login.html'];
        yield 'double slash path' => ['https://login.example.net//example.net/'];
        yield 'user info' => ['https://user@login.example.net/login.html'];
        yield 'user and password' => ['https://user:pw@login.example.net/login.html'];
        yield 'port' => ['https://login.example.net:8443/login.html'];
        yield 'line feed' => ["https://login.example.net/login.html\nX: y"];
        yield 'nul' => ["https://login.example.net/a\0b"];
        yield 'space' => ['https://login.example.net/a b'];
        yield 'backslash' => ['https://login.example.net\\example.net/'];
        yield 'IDN' => ['https://lögin.example.net/login.html'];
        yield 'trailing dot' => ['https://login.example.net./login.html'];
    }

    public function testLocalLoginUriHasTheRequestUriAsReturnTarget(): void
    {
        $httpRequest = HttpRequestFactory::create(uri: '/a/?b=1');

        $this->assertSame(
            '/login/?returnTo=%2Fa%2F%3Fb%3D1',
            LoginTarget::local(path: '/login/')->createLoginUri(httpRequest: $httpRequest),
        );
    }

    public function testRemoteLoginUriHasTheRequestUrlAsReturnTarget(): void
    {
        $httpRequest = HttpRequestFactory::create(host: 'site-a.example.com', uri: '/de/page.html');

        $this->assertSame(
            'https://login.example.net/login.html?lang=de&returnTo=https%3A%2F%2Fsite-a.example.com%2Fde%2Fpage.html',
            LoginTarget::remote(url: 'https://login.example.net/login.html?lang=de')->createLoginUri(
                httpRequest: $httpRequest,
            ),
        );
    }

    public function testRemoteLoginUriKeepsTheProtocolOfTheRequest(): void
    {
        $httpRequest = HttpRequestFactory::create(host: 'site-a.example.com', uri: '/', protocol: ProtocolEnum::HTTP);

        $this->assertSame(
            'https://login.example.net/login.html?returnTo=http%3A%2F%2Fsite-a.example.com%2F',
            LoginTarget::remote(url: 'https://login.example.net/login.html')->createLoginUri(httpRequest: $httpRequest),
        );
    }

    public function testRemoteLoginUriOmitsAnInvalidRequestUri(): void
    {
        $httpRequest = HttpRequestFactory::create(host: 'site-a.example.com', uri: '//example.net/');

        $this->assertSame(
            'https://login.example.net/login.html',
            LoginTarget::remote(url: 'https://login.example.net/login.html')->createLoginUri(httpRequest: $httpRequest),
        );
    }

    public function testLocalLoginRequestIsRecognizedByPath(): void
    {
        $loginTarget = LoginTarget::local(path: '/login.html?lang=de');

        $this->assertTrue($loginTarget->isLoginRequest(httpRequest: HttpRequestFactory::create(uri: '/login.html?x=1')));
        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'other.example.com', uri: '/login.html'),
        ));
        $this->assertFalse($loginTarget->isLoginRequest(httpRequest: HttpRequestFactory::create(uri: '/other.html')));
    }

    public function testRemoteLoginRequestIsRecognizedByHostAndPath(): void
    {
        $loginTarget = LoginTarget::remote(url: 'https://login.example.net/login.html');

        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'login.example.net', uri: '/login.html?returnTo=x'),
        ));
        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'LOGIN.example.net', uri: '/login.html'),
        ));
        $this->assertFalse($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'site-a.example.com', uri: '/login.html'),
        ));
        $this->assertFalse($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'login.example.net', uri: '/other.html'),
        ));
    }
}
