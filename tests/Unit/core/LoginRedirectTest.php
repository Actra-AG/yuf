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
}
