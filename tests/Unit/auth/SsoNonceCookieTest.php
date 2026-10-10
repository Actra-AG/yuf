<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\SsoNonceCookie;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\tests\Double\auth\RecordingSsoNonceCookieWriter;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SsoNonceCookieTest extends TestCase
{
    private const string NONCE = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testIssuedNonceHasThirtyTwoRandomBytesAsHex(): void
    {
        $writer = new RecordingSsoNonceCookieWriter();
        $cookie = new SsoNonceCookie(httpRequest: HttpRequestFactory::create(), cookieWriter: $writer);

        $first = $cookie->issue(redirectUri: 'https://example.com/sso/callback?x=1');
        $second = $cookie->issue(redirectUri: 'https://example.com/sso/callback');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $first);
        $this->assertNotSame($first, $second);
        $this->assertSame(
            [
                ['name' => '__Secure-yuf-sso-nonce', 'value' => $first, 'maxAge' => 600, 'path' => '/sso/callback'],
                ['name' => '__Secure-yuf-sso-nonce', 'value' => $second, 'maxAge' => 600, 'path' => '/sso/callback'],
            ],
            $writer->setCookies,
        );
    }

    public function testConsumeReturnsTheNonceAndDeletesTheCookie(): void
    {
        $writer = new RecordingSsoNonceCookieWriter();
        $cookie = new SsoNonceCookie(
            httpRequest: HttpRequestFactory::create(cookies: [SsoNonceCookie::NAME => SsoNonceCookieTest::NONCE]),
            cookieWriter: $writer,
        );

        $nonce = $cookie->consume(redirectUri: 'https://example.com/sso/callback');

        $this->assertSame(SsoNonceCookieTest::NONCE, $nonce);
        $this->assertSame([['name' => SsoNonceCookie::NAME, 'path' => '/sso/callback']], $writer->deletedCookies);
    }

    public function testConsumeWithoutCookieFails(): void
    {
        $cookie = new SsoNonceCookie(
            httpRequest: HttpRequestFactory::create(),
            cookieWriter: new RecordingSsoNonceCookieWriter(),
        );

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Missing SSO nonce cookie');

        $cookie->consume(redirectUri: 'https://example.com/sso/callback');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNonceProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'short' => ['abc'];
        yield 'upper case' => [strtoupper(string: SsoNonceCookieTest::NONCE)];
        yield 'not hex' => [str_repeat(string: 'g', times: 64)];
        yield 'with line break' => [SsoNonceCookieTest::NONCE . "\n"];
    }

    #[DataProvider('invalidNonceProvider')]
    public function testConsumeOfAnInvalidNonceFailsAndDeletesTheCookie(string $value): void
    {
        $writer = new RecordingSsoNonceCookieWriter();
        $cookie = new SsoNonceCookie(
            httpRequest: HttpRequestFactory::create(cookies: [SsoNonceCookie::NAME => $value]),
            cookieWriter: $writer,
        );

        try {
            $cookie->consume(redirectUri: 'https://example.com/sso/callback');
            SsoNonceCookieTest::fail('The nonce was accepted.');
        } catch (UnauthorizedException) {
            $this->assertCount(1, $writer->deletedCookies);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRedirectUriProvider(): iterable
    {
        yield 'no path' => ['https://example.com'];
        yield 'relative' => ['sso/callback'];
        yield 'semicolon' => ['https://example.com/a;b'];
        yield 'space' => ['https://example.com/a b'];
    }

    #[DataProvider('invalidRedirectUriProvider')]
    public function testRedirectUriWithoutUsablePathIsRefused(string $redirectUri): void
    {
        $cookie = new SsoNonceCookie(
            httpRequest: HttpRequestFactory::create(),
            cookieWriter: new RecordingSsoNonceCookieWriter(),
        );

        $this->expectException(InvalidArgumentException::class);

        $cookie->issue(redirectUri: $redirectUri);
    }
}
