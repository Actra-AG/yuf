<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\UrlHelper;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, ProtocolEnum, string}>
     */
    public static function uriProvider(): iterable
    {
        $https = ProtocolEnum::HTTPS;
        $site = 'https://www.example.com';

        yield 'absolute path' => ['/login.html', '/de/page.html', $https, $site . '/login.html'];
        yield 'relative to a directory' => ['next.html', '/de/page.html', $https, $site . '/de/next.html'];
        yield 'relative to the root' => ['next.html', '/page.html', $https, $site . '/next.html'];
        yield 'query of the request' => ['next.html', '/de/page.html?a=1', $https, $site . '/de/next.html'];
        yield 'slash in the query' => ['next.html', '/de/page.html?a=x/y', $https, $site . '/de/next.html'];
        yield 'protocol of the request' => [
            '/login.html',
            '/',
            ProtocolEnum::HTTP,
            'http://www.example.com/login.html',
        ];
        yield 'query only' => ['?page=2', '/de/page.html', $https, $site . '/de/?page=2'];
        yield 'scheme relative uri gets the protocol' => [
            '//cdn.example.org/x.js',
            '/',
            $https,
            'https://cdn.example.org/x.js',
        ];
        yield 'scheme relative uri with http' => [
            '//cdn.example.org/x.js',
            '/',
            ProtocolEnum::HTTP,
            'http://cdn.example.org/x.js',
        ];
        yield 'absolute uri stays' => [
            'https://other.example.org/x?y=1',
            '/',
            $https,
            'https://other.example.org/x?y=1',
        ];
    }

    #[DataProvider('uriProvider')]
    public function testGenerateAbsoluteUri(
        string $relativeOrAbsoluteUri,
        string $requestUri,
        ProtocolEnum $protocol,
        string $expected,
    ): void {
        $httpRequest = HttpRequestFactory::create(host: 'www.example.com', uri: $requestUri, protocol: $protocol);

        $this->assertSame(
            $expected,
            UrlHelper::generateAbsoluteUri(relativeOrAbsoluteUri: $relativeOrAbsoluteUri, httpRequest: $httpRequest),
        );
    }

    public function testMalformedUriIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('~http:///x~');

        UrlHelper::generateAbsoluteUri(
            relativeOrAbsoluteUri: 'http:///x',
            httpRequest: HttpRequestFactory::create(),
        );
    }
}
