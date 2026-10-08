<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\tests\Double\core\HttpRequestFactory;
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

    public function testResponseToARequestWithAnOtherETagCarriesTheContent(): void
    {
        $httpRequest = HttpRequestFactory::create(headers: ['If-None-Match' => 'other']);

        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: null,
            nonce: null,
            httpRequest: $httpRequest,
        );

        $headers = $httpResponse->listHeaders();
        $this->assertArrayHasKey('Etag', $headers);
        $this->assertSame(64, strlen(string: $headers['Etag']));
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $httpResponse->httpStatusCode);
    }
}
