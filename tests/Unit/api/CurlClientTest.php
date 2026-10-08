<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlClient;
use actra\yuf\api\CurlResponse;
use actra\yuf\api\request\CurlGetRequest;
use actra\yuf\api\request\CurlHeadRequest;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\tests\Double\api\EchoedRequest;
use actra\yuf\tests\Double\api\LocalHttpServer;
use actra\yuf\tests\Double\api\LocalTlsServer;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The client against a web server on the loopback address (no network): response headers, limits, redirects,
 * credentials and the certificate check. The request building is in `CurlRequestCharacterizationTest`.
 */
final class CurlClientTest extends TestCase
{
    private LocalHttpServer $server;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new LocalHttpServer();
    }

    public function testRequestCanBeExecutedMoreThanOnce(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));

        $first = EchoedRequest::fromResponse(response: $request->execute());
        $second = EchoedRequest::fromResponse(response: $request->execute());

        self::assertSame('GET', $first->method);
        self::assertSame('GET', $second->method);
    }

    public function testOptionsOfARequestDoNotCarryOverToTheNextRequestOfTheSameClient(): void
    {
        $client = new CurlClient();
        $post = CurlPostRequest::createWithJsonBody(
            requestTargetUrl: $this->server->url('/echo'),
            jsonString: '{"a":1}',
        );
        $post->useTokenAuthentication(token: 'first-token');
        $post->setHttpHeader(key: 'X-First', value: '1');
        $get = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));

        $postEcho = EchoedRequest::fromResponse(response: $client->send(request: $post));
        $getEcho = EchoedRequest::fromResponse(response: $client->send(request: $get));

        self::assertSame('POST', $postEcho->method);
        self::assertSame('{"a":1}', $postEcho->body);
        self::assertSame('GET', $getEcho->method);
        self::assertSame('', $getEcho->body);
        self::assertFalse($getEcho->hasHeader(name: 'Authorization'));
        self::assertFalse($getEcho->hasHeader(name: 'X-First'));
        self::assertFalse($getEcho->hasHeader(name: 'Content-Type'));
    }

    public function testClientsAreIndependentOfEachOther(): void
    {
        $firstClient = new CurlClient();
        $secondClient = new CurlClient();
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo?n=1'));

        $firstResponse = $firstClient->send(request: $request);
        unset($firstClient);
        $secondResponse = $secondClient->send(request: $request);

        self::assertFalse($firstResponse->hasErrors());
        self::assertFalse($secondResponse->hasErrors());
    }

    public function testExecuteWithAGivenClient(): void
    {
        $client = new CurlClient();

        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'))->execute(
            curlClient: $client,
        );

        self::assertFalse($response->hasErrors());
    }

    public function testResponseHeaders(): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/headers'))->execute();

        self::assertSame('with headers', $response->rawResponseBody);
        self::assertSame('one', $response->getHeader(name: 'x-single'));
        self::assertSame(['a', 'b'], $response->getHeaderValues(name: 'X-Multi'));
        self::assertSame('', $response->getHeader(name: 'X-Empty'));
        self::assertNull($response->getHeader(name: 'X-Missing'));
    }

    public function testHeadRequestHasHeadersAndNoBody(): void
    {
        $response = CurlHeadRequest::create(requestTargetUrl: $this->server->url('/headers'))->execute();

        self::assertFalse($response->hasErrors());
        self::assertSame('', $response->rawResponseBody);
        self::assertSame('one', $response->getHeader(name: 'X-Single'));
    }

    public function testRedirectIsNotFollowedAndTheTargetIsInTheLocationHeader(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/redirect?code=301'));
        $request->useTokenAuthentication(token: 'secret-token');
        $request->acceptRedirectionResponseCode();

        $response = $request->execute();

        self::assertFalse($response->hasErrors());
        self::assertSame(HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY, $response->responseHttpCode);
        self::assertSame('http://127.0.0.2:1/echo', $response->getHeader(name: 'Location'));
        self::assertSame(0, $response->curlInfo['redirect_count']);
    }

    /**
     * @return iterable<string, array{0: int}>
     */
    public static function unknownStatusProvider(): iterable
    {
        return [
            'status 418' => [418],
            'status 429' => [429],
            'status 599' => [599],
        ];
    }

    #[DataProvider('unknownStatusProvider')]
    public function testStatusCodeThatIsNotInTheEnumIsStillAnError(int $statusCode): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/status/' . $statusCode))->execute();

        self::assertTrue($response->hasErrors());
        self::assertSame(CurlResponse::ERROR_BAD_HTTP_RESPONSE_CODE, $response->errorCode);
        self::assertSame(HttpStatusCodeEnum::HTTP_UNKNOWN, $response->responseHttpCode);
        self::assertSame($statusCode, $response->curlInfo['http_code']);
        self::assertSame('status ' . $statusCode, $response->rawResponseBody);
    }

    public function testResponseAtTheLimitIsAccepted(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/big?bytes=1000'));
        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: 1000);

        $response = $request->execute();

        self::assertFalse($response->hasErrors());
        self::assertSame(1000, strlen(string: (string) $response->rawResponseBody));
    }

    public function testResponseWithContentLengthBeyondTheLimitIsRefused(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/big?bytes=1001'));
        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: 1000);

        $response = $request->execute();

        self::assertTrue($response->hasErrors());
        self::assertSame(CurlResponse::ERROR_RESPONSE_TOO_LARGE, $response->errorCode);
        self::assertFalse($response->rawResponseBody);
        self::assertSame(
            CurlResponse::class . ': The response is larger than 1000 bytes.',
            $response->errorMessage,
        );
    }

    public function testStreamedResponseBeyondTheLimitIsAborted(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/big?bytes=5000&chunked=1'));
        $request->setMaxResponseSizeInBytes(maxResponseSizeInBytes: 1200);

        $response = $request->execute();

        self::assertTrue($response->hasErrors());
        self::assertSame(CurlResponse::ERROR_RESPONSE_TOO_LARGE, $response->errorCode);
        self::assertFalse($response->rawResponseBody);
    }

    public function testNoCredentialsAreSentWithoutAuthentication(): void
    {
        $echo = EchoedRequest::fromResponse(
            response: CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'))->execute(),
        );

        self::assertFalse($echo->hasHeader(name: 'Authorization'));
    }

    public function testCertificateOfTheServerIsVerified(): void
    {
        $tlsServer = new LocalTlsServer();
        $request = CurlGetRequest::create(requestTargetUrl: $tlsServer->url());
        $request->setTimeoutInSeconds(connectTimeOut: 5, requestTimeOut: 5);

        $response = $request->execute();

        self::assertTrue($response->hasErrors());
        self::assertSame(CURLE_SSL_PEER_CERTIFICATE, $response->errorCode);
        self::assertFalse($response->rawResponseBody);
        self::assertStringContainsString('always verified', $response->errorMessage);
    }
}
