<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpResponse;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: sending the response (`sendAndExit()` prints and exits); `isNotModified()` is the 304 decision.
 */
final class HttpResponseConditionalRequestTest extends TestCase
{
    private const int MODIFIED = 1_790_000_000;

    public function testRequestWithoutConditionalHeadersIsModified(): void
    {
        $httpRequest = HttpRequestFactory::create();

        $this->assertFalse(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testMatchingETagIsNotModified(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['If-None-Match' => 'abc']);

        $this->assertTrue(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testOtherETagIsModified(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['If-None-Match' => 'other']);

        $this->assertFalse(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testSameModificationTimeIsNotModified(): void
    {
        $httpRequest = HttpRequestFactory::create(
            headers: ['If-Modified-Since' => gmdate(format: 'r', timestamp: self::MODIFIED)],
        );

        $this->assertTrue(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testOtherModificationTimeIsModified(): void
    {
        $httpRequest = HttpRequestFactory::create(
            headers: ['If-Modified-Since' => gmdate(format: 'r', timestamp: self::MODIFIED - 60)],
        );

        $this->assertFalse(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testInvalidModificationDateIsModified(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['If-Modified-Since' => 'not a date']);

        $this->assertFalse(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function provideIfNoneMatch(): array
    {
        return [
            'quoted' => ['"abc"', true],
            'unquoted' => ['abc', true],
            'weak' => ['W/"abc"', true],
            'in a list' => ['"x", "abc" , "y"', true],
            'any' => ['*', true],
            'other' => ['"abcd"', false],
            'compressed by Apache' => ['"abc-gzip"', true],
            'compressed by Apache with Brotli' => ['"abc-br"', true],
            'other suffix' => ['"abc-zip"', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('provideIfNoneMatch')]
    public function testIfNoneMatchIsComparedWeaklyWithEveryEntityTag(string $ifNoneMatch, bool $isNotModified): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['If-None-Match' => $ifNoneMatch]);

        $this->assertSame($isNotModified, HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: [
            'If-None-Match' => '"other"',
            'If-Modified-Since' => gmdate(format: 'r', timestamp: self::MODIFIED),
        ]);

        $this->assertFalse(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }

    public function testLaterModificationTimeIsNotModified(): void
    {
        $httpRequest = HttpRequestFactory::create(
            headers: ['If-Modified-Since' => gmdate(format: 'r', timestamp: self::MODIFIED + 60)],
        );

        $this->assertTrue(HttpResponse::isNotModified(
            httpRequest: $httpRequest,
            eTag: 'abc',
            lastModifiedTimeStamp: HttpResponseConditionalRequestTest::MODIFIED,
        ));
    }
}
