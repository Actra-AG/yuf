<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlResponse;
use actra\yuf\api\request\CurlDeleteRequest;
use actra\yuf\api\request\CurlGetRequest;
use actra\yuf\api\request\CurlHeadRequest;
use actra\yuf\api\request\CurlPatchRequest;
use actra\yuf\api\request\CurlPostRequest;
use actra\yuf\api\request\CurlPutRequest;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\tests\Double\api\EchoedRequest;
use actra\yuf\tests\Double\api\LocalHttpServer;
use JsonException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * What is sent over the wire and what the response says, against a web server on the loopback address.
 */
final class CurlRequestCharacterizationTest extends TestCase
{
    private LocalHttpServer $server;

    #[Override]
    protected function setUp(): void
    {
        $this->server = new LocalHttpServer();
    }

    public function testGetRequestSendsMethodAndQueryString(): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo?a=1&b=two'))->execute();

        $echo = EchoedRequest::fromResponse(response: $response);
        $this->assertFalse($response->hasErrors());
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $response->responseHttpCode);
        $this->assertSame('GET', $echo->method);
        $this->assertSame('/echo?a=1&b=two', $echo->uri);
        $this->assertSame('', $echo->body);
    }

    public function testDeleteRequestSendsMethodWithoutBody(): void
    {
        $response = CurlDeleteRequest::create(requestTargetUrl: $this->server->url('/echo'))->execute();

        $echo = EchoedRequest::fromResponse(response: $response);
        $this->assertSame('DELETE', $echo->method);
        $this->assertSame('', $echo->body);
    }

    public function testPatchRequestWithoutBody(): void
    {
        $response = CurlPatchRequest::createWithoutBody(requestTargetUrl: $this->server->url('/echo'))->execute();

        $echo = EchoedRequest::fromResponse(response: $response);
        $this->assertSame('PATCH', $echo->method);
        $this->assertSame('', $echo->body);
    }

    public function testPostFieldsAreFormEncodedWithRfc3986(): void
    {
        $request = CurlPostRequest::createWithPostBody(
            requestTargetUrl: $this->server->url('/echo'),
            postData: [
                'name' => 'Anna Muster',
                'flag' => true,
                'off' => false,
                'nothing' => null,
                'number' => 12,
                'price' => 1.5,
                'list' => ['a b', 'ä&='],
                'nested' => ['key' => ['deep' => 'x']],
            ],
        );

        $echo = EchoedRequest::fromResponse(response: $request->execute());

        $this->assertSame('POST', $echo->method);
        $this->assertSame(
            'name=Anna%20Muster&flag=1&off=0&nothing=&number=12&price=1.5&list%5B0%5D=a%20b'
            . '&list%5B1%5D=%C3%A4%26%3D&nested%5Bkey%5D%5Bdeep%5D=x',
            $echo->body,
        );
        $this->assertSame('application/x-www-form-urlencoded; charset=utf-8', $echo->header(name: 'Content-Type'));
    }

    public function testPostFieldsOfObjectsUseTheirPublicProperties(): void
    {
        $object = new class {
            public string $title = 'T';
            public bool $active = true;
            private string $secret = 'hidden';

            public function secret(): string
            {
                return $this->secret;
            }
        };

        $echo = EchoedRequest::fromResponse(
            response: CurlPostRequest::createWithPostBody(
                requestTargetUrl: $this->server->url('/echo'),
                postData: ['item' => $object],
            )->execute(),
        );

        $this->assertSame('item%5Btitle%5D=T&item%5Bactive%5D=1', $echo->body);
    }

    #[DataProvider('bodyRequestProvider')]
    public function testBodyIsSentWithContentTypeAndMethod(
        string $method,
        string $kind,
        string $content,
        string $expectedContentType,
    ): void {
        $url = $this->server->url('/echo');
        $request = match ($method . ' ' . $kind) {
            'POST xml' => CurlPostRequest::createWithXmlBody(requestTargetUrl: $url, xmlString: $content),
            'POST json' => CurlPostRequest::createWithJsonBody(requestTargetUrl: $url, jsonString: $content),
            'POST jsonapi' => CurlPostRequest::createJsonApiRequest(requestTargetUrl: $url, jsonString: $content),
            'POST text' => CurlPostRequest::createWithPlainTextBody(requestTargetUrl: $url, plainText: $content),
            'PUT xml' => CurlPutRequest::createWithXmlBody(requestTargetUrl: $url, xmlString: $content),
            'PUT json' => CurlPutRequest::createWithJsonBody(requestTargetUrl: $url, jsonString: $content),
            'PUT jsonapi' => CurlPutRequest::createJsonApiRequest(requestTargetUrl: $url, jsonString: $content),
            'PUT text' => CurlPutRequest::createWithPlainTextBody(requestTargetUrl: $url, plainText: $content),
            'PATCH xml' => CurlPatchRequest::createWithXmlBody(requestTargetUrl: $url, xmlString: $content),
            'PATCH json' => CurlPatchRequest::createWithJsonBody(requestTargetUrl: $url, jsonString: $content),
            'PATCH jsonapi' => CurlPatchRequest::createJsonApiRequest(requestTargetUrl: $url, jsonString: $content),
            default => CurlPatchRequest::createWithPlainTextBody(requestTargetUrl: $url, plainText: $content),
        };

        $echo = EchoedRequest::fromResponse(response: $request->execute());

        $this->assertSame($method, $echo->method);
        $this->assertSame($content, $echo->body);
        $this->assertSame($expectedContentType, $echo->header(name: 'Content-Type'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function bodyRequestProvider(): iterable
    {
        foreach (['POST', 'PUT', 'PATCH'] as $method) {
            yield $method . ' xml' => [$method, 'xml', '<a>ä</a>', 'text/xml; charset=utf-8'];
            yield $method . ' json' => [$method, 'json', '{"a":"ä"}', 'application/json; charset=utf-8'];
            yield $method . ' jsonapi' => [$method, 'jsonapi', '{"data":[]}', 'application/vnd.api+json'];
            yield $method . ' text' => [$method, 'text', "line 1\nline ä", 'text/plain; charset=utf-8'];
        }
    }

    public function testXmlBodyAddsPrettyPrintHeader(): void
    {
        $echo = EchoedRequest::fromResponse(
            response: CurlPostRequest::createWithXmlBody(
                requestTargetUrl: $this->server->url('/echo'),
                xmlString: '<a/>',
            )->execute(),
        );

        $this->assertSame('TRUE', $echo->header(name: 'HTTP_PRETTY_PRINT'));
    }

    public function testJsonApiBodyAcceptsJsonApi(): void
    {
        $echo = EchoedRequest::fromResponse(
            response: CurlPostRequest::createJsonApiRequest(
                requestTargetUrl: $this->server->url('/echo'),
                jsonString: '{}',
            )->execute(),
        );

        $this->assertSame('application/vnd.api+json', $echo->header(name: 'Accept'));
    }

    public function testCustomHeadersAreSent(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));
        $request->setHttpHeader(key: 'X-Api-Key', value: 'k-123');
        $request->setHttpHeader(key: 'Accept', value: 'application/json');

        $echo = EchoedRequest::fromResponse(response: $request->execute());

        $this->assertSame('k-123', $echo->header(name: 'X-Api-Key'));
        $this->assertSame('application/json', $echo->header(name: 'Accept'));
    }

    public function testLastValueOfAHeaderWins(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));
        $request->setHttpHeader(key: 'X-Api-Key', value: 'first');
        $request->setHttpHeader(key: 'X-Api-Key', value: 'second');

        $this->assertSame(
            'second',
            EchoedRequest::fromResponse(response: $request->execute())->header(name: 'X-Api-Key'),
        );
    }

    public function testBearerTokenAuthentication(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));
        $request->useTokenAuthentication(token: 'token-value');

        $this->assertSame(
            'Bearer token-value',
            EchoedRequest::fromResponse(response: $request->execute())->header(name: 'Authorization'),
        );
    }

    public function testBasicAuthentication(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));
        $request->useBasicHttpAuthentication(authUserNamePassword: 'user:pass word');

        $this->assertSame(
            'Basic ' . base64_encode(string: 'user:pass word'),
            EchoedRequest::fromResponse(response: $request->execute())->header(name: 'Authorization'),
        );
    }

    public function testContentTypeCannotBeSetAsHeader(): void
    {
        $request = CurlPostRequest::createWithJsonBody(
            requestTargetUrl: $this->server->url('/echo'),
            jsonString: '{}',
        );

        $this->expectException(LogicException::class);

        $request->setHttpHeader(key: 'Content-Type', value: 'text/html');
    }

    public function testContentLengthCannotBeSetAsHeader(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));

        $this->expectException(LogicException::class);

        $request->setHttpHeader(key: 'Content-Length', value: '1');
    }

    public function testConnectTimeoutCannotBeLongerThanRequestTimeout(): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));

        $this->expectException(LogicException::class);

        $request->setTimeoutInSeconds(connectTimeOut: 5, requestTimeOut: 2);
    }

    public function testResponseOfSuccessfulRequest(): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/json'))->execute();

        $this->assertFalse($response->hasErrors());
        $this->assertSame(0, $response->errorCode);
        $this->assertSame('', $response->errorMessage);
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $response->responseHttpCode);
        $this->assertSame('{"name":"yuf","list":[1,2],"nested":{"ok":true}}', $response->rawResponseBody);
        $this->assertSame(200, $response->curlInfo['http_code']);
        $this->assertGreaterThan(0.0, $response->totalRequestTime);
    }

    public function testJsonResponseIsDecodedToObject(): void
    {
        $json = CurlGetRequest::create(requestTargetUrl: $this->server->url('/json'))->execute()->getJsonResponse();

        $this->assertInstanceOf(stdClass::class, $json);
        $this->assertSame('yuf', $json->name);
        $this->assertSame([1, 2], $json->list);
        $this->assertInstanceOf(stdClass::class, $json->nested);
        $this->assertTrue($json->nested->ok);
    }

    public function testJsonResponseOfListIsArray(): void
    {
        $json = CurlGetRequest::create(requestTargetUrl: $this->server->url('/json-list'))
            ->execute()
            ->getJsonResponse();

        $this->assertSame([1, 2, 3], $json);
    }

    public function testInvalidJsonResponseThrows(): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/bad-json'))->execute();

        $this->expectException(JsonException::class);

        $response->getJsonResponse();
    }

    public function testXmlResponseIsParsedWithCdata(): void
    {
        $xml = CurlGetRequest::create(requestTargetUrl: $this->server->url('/xml'))->execute()->getXmlResponse();

        $this->assertSame('a & b', (string) $xml->item);
    }

    public function testNoContentResponse(): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/empty'))->execute();

        $this->assertFalse($response->hasErrors());
        $this->assertSame(HttpStatusCodeEnum::HTTP_NO_CONTENT, $response->responseHttpCode);
        $this->assertSame('', $response->rawResponseBody);
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function errorStatusProvider(): iterable
    {
        return [
            'status 400' => [400, ''],
            'status 401' => [401, ' ("unauthorized". Check credentials or request format.)'],
            'status 404' => [404, ' ("not found" on server)'],
            'status 405' => [405, ' ("method not allowed". Check URL or request format/data.)'],
            'status 406' => [406, ' ("not acceptable" on server. Check request format/data.)'],
            'status 500' => [500, ' (remote "Server error")'],
            'status 503' => [503, ''],
            'status 301' => [301, ' ("moved permanently". Check URL/settings.)'],
            'status 303' => [303, ' ("Redirect". Maybe HTTP-to-HTTPS? Check URL/settings.)'],
            'status 302' => [302, ' ("found", temporary redirect. Check URL/settings.)'],
            'status 307' => [307, ' ("temporary redirect". Check URL/settings.)'],
            'status 308' => [308, ' ("permanent redirect". Check URL/settings.)'],
        ];
    }

    #[DataProvider('errorStatusProvider')]
    public function testBadStatusCodeIsAnError(int $statusCode, string $hint): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/status/' . $statusCode))->execute();

        $this->assertTrue($response->hasErrors());
        $this->assertSame(CurlResponse::ERROR_BAD_HTTP_RESPONSE_CODE, $response->errorCode);
        $this->assertSame(
            CurlResponse::class . ': Bad HTTP response code received: ' . $statusCode . $hint,
            $response->errorMessage,
        );
        $this->assertSame($statusCode, $response->curlInfo['http_code']);
        $this->assertSame('status ' . $statusCode, $response->rawResponseBody);
    }

    public function testRedirectIsNotFollowed(): void
    {
        $response = CurlGetRequest::create(requestTargetUrl: $this->server->url('/redirect?code=302'))->execute();

        $this->assertSame(HttpStatusCodeEnum::HTTP_FOUND, $response->responseHttpCode);
        $this->assertSame(0, $response->curlInfo['redirect_count']);
        $this->assertTrue($response->hasErrors());
    }

    /**
     * @return iterable<string, array{0: int, 1: bool}>
     */
    public static function acceptedRedirectProvider(): iterable
    {
        return [
            '301 accepted' => [301, false],
            '303 accepted' => [303, false],
            '302 accepted' => [302, false],
            '307 accepted' => [307, false],
            '308 accepted' => [308, false],
            '300 still an error' => [300, true],
            '304 still an error' => [304, true],
        ];
    }

    #[DataProvider('acceptedRedirectProvider')]
    public function testAcceptedRedirectionCodesAreNoErrors(int $statusCode, bool $hasErrors): void
    {
        $request = CurlGetRequest::create(requestTargetUrl: $this->server->url('/redirect?code=' . $statusCode));
        $request->acceptRedirectionResponseCode();

        $this->assertSame($hasErrors, $request->execute()->hasErrors());
    }

    public function testConnectionRefusedIsACurlError(): void
    {
        $port = $this->server->port;
        unset($this->server);
        $request = CurlGetRequest::create(requestTargetUrl: 'http://127.0.0.1:' . $port . '/echo');
        $request->setTimeoutInSeconds(connectTimeOut: 2, requestTimeOut: 2);

        $response = $request->execute();

        $this->assertTrue($response->hasErrors());
        $this->assertSame(CURLE_COULDNT_CONNECT, $response->errorCode);
        $this->assertStringStartsWith(CurlResponse::class . ': (7) ', $response->errorMessage);
        $this->assertFalse($response->rawResponseBody);
        $this->assertSame(HttpStatusCodeEnum::HTTP_UNKNOWN, $response->responseHttpCode);
    }

    public function testRequestTimeoutIsACurlError(): void
    {
        $silentServer = stream_socket_server(address: 'tcp://127.0.0.1:0');
        $this->assertNotFalse($silentServer);
        $name = (string) stream_socket_get_name(socket: $silentServer, remote: false);
        $request = CurlGetRequest::create(requestTargetUrl: 'http://' . $name . '/echo');
        $request->setTimeoutInSeconds(connectTimeOut: 1, requestTimeOut: 1);

        $response = $request->execute();
        fclose(stream: $silentServer);

        $this->assertTrue($response->hasErrors());
        $this->assertSame(CURLE_OPERATION_TIMEDOUT, $response->errorCode);
        $this->assertStringStartsWith(CurlResponse::class . ': (28) ', $response->errorMessage);
        $this->assertFalse($response->rawResponseBody);
    }

    public function testSeveralRequestsAtTheSameTimeAreIndependent(): void
    {
        $first = CurlPostRequest::createWithJsonBody(
            requestTargetUrl: $this->server->url('/echo'),
            jsonString: '{"n":1}',
        );
        $second = CurlGetRequest::create(requestTargetUrl: $this->server->url('/echo'));

        $firstEcho = EchoedRequest::fromResponse(response: $first->execute());
        $secondEcho = EchoedRequest::fromResponse(response: $second->execute());

        $this->assertSame('POST', $firstEcho->method);
        $this->assertSame('{"n":1}', $firstEcho->body);
        $this->assertSame('GET', $secondEcho->method);
        $this->assertSame('', $secondEcho->body);
        $this->assertFalse($secondEcho->hasHeader(name: 'Content-Type'));
    }

    public function testHeadRequestSendsHeadAndHasNoBody(): void
    {
        $response = CurlHeadRequest::create(requestTargetUrl: $this->server->url('/echo'))->execute();

        $this->assertFalse($response->hasErrors());
        $this->assertSame(HttpStatusCodeEnum::HTTP_OK, $response->responseHttpCode);
    }
}
