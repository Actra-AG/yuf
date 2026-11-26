<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlTargetUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurlTargetUrlTest extends TestCase
{
    /**
     * @return array<string, array{string, string, int, ?string}>
     */
    public static function urlProvider(): array
    {
        return [
            'https' => ['https://Api.Example.com/v1', 'api.example.com', 443, null],
            'http' => ['http://example.com', 'example.com', 80, null],
            'explicit port' => ['https://example.com:8443/', 'example.com', 8443, null],
            'IPv4' => ['http://127.0.0.1:8080/', '127.0.0.1', 8080, '127.0.0.1'],
            'IPv6' => ['https://[2001:DB8::1]/', '[2001:db8::1]', 443, '2001:db8::1'],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testHostPortAndIpAddress(string $url, string $host, int $port, ?string $ipAddress): void
    {
        $targetUrl = new CurlTargetUrl(url: $url);

        $this->assertSame($host, $targetUrl->host);
        $this->assertSame($port, $targetUrl->port);
        $this->assertSame($ipAddress, $targetUrl->getIpAddress());
    }
}
