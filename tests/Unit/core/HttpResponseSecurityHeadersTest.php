<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class HttpResponseSecurityHeadersTest extends TestCase
{
    /**
     * @return array<mixed>
     */
    private function headersOf(HttpResponse $httpResponse): array
    {
        $headers = new ReflectionProperty(class: HttpResponse::class, property: 'headers')->getValue(object: $httpResponse);
        $this->assertIsArray($headers);

        return $headers;
    }

    public function testFileResponseWithoutCachingKeepsHsts(): void
    {
        $httpResponse = HttpResponse::createResponseFromFilePath(
            absolutePathToFile: __FILE__,
            forceDownload: true,
            individualFileName: null,
            maxAge: 0,
            httpRequest: HttpRequestFactory::create(),
        );

        $headers = $this->headersOf(httpResponse: $httpResponse);
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertSame('max-age=31536000', $headers['Strict-Transport-Security']);
    }

    public function testHtmlResponseSendsHstsForOneYear(): void
    {
        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: null,
            nonce: null,
            httpRequest: HttpRequestFactory::create(),
        );

        $headers = $this->headersOf(httpResponse: $httpResponse);
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertSame('max-age=31536000', $headers['Strict-Transport-Security']);
    }

    public function testResponsesForbidMimeSniffingAndLimitTheReferrer(): void
    {
        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: null,
            nonce: null,
            httpRequest: HttpRequestFactory::create(),
        );

        $headers = $this->headersOf(httpResponse: $httpResponse);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertSame('strict-origin-when-cross-origin', $headers['Referrer-Policy']);
    }

    public function testCspHeaderContainsTheNonceForScriptsAndStyles(): void
    {
        $cspNonce = new CspNonce(value: 'fixed+nonce==');
        // The default policy reads protocol and host of the request
        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_OK,
            htmlContent: '<p>Test</p>',
            cspPolicySettings: new CspPolicySettings(),
            nonce: $cspNonce->value,
            httpRequest: HttpRequestFactory::create(host: 'example.test'),
        );

        $headers = $this->headersOf(httpResponse: $httpResponse);
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $policy = $headers['Content-Security-Policy'];
        $this->assertIsString($policy);
        $this->assertMatchesRegularExpression("#script-src [^;]*'nonce-fixed\\+nonce=='#", $policy);
        $this->assertMatchesRegularExpression("#style-src [^;]*'nonce-fixed\\+nonce=='#", $policy);
    }
}
