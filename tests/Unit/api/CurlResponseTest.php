<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use JsonException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

final class CurlResponseTest extends TestCase
{
    public function testSuccessfulResponse(): void
    {
        $response = $this->createResponse(body: 'ok');

        self::assertFalse($response->hasErrors());
        self::assertSame('ok', $response->rawResponseBody);
    }

    public function testResponseWithErrorCodeHasErrors(): void
    {
        self::assertTrue($this->createResponse(body: 'x', errorCode: CurlResponse::ERROR_BAD_HTTP_RESPONSE_CODE)->hasErrors());
        self::assertTrue($this->createResponse(body: false, errorCode: CURLE_OPERATION_TIMEOUTED)->hasErrors());
    }

    public function testHeaderLookupIgnoresTheCaseOfTheName(): void
    {
        $response = $this->createResponse(
            body: '',
            headers: ['content-type' => ['text/plain'], 'set-cookie' => ['a=1', 'b=2']],
        );

        self::assertSame('text/plain', $response->getHeader(name: 'Content-Type'));
        self::assertSame('text/plain', $response->getHeader(name: 'CONTENT-TYPE'));
        self::assertSame('a=1', $response->getHeader(name: 'Set-Cookie'));
        self::assertSame(['a=1', 'b=2'], $response->getHeaderValues(name: 'Set-Cookie'));
    }

    public function testMissingHeader(): void
    {
        $response = $this->createResponse(body: '');

        self::assertNull($response->getHeader(name: 'X-Missing'));
        self::assertSame([], $response->getHeaderValues(name: 'X-Missing'));
    }

    public function testJsonObject(): void
    {
        $json = $this->createResponse(body: '{"a":{"b":[1,2]},"big":12345678901234567890}')->getJsonResponse();

        self::assertInstanceOf(stdClass::class, $json);
        self::assertSame('12345678901234567890', $json->big);
    }

    public function testJsonList(): void
    {
        self::assertSame(['a', 'b'], $this->createResponse(body: '["a","b"]')->getJsonResponse());
    }

    public function testInvalidJsonThrows(): void
    {
        $response = $this->createResponse(body: '{"a":');

        $this->expectException(JsonException::class);

        $response->getJsonResponse();
    }

    public function testJsonScalarThrows(): void
    {
        $response = $this->createResponse(body: '"text"');

        $this->expectException(UnexpectedValueException::class);

        $response->getJsonResponse();
    }

    public function testEmptyBodyIsNoJson(): void
    {
        $response = $this->createResponse(body: '');

        $this->expectException(JsonException::class);

        $response->getJsonResponse();
    }

    public function testJsonOfAFailedRequestThrowsInsteadOfATypeError(): void
    {
        $response = $this->createResponse(body: false, errorCode: CURLE_COULDNT_CONNECT);

        $this->expectException(RuntimeException::class);

        $response->getJsonResponse();
    }

    public function testXmlOfAFailedRequestThrowsInsteadOfATypeError(): void
    {
        $response = $this->createResponse(body: false, errorCode: CURLE_COULDNT_CONNECT);

        $this->expectException(RuntimeException::class);

        $response->getXmlResponse();
    }

    public function testXmlWithCdata(): void
    {
        $xml = $this->createResponse(body: '<root><item><![CDATA[a & b]]></item></root>')->getXmlResponse();

        self::assertSame('a & b', (string) $xml->item);
    }

    public function testXmlDoesNotLoadExternalEntities(): void
    {
        $path = tempnam(directory: sys_get_temp_dir(), prefix: 'yuf-xxe-');
        self::assertNotFalse($path);
        file_put_contents(filename: $path, data: 'SECRET-FILE-CONTENT');
        $body = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY e SYSTEM "file://' . $path . '">]><r>&e;</r>';

        try {
            $content = (string) $this->createResponse(body: $body)->getXmlResponse();
        } finally {
            unlink(filename: $path);
        }

        self::assertStringNotContainsString('SECRET-FILE-CONTENT', $content);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function createResponse(false|string $body, int $errorCode = CURLE_OK, array $headers = []): CurlResponse
    {
        return new CurlResponse(
            rawResponseBody: $body,
            curlInfo: ['url' => 'https://example.com/', 'http_code' => 200, 'total_time' => 0.5, 'redirect_count' => 0],
            responseHttpCode: HttpStatusCodeEnum::HTTP_OK,
            totalRequestTime: 0.5,
            errorCode: $errorCode,
            errorMessage: $errorCode === CURLE_OK ? '' : 'error',
            headers: $headers,
        );
    }
}
