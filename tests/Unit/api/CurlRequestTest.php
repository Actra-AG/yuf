<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\AbstractCurlRequest;
use actra\yuf\api\request\CurlDeleteRequest;
use actra\yuf\api\request\CurlGetRequest;
use actra\yuf\api\request\CurlHeadRequest;
use actra\yuf\api\request\CurlPatchRequest;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\api\request\CurlPutRequest;
use actra\yuf\core\RequestMethodEnum;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The request as a value: no cURL, no network.
 */
final class CurlRequestTest extends TestCase
{
    private const string URL = 'https://api.example.com/v1/items?page=2';

    /**
     * @return iterable<string, array{0: AbstractCurlRequest, 1: RequestMethodEnum}>
     */
    public static function methodProvider(): iterable
    {
        return [
            'get' => [CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL), RequestMethodEnum::GET],
            'head' => [CurlHeadRequest::create(requestTargetUrl: CurlRequestTest::URL), RequestMethodEnum::HEAD],
            'delete' => [CurlDeleteRequest::create(requestTargetUrl: CurlRequestTest::URL), RequestMethodEnum::DELETE],
            'patch' => [
                CurlPatchRequest::createWithoutBody(requestTargetUrl: CurlRequestTest::URL),
                RequestMethodEnum::PATCH,
            ],
            'post' => [
                CurlPostRequest::createWithJsonBody(requestTargetUrl: CurlRequestTest::URL, jsonString: '{}'),
                RequestMethodEnum::POST,
            ],
            'put' => [
                CurlPutRequest::createWithJsonBody(requestTargetUrl: CurlRequestTest::URL, jsonString: '{}'),
                RequestMethodEnum::PUT,
            ],
        ];
    }

    #[DataProvider('methodProvider')]
    public function testRequestKnowsMethodAndUrl(AbstractCurlRequest $request, RequestMethodEnum $expectedMethod): void
    {
        $this->assertSame($expectedMethod, $request->getMethod());
        $this->assertSame(CurlRequestTest::URL, $request->getUrl());
    }

    public function testRequestWithoutBodyHasNoBodyAndNoHeaders(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->assertNull($request->getBody());
        $this->assertSame([], $this->headersOf(request: $request));
    }

    public function testDefaults(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->assertSame(3, $request->getConnectTimeoutInSeconds());
        $this->assertSame(10, $request->getRequestTimeoutInSeconds());
        $this->assertSame(33554432, $request->getMaxResponseSizeInBytes());
        $this->assertFalse($request->isRedirectionResponseCodeAccepted());
        $this->assertNull($request->getAuthentication());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidUrlProvider(): iterable
    {
        return [
            'empty' => [''],
            'relative' => ['/v1/items'],
            'no scheme' => ['api.example.com/v1'],
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://api.example.com/'],
            'gopher' => ['gopher://api.example.com/'],
            'dict' => ['dict://api.example.com:11211/'],
            'javascript' => ['javascript:alert(1)'],
            'no host' => ['https:///v1'],
            'user name' => ['https://user@api.example.com/'],
            'user name and password' => ['https://user:secret@api.example.com/'],
            'only password' => ['https://:secret@api.example.com/'],
            'line break' => ["https://api.example.com/v1\r\nHost: evil.example"],
            'space' => ['https://api.example.com/a b'],
            'null byte' => ["https://api.example.com/\0"],
            'backslash' => ['https://api.example.com\\@evil.example/'],
        ];
    }

    #[DataProvider('invalidUrlProvider')]
    public function testInvalidUrlIsRejected(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        CurlGetRequest::create(requestTargetUrl: $url);
    }

    public function testExceptionOfAnInvalidUrlDoesNotShowTheUrl(): void
    {
        try {
            CurlGetRequest::create(requestTargetUrl: 'ftp://api.example.com/?token=SECRET-TOKEN');
            CurlRequestTest::fail('The URL must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringNotContainsString('SECRET-TOKEN', $exception->getMessage());
            $this->assertStringNotContainsString('api.example.com', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validUrlProvider(): iterable
    {
        return [
            'http' => ['http://api.example.com/'],
            'https with port and query' => ['https://api.example.com:8443/v1?a=b&c=d#top'],
            'upper case scheme' => ['HTTPS://API.EXAMPLE.COM/'],
            'ip address' => ['http://192.0.2.1/'],
            'ip v6' => ['http://[2001:db8::1]/'],
            'encoded' => ['https://api.example.com/a%20b?q=%C3%A4'],
        ];
    }

    #[DataProvider('validUrlProvider')]
    public function testValidUrlIsKept(string $url): void
    {
        $this->assertSame($url, CurlGetRequest::create(requestTargetUrl: $url)->getUrl());
    }

    public function testHeadersAreSentInTheOrderTheyWereSet(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);
        $request->setHttpHeader(key: 'X-First', value: '1');
        $request->setHttpHeader(key: 'X-Second', value: '2');

        $this->assertSame(['X-First' => '1', 'X-Second' => '2'], $this->headersOf(request: $request));
    }

    public function testHeaderIsReplacedCaseInsensitively(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);
        $request->setHttpHeader(key: 'X-Api-Key', value: 'old');
        $request->setHttpHeader(key: 'x-api-key', value: 'new');

        $this->assertSame(['x-api-key' => 'new'], $this->headersOf(request: $request));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function protectedHeaderProvider(): iterable
    {
        return [
            'Content-Type' => ['Content-Type'],
            'lower case content-type' => ['content-type'],
            'upper case CONTENT-TYPE' => ['CONTENT-TYPE'],
            'Content-Length' => ['Content-Length'],
            'lower case content-length' => ['content-length'],
        ];
    }

    #[DataProvider('protectedHeaderProvider')]
    public function testHeadersOfTheBodyCannotBeSet(string $name): void
    {
        $request = CurlPostRequest::createWithJsonBody(requestTargetUrl: CurlRequestTest::URL, jsonString: '{}');

        $this->expectException(LogicException::class);

        $request->setHttpHeader(key: $name, value: 'x');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function invalidHeaderProvider(): iterable
    {
        return [
            'line break in value' => ['X-Test', "value\r\nX-Injected: 1"],
            'line feed in value' => ['X-Test', "value\nX-Injected: 1"],
            'carriage return in value' => ['X-Test', "value\rX-Injected: 1"],
            'null byte in value' => ['X-Test', "val\0ue"],
            'control character in value' => ['X-Test', "val\x1Bue"],
            'delete character in value' => ['X-Test', "val\x7Fue"],
            'line break in name' => ["X-Test\r\nX-Injected", 'value'],
            'colon in name' => ['X-Test: 1', 'value'],
            'space in name' => ['X Test', 'value'],
            'empty name' => ['', 'value'],
            'non ASCII name' => ['X-Tést', 'value'],
        ];
    }

    #[DataProvider('invalidHeaderProvider')]
    public function testHeaderInjectionIsRejected(string $name, string $value): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->expectException(InvalidArgumentException::class);

        $request->setHttpHeader(key: $name, value: $value);
    }

    public function testExceptionOfAnInvalidHeaderValueDoesNotShowTheValue(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        try {
            $request->setHttpHeader(key: 'X-Api-Key', value: "SECRET-KEY\r\n");
            CurlRequestTest::fail('The header must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringNotContainsString('SECRET-KEY', $exception->getMessage());
            $this->assertStringContainsString('X-Api-Key', $exception->getMessage());
        }
    }

    public function testHeaderValueWithTabAndUnicodeIsAllowed(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);
        $request->setHttpHeader(key: 'X-Test', value: "a\tb ä");

        $this->assertSame(['X-Test' => "a\tb ä"], $this->headersOf(request: $request));
    }

    public function testHeaderLineOfAnEmptyValueIsSentWithSemicolon(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);
        $request->setHttpHeader(key: 'X-Empty', value: '');

        $headers = $request->getHttpHeaders();
        $this->assertCount(1, $headers);
        $this->assertSame('X-Empty;', $headers[0]->toLine());
    }

    public function testPostFieldsBody(): void
    {
        $request = CurlPostRequest::createWithPostBody(
            requestTargetUrl: CurlRequestTest::URL,
            postData: ['a' => 'b c'],
        );

        $this->assertSame('a=b%20c', $request->getBody());
        $this->assertSame(
            ['Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'],
            $this->headersOf(request: $request),
        );
    }

    public function testXmlBody(): void
    {
        $request = CurlPutRequest::createWithXmlBody(requestTargetUrl: CurlRequestTest::URL, xmlString: '<a/>');

        $this->assertSame('<a/>', $request->getBody());
        $this->assertSame(
            ['HTTP_PRETTY_PRINT' => 'TRUE', 'Content-Type' => 'text/xml; charset=utf-8'],
            $this->headersOf(request: $request),
        );
    }

    public function testJsonBody(): void
    {
        $request = CurlPatchRequest::createWithJsonBody(requestTargetUrl: CurlRequestTest::URL, jsonString: '{"a":1}');

        $this->assertSame('{"a":1}', $request->getBody());
        $this->assertSame(['Content-Type' => 'application/json; charset=utf-8'], $this->headersOf(request: $request));
    }

    public function testJsonApiBody(): void
    {
        $request = CurlPostRequest::createJsonApiRequest(requestTargetUrl: CurlRequestTest::URL, jsonString: '{}');

        $this->assertSame(
            ['Accept' => 'application/vnd.api+json', 'Content-Type' => 'application/vnd.api+json'],
            $this->headersOf(request: $request),
        );
    }

    public function testPlainTextBody(): void
    {
        $request = CurlPostRequest::createWithPlainTextBody(requestTargetUrl: CurlRequestTest::URL, plainText: 'hi');

        $this->assertSame('hi', $request->getBody());
        $this->assertSame(['Content-Type' => 'text/plain; charset=utf-8'], $this->headersOf(request: $request));
    }

    public function testDefaultHeaderOfTheBodyCanBeReplaced(): void
    {
        $request = CurlPostRequest::createJsonApiRequest(requestTargetUrl: CurlRequestTest::URL, jsonString: '{}');
        $request->setHttpHeader(key: 'accept', value: 'application/json');

        $this->assertSame(
            ['accept' => 'application/json', 'Content-Type' => 'application/vnd.api+json'],
            $this->headersOf(request: $request),
        );
    }

    public function testTimeouts(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $request->setTimeoutInSeconds(connectTimeOut: 3, requestTimeOut: 30);

        $this->assertSame(3, $request->getConnectTimeoutInSeconds());
        $this->assertSame(30, $request->getRequestTimeoutInSeconds());
    }

    /**
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function invalidTimeoutProvider(): iterable
    {
        return [
            'zero request timeout (cURL would wait forever)' => [0, 0],
            'zero connect timeout' => [0, 5],
            'negative' => [-1, 5],
            'negative request timeout' => [1, -5],
        ];
    }

    #[DataProvider('invalidTimeoutProvider')]
    public function testTimeoutsBelowOneSecondAreRejected(int $connectTimeout, int $requestTimeout): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->expectException(InvalidArgumentException::class);

        $request->setTimeoutInSeconds(connectTimeOut: $connectTimeout, requestTimeOut: $requestTimeout);
    }

    public function testConnectTimeoutLongerThanRequestTimeoutIsRejected(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->expectException(LogicException::class);

        $request->setTimeoutInSeconds(connectTimeOut: 5, requestTimeOut: 2);
    }

    public function testMaxResponseSize(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: 1024);

        $this->assertSame(1024, $request->getMaxResponseSizeInBytes());
    }

    public function testMaxResponseSizeBelowOneByteIsRejected(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->expectException(InvalidArgumentException::class);

        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: 0);
    }

    public function testRedirectionResponseCodeCanBeAccepted(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $request->acceptRedirectionResponseCode();

        $this->assertTrue($request->isRedirectionResponseCodeAccepted());
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function credentialsTargetProvider(): iterable
    {
        return [
            'https' => ['https://api.example.com/', true],
            'http to a public host' => ['http://api.example.com/', false],
            'http to an IP address' => ['http://192.0.2.1/', false],
            'http to localhost' => ['http://localhost:8080/', true],
            'http to 127.0.0.1' => ['http://127.0.0.1:8080/', true],
            'http to 127.1.2.3' => ['http://127.1.2.3/', true],
            'http to ::1' => ['http://[::1]:8080/', true],
            'http to a host that starts like localhost' => ['http://localhost.example.com/', false],
            'http to a host that starts like 127.' => ['http://127.example.com/', false],
        ];
    }

    #[DataProvider('credentialsTargetProvider')]
    public function testCredentialsAreOnlySentOverHttpsOrToThisMachine(string $url, bool $isAllowed): void
    {
        $bearerRequest = CurlGetRequest::create(requestTargetUrl: $url);
        $basicRequest = CurlGetRequest::create(requestTargetUrl: $url);
        if (!$isAllowed) {
            $this->expectException(LogicException::class);
        }

        $bearerRequest->useTokenAuthentication(token: 'token');
        $basicRequest->useBasicHttpAuthentication(authUserNamePassword: 'user:password');

        $this->assertNotNull($bearerRequest->getAuthentication());
        $this->assertNotNull($basicRequest->getAuthentication());
    }

    public function testExceptionOfCredentialsOverHttpDoesNotShowThem(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: 'http://api.example.com/?key=SECRET-KEY');

        try {
            $request->useTokenAuthentication(token: 'SECRET-TOKEN');
            CurlRequestTest::fail('Credentials over plain HTTP must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringNotContainsString('SECRET', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidTokenProvider(): iterable
    {
        return [
            'empty' => [''],
            'space' => ['to ken'],
            'line break' => ["token\r\nX-Injected: 1"],
            'tab' => ["to\tken"],
            'non ASCII' => ['tökén'],
        ];
    }

    #[DataProvider('invalidTokenProvider')]
    public function testInvalidBearerTokenIsRejected(string $token): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->expectException(InvalidArgumentException::class);

        $request->useTokenAuthentication(token: $token);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidBasicCredentialsProvider(): iterable
    {
        return [
            'empty' => [''],
            'line break' => ["user:pass\r\n"],
            'null byte' => ["user:pa\0ss"],
        ];
    }

    #[DataProvider('invalidBasicCredentialsProvider')]
    public function testInvalidBasicCredentialsAreRejected(string $credentials): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlRequestTest::URL);

        $this->expectException(InvalidArgumentException::class);

        $request->useBasicHttpAuthentication(authUserNamePassword: $credentials);
    }

    public function testDebugOutputShowsNoSecrets(): void
    {
        $request = CurlPostRequest::createWithJsonBody(
            requestTargetUrl: 'https://api.example.com/v1?apikey=URL-SECRET',
            jsonString: '{"password":"BODY-SECRET"}',
        );
        $request->useTokenAuthentication(token: 'TOKEN-SECRET');
        $request->setHttpHeader(key: 'X-Api-Key', value: 'HEADER-SECRET');

        $dump = print_r(value: $request, return: true) . print_r(value: $request->getAuthentication(), return: true);
        $dump .= (string) json_encode(value: $request->getAuthentication());

        $this->assertStringNotContainsString('URL-SECRET', $dump);
        $this->assertStringNotContainsString('BODY-SECRET', $dump);
        $this->assertStringNotContainsString('TOKEN-SECRET', $dump);
        $this->assertStringNotContainsString('HEADER-SECRET', $dump);
        $this->assertStringContainsString('api.example.com', $dump);
    }

    /**
     * @return array<string, string>
     */
    private function headersOf(AbstractCurlRequest $request): array
    {
        $headers = [];
        foreach ($request->getHttpHeaders() as $header) {
            $headers[$header->name] = $header->value;
        }

        return $headers;
    }
}
