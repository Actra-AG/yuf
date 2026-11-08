<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\LoginRedirect;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoginRedirectTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function uris(): iterable
    {
        yield 'root' => ['/', true];
        yield 'path with query' => ['/en/secret.html?id=7&x=%2F', true];
        yield 'empty' => ['', false];
        yield 'relative' => ['secret.html', false];
        yield 'absolute URL' => ['https://example.com/', false];
        yield 'protocol relative' => ['//example.com/', false];
        yield 'backslash after slash' => ['/\\example.com/', false];
        yield 'backslash later' => ['/a\\b', false];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'line feed' => ["/a\nLocation: x", false];
        yield 'trailing line feed' => ["/a\n", false];
        yield 'carriage return' => ["/a\rb", false];
        yield 'tab' => ["/\texample.com", false];
        yield 'space' => ['/a b', false];
        yield 'port-like segment' => ['/x:80', false];
        yield 'port only' => ['/:80', false];
        yield 'nul' => ["/a\0b", false];
    }

    #[DataProvider('uris')]
    public function testIsLocalPath(string $uri, bool $expected): void
    {
        $this->assertSame($expected, LoginRedirect::isLocalPath(uri: $uri));
    }

    public function testCreateLoginUriEncodesTheReturnTarget(): void
    {
        $this->assertSame(
            '/login/?returnTo=%2Fa%2F%3Fb%3D1%26c%3D2',
            LoginRedirect::createLoginUri(loginPath: '/login/', returnUri: '/a/?b=1&c=2'),
        );
    }

    public function testCreateLoginUriAppendsToAnExistingQuery(): void
    {
        $this->assertSame(
            '/login/?lang=en&returnTo=%2Fa%2F',
            LoginRedirect::createLoginUri(loginPath: '/login/?lang=en', returnUri: '/a/'),
        );
    }

    public function testCreateLoginUriOmitsAForeignReturnTarget(): void
    {
        $this->assertSame(
            '/login/',
            LoginRedirect::createLoginUri(loginPath: '/login/', returnUri: '//example.com/'),
        );
    }

    public function testFindReturnPathReadsALocalTarget(): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['returnTo' => '/a/?b=1']);

        $this->assertSame('/a/?b=1', LoginRedirect::findReturnPath(httpRequest: $httpRequest));
    }

    public function testFindReturnPathIgnoresAForeignTarget(): void
    {
        $httpRequest = HttpRequestFactory::create(queryParameters: ['returnTo' => 'https://example.org/']);

        $this->assertNull(LoginRedirect::findReturnPath(httpRequest: $httpRequest));
    }

    public function testFindReturnPathWithoutTargetIsNull(): void
    {
        $this->assertNull(LoginRedirect::findReturnPath(httpRequest: HttpRequestFactory::create()));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function httpsUrls(): iterable
    {
        yield 'URL' => ['https://site-a.example.com/de/page.html?a=1', 'site-a.example.com'];
        yield 'root' => ['https://www.example.net/', 'www.example.net'];
        yield 'uppercase host' => ['https://SITE-A.example.com/de/', 'site-a.example.com'];
        yield 'single label' => ['https://localhost/', 'localhost'];
        yield 'punycode' => ['https://xn--ste-a-mta.example.com/', 'xn--ste-a-mta.example.com'];
        yield 'path with at' => ['https://site-a.example.com/@example.net', 'site-a.example.com'];
        yield 'encoded path' => ['https://site-a.example.com/%2F%2Fexample.net', 'site-a.example.com'];
        yield 'no path' => ['https://site-a.example.com', null];
        yield 'http' => ['http://site-a.example.com/', null];
        yield 'uppercase scheme' => ['HTTPS://site-a.example.com/', null];
        yield 'local path' => ['/de/page.html', null];
        yield 'protocol relative' => ['//site-a.example.com/', null];
        yield 'javascript' => ['javascript:alert(1)//site-a.example.com/', null];
        yield 'port' => ['https://site-a.example.com:8443/', null];
        yield 'standard port' => ['https://site-a.example.com:443/', null];
        yield 'user info' => ['https://user@site-a.example.com/', null];
        yield 'user info with host' => ['https://site-a.example.com@example.net/', null];
        yield 'backslash' => ['https://site-a.example.com\\@example.net/', null];
        yield 'backslash in path' => ['https://site-a.example.com/\\example.net/', null];
        yield 'double slash path' => ['https://site-a.example.com//example.net/', null];
        yield 'encoded at' => ['https://site-a.example.com%40example.net/', null];
        yield 'encoded slash' => ['https://site-a.example.com%2F@example.net/', null];
        yield 'encoded scheme' => ['https%3A%2F%2Fsite-a.example.com%2F', null];
        yield 'IDN' => ['https://sïte-a.example.com/', null];
        yield 'trailing dot' => ['https://site-a.example.com./', null];
        yield 'empty label' => ['https://site..example.com/', null];
        yield 'hyphen at label start' => ['https://-site.example.com/', null];
        yield 'too long label' => ['https://' . str_repeat(string: 'a', times: 64) . '.example.com/', null];
        yield 'white space' => ['https://site-a.example.com/a b', null];
        yield 'tab in host' => ["https://site-a.example.com\t.example.net/", null];
        yield 'line feed' => ["https://site-a.example.com/a\nLocation: https://example.net/", null];
        yield 'nul' => ["https://site-a.example.com/a\0b", null];
        yield 'fragment host' => ['https://example.net#https://site-a.example.com/', null];
    }

    #[DataProvider('httpsUrls')]
    public function testFindHttpsHost(string $url, ?string $expected): void
    {
        $this->assertSame($expected, LoginRedirect::findHttpsHost(url: $url));
    }
}
