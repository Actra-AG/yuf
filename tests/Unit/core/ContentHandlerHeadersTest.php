<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\security\CspNonce;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The response headers a view adds with `ContentHandler::setHeader()`.
 */
final class ContentHandlerHeadersTest extends TestCase
{
    private function createHandler(): ContentHandler
    {
        return new ContentHandler(contentType: ContentType::createJson(), cspNonce: new CspNonce(value: 'n'));
    }

    public function testNoHeadersByDefault(): void
    {
        $this->assertSame([], $this->createHandler()->listHeaders());
    }

    public function testHeadersAreKeptInOrder(): void
    {
        $handler = $this->createHandler();
        $handler->setHeader(name: 'Link', value: '</style.css>; rel=preload');
        $handler->setHeader(name: 'X-Request-Id', value: "a\tb");

        $this->assertSame(
            ['Link' => '</style.css>; rel=preload', 'X-Request-Id' => "a\tb"],
            $handler->listHeaders(),
        );
    }

    public function testHeaderIsReplacedCaseInsensitively(): void
    {
        $handler = $this->createHandler();
        $handler->setHeader(name: 'retry-after', value: '10');
        $handler->setHeader(name: 'Retry-After', value: '20');

        $this->assertSame(['Retry-After' => '20'], $handler->listHeaders());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function retryAfterProvider(): array
    {
        return [
            'seconds' => [30, '30'],
            'zero' => [0, '1'],
            'negative' => [-5, '1'],
        ];
    }

    #[DataProvider('retryAfterProvider')]
    public function testRetryAfterIsAtLeastOneSecond(int $seconds, string $expected): void
    {
        $handler = $this->createHandler();
        $handler->setRetryAfter(seconds: $seconds);

        $this->assertSame(['Retry-After' => $expected], $handler->listHeaders());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidHeaderProvider(): array
    {
        return [
            'empty name' => ['', '1', 'The response header name "" is invalid.'],
            'space in name' => ['Retry After', '1', 'The response header name "Retry After" is invalid.'],
            'colon in name' => ['X-A:', '1', 'is invalid'],
            'line break in name' => ["X-A\n", '1', 'is invalid'],
            'carriage return in value' => ['X-A', "1\rSet-Cookie: a=b", 'must not contain control characters'],
            'line feed in value' => ['X-A', "1\nSet-Cookie: a=b", 'must not contain control characters'],
            'null byte in value' => ['X-A', "1\0", 'must not contain control characters'],
            'content type' => ['content-type', 'text/html', 'is set by yuf and cannot be set by a view'],
            'CSP' => ['Content-Security-Policy', 'default-src *', 'is set by yuf'],
            'cache control' => ['Cache-Control', 'public', 'is set by yuf'],
            'etag' => ['ETag', '"x"', 'is set by yuf'],
            'HSTS' => ['Strict-Transport-Security', 'max-age=0', 'is set by yuf'],
            'nosniff' => ['X-Content-Type-Options', 'none', 'is set by yuf'],
            'referrer' => ['Referrer-Policy', 'unsafe-url', 'is set by yuf'],
            'language' => ['Content-Language', 'en', 'is set by yuf'],
            'location' => ['Location', 'https://evil.example/', 'is set by yuf'],
            'cookie' => ['Set-Cookie', 'a=b', 'is set by yuf'],
            'length' => ['Content-Length', '1', 'is set by yuf'],
        ];
    }

    #[DataProvider('invalidHeaderProvider')]
    public function testInvalidHeaderThrows(string $name, string $value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->createHandler()->setHeader(name: $name, value: $value);
    }
}
