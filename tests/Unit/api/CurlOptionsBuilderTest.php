<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\AbstractCurlRequest;
use actra\yuf\api\CurlOptionsBuilder;
use actra\yuf\api\CurlResponseCollector;
use actra\yuf\api\request\CurlDeleteRequest;
use actra\yuf\api\request\CurlGetRequest;
use actra\yuf\api\request\CurlHeadRequest;
use actra\yuf\api\request\CurlPatchRequest;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\api\request\CurlPutRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The cURL options of a request, without sending it. This is where the security settings are tested.
 */
final class CurlOptionsBuilderTest extends TestCase
{
    private const string URL = 'https://api.example.com/v1';

    /**
     * @return iterable<string, array{0: AbstractCurlRequest}>
     */
    public static function everyRequestProvider(): iterable
    {
        return [
            'get' => [CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL)],
            'head' => [CurlHeadRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL)],
            'delete' => [CurlDeleteRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL)],
            'patch' => [CurlPatchRequest::createWithoutBody(requestTargetUrl: CurlOptionsBuilderTest::URL)],
            'post' => [
                CurlPostRequest::createWithJsonBody(requestTargetUrl: CurlOptionsBuilderTest::URL, jsonString: '{}'),
            ],
            'put' => [
                CurlPutRequest::createWithPostBody(requestTargetUrl: CurlOptionsBuilderTest::URL, postData: ['a' => 1]),
            ],
        ];
    }

    #[DataProvider('everyRequestProvider')]
    public function testSecuritySettingsAreSetForEveryRequest(AbstractCurlRequest $request): void
    {
        $options = $this->buildOptions(request: $request);

        $this->assertOption(expected: true, options: $options, option: CURLOPT_SSL_VERIFYPEER);
        $this->assertOption(expected: 2, options: $options, option: CURLOPT_SSL_VERIFYHOST);
        $this->assertOption(expected: CURL_SSLVERSION_TLSv1_2, options: $options, option: CURLOPT_SSLVERSION);
        $this->assertOption(expected: 'http,https', options: $options, option: CURLOPT_PROTOCOLS_STR);
        $this->assertOption(expected: 'http,https', options: $options, option: CURLOPT_REDIR_PROTOCOLS_STR);
        $this->assertOption(expected: false, options: $options, option: CURLOPT_FOLLOWLOCATION);
        $this->assertOption(expected: 3, options: $options, option: CURLOPT_CONNECTTIMEOUT);
        $this->assertOption(expected: 10, options: $options, option: CURLOPT_TIMEOUT);
        $this->assertOption(expected: 33554432, options: $options, option: CURLOPT_MAXFILESIZE);
        $this->assertOption(expected: self::URL, options: $options, option: CURLOPT_URL);
    }

    public function testLimitsAreTakenFromTheRequest(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL);
        $request->setTimeoutInSeconds(connectTimeOut: 2, requestTimeOut: 7);
        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: 500);

        $options = $this->buildOptions(request: $request);

        $this->assertOption(expected: 2, options: $options, option: CURLOPT_CONNECTTIMEOUT);
        $this->assertOption(expected: 7, options: $options, option: CURLOPT_TIMEOUT);
        $this->assertOption(expected: 500, options: $options, option: CURLOPT_MAXFILESIZE);
    }

    public function testMethodOptions(): void
    {
        $get = $this->buildOptions(request: CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL));
        $head = $this->buildOptions(request: CurlHeadRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL));
        $delete = $this->buildOptions(
            request: CurlDeleteRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL),
        );
        $post = $this->buildOptions(
            request: CurlPostRequest::createWithPlainTextBody(
                requestTargetUrl: CurlOptionsBuilderTest::URL,
                plainText: 'a',
            ),
        );
        $put = $this->buildOptions(
            request: CurlPutRequest::createWithPlainTextBody(
                requestTargetUrl: CurlOptionsBuilderTest::URL,
                plainText: 'a',
            ),
        );
        $patch = $this->buildOptions(
            request: CurlPatchRequest::createWithoutBody(requestTargetUrl: CurlOptionsBuilderTest::URL),
        );

        $this->assertOption(expected: true, options: $get, option: CURLOPT_HTTPGET);
        $this->assertOption(expected: true, options: $head, option: CURLOPT_NOBODY);
        $this->assertOption(expected: 'DELETE', options: $delete, option: CURLOPT_CUSTOMREQUEST);
        $this->assertOption(expected: true, options: $post, option: CURLOPT_POST);
        $this->assertOption(expected: 'PUT', options: $put, option: CURLOPT_CUSTOMREQUEST);
        $this->assertOption(expected: 'PATCH', options: $patch, option: CURLOPT_CUSTOMREQUEST);
    }

    public function testBodyAndHeaderLines(): void
    {
        $request = CurlPostRequest::createJsonApiRequest(
            requestTargetUrl: CurlOptionsBuilderTest::URL,
            jsonString: '{"a":1}',
        );
        $request->setHttpHeader(key: 'X-Api-Key', value: 'key');

        $options = $this->buildOptions(request: $request);

        $this->assertOption(expected: '{"a":1}', options: $options, option: CURLOPT_POSTFIELDS);
        $this->assertOption(
            expected: ['Accept: application/vnd.api+json', 'X-Api-Key: key', 'Content-Type: application/vnd.api+json'],
            options: $options,
            option: CURLOPT_HTTPHEADER,
        );
    }

    public function testRequestWithoutBodyHasNoPostFields(): void
    {
        $options = $this->buildOptions(request: CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL));

        self::assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
        $this->assertOption(expected: [], options: $options, option: CURLOPT_HTTPHEADER);
    }

    public function testBasicAuthentication(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL);
        $request->useBasicHttpAuthentication(authUserNamePassword: 'user:pass');

        $options = $this->buildOptions(request: $request);

        $this->assertOption(expected: CURLAUTH_BASIC, options: $options, option: CURLOPT_HTTPAUTH);
        $this->assertOption(expected: 'user:pass', options: $options, option: CURLOPT_USERPWD);
        self::assertArrayNotHasKey(CURLOPT_XOAUTH2_BEARER, $options);
    }

    public function testBearerAuthentication(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL);
        $request->useTokenAuthentication(token: 'abc.def');

        $options = $this->buildOptions(request: $request);

        $this->assertOption(expected: CURLAUTH_BEARER, options: $options, option: CURLOPT_HTTPAUTH);
        $this->assertOption(expected: 'abc.def', options: $options, option: CURLOPT_XOAUTH2_BEARER);
        self::assertArrayNotHasKey(CURLOPT_USERPWD, $options);
    }

    public function testNoAuthenticationByDefault(): void
    {
        $options = $this->buildOptions(request: CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL));

        self::assertArrayNotHasKey(CURLOPT_HTTPAUTH, $options);
        self::assertArrayNotHasKey(CURLOPT_USERPWD, $options);
        self::assertArrayNotHasKey(CURLOPT_XOAUTH2_BEARER, $options);
    }

    public function testCallbacksFillTheCollector(): void
    {
        $collector = new CurlResponseCollector(maxBodyBytes: 100);
        $options = CurlOptionsBuilder::build(
            request: CurlGetRequest::create(requestTargetUrl: CurlOptionsBuilderTest::URL),
            collector: $collector,
        );
        $handle = curl_init();
        self::assertArrayHasKey(CURLOPT_WRITEFUNCTION, $options);
        self::assertArrayHasKey(CURLOPT_HEADERFUNCTION, $options);
        $writeCallback = $options[CURLOPT_WRITEFUNCTION];
        $headerCallback = $options[CURLOPT_HEADERFUNCTION];
        self::assertIsCallable($writeCallback);
        self::assertIsCallable($headerCallback);

        $written = $writeCallback($handle, 'body');
        $headerRead = $headerCallback($handle, "X-A: b\r\n");

        self::assertSame(4, $written);
        self::assertSame(8, $headerRead);
        self::assertSame('body', $collector->getBody());
        self::assertSame(['x-a' => ['b']], $collector->getHeaders());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function removedMethodProvider(): iterable
    {
        return [
            'setCurlOption' => ['setCurlOption'],
            'removeCurlOption' => ['removeCurlOption'],
            'disableSslCheck' => ['disableSslCheck'],
        ];
    }

    #[DataProvider('removedMethodProvider')]
    public function testNoMethodGivesAccessToCurlOptionsOrSwitchesTheCertificateCheckOff(string $method): void
    {
        // The options of the builder are the only ones: a caller cannot weaken them
        self::assertFalse(method_exists(object_or_class: AbstractCurlRequest::class, method: $method));
    }

    /**
     * @param array<int, mixed> $options
     * @param bool|int|string|list<string> $expected
     */
    private function assertOption(bool|int|string|array $expected, array $options, int $option): void
    {
        self::assertArrayHasKey($option, $options);
        self::assertSame($expected, $options[$option]);
    }

    /**
     * @return array<int, mixed>
     */
    private function buildOptions(AbstractCurlRequest $request): array
    {
        return CurlOptionsBuilder::build(request: $request, collector: new CurlResponseCollector(maxBodyBytes: 100));
    }
}
