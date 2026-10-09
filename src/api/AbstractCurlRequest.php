<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use actra\yuf\core\RequestMethodEnum;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

/**
 * Base of the request classes (`CurlGetRequest`, `CurlPostRequest`, ...): what is sent, not how. A request is sent by
 * a `CurlClient`; `execute()` does this with a client of its own. Not an extension point for projects, use one of the
 * request classes.
 *
 * Safe by default: the certificate and host name of the server are always verified, only http and https URLs
 * are accepted, redirects are not followed, credentials are only sent over HTTPS (or to this machine), the transfer
 * has timeouts and a limit for the size of the response.
 */
abstract class AbstractCurlRequest
{
    /** The default request timeout (the whole transfer) */
    public const int DEFAULT_TIMEOUT_IN_SECONDS = 10;
    /** The default connect timeout: an unreachable server fails fast instead of blocking the request */
    public const int DEFAULT_CONNECT_TIMEOUT_IN_SECONDS = 3;
    public const int DEFAULT_MAX_RESPONSE_SIZE_IN_BYTES = 33554432;
    private const string CONTENT_TYPE = 'Content-Type';
    private const string CONTENT_LENGTH = 'Content-Length';

    /** @var array<string, CurlHeader> by lower case name */
    private array $httpHeaders = [];
    private ?string $body = null;
    private ?CurlBodyTypeEnum $bodyType = null;
    private ?CurlAuthentication $authentication = null;
    private int $connectTimeoutInSeconds = AbstractCurlRequest::DEFAULT_CONNECT_TIMEOUT_IN_SECONDS;
    private int $requestTimeoutInSeconds = AbstractCurlRequest::DEFAULT_TIMEOUT_IN_SECONDS;
    private int $maxResponseSizeInBytes = AbstractCurlRequest::DEFAULT_MAX_RESPONSE_SIZE_IN_BYTES;
    private bool $acceptRedirectionResponseCode = false;
    private readonly CurlTargetUrl $targetUrl;

    /**
     * @throws InvalidArgumentException If the URL is no absolute http or https URL or contains a user name or password
     */
    protected function __construct(
        private readonly RequestMethodEnum $method,
        string $requestTargetUrl,
    ) {
        $this->targetUrl = new CurlTargetUrl(url: $requestTargetUrl);
    }

    /**
     * @throws InvalidArgumentException If a timeout is less than 1 second (cURL would wait forever for 0)
     * @throws LogicException If the connect timeout is longer than the request timeout
     */
    public function setTimeoutInSeconds(int $connectTimeOut, int $requestTimeOut): void
    {
        if ($connectTimeOut < 1 || $requestTimeOut < 1) {
            throw new InvalidArgumentException(message: 'A timeout must be at least 1 second.');
        }
        if ($connectTimeOut > $requestTimeOut) {
            throw new LogicException(message: 'Connect timeout cannot be more than request timeout.');
        }
        $this->connectTimeoutInSeconds = $connectTimeOut;
        $this->requestTimeoutInSeconds = $requestTimeOut;
    }

    /**
     * A response with a larger body is aborted and reported as `CurlResponse::ERROR_RESPONSE_TOO_LARGE`.
     */
    public function setMaxResponseSizeInBytes(int $maxResponseSizeInBytes): void
    {
        if ($maxResponseSizeInBytes < 1) {
            throw new InvalidArgumentException(message: 'The maximum response size must be at least 1 byte.');
        }
        $this->maxResponseSizeInBytes = $maxResponseSizeInBytes;
    }

    /**
     * Sets a header, a header of the same name (in any case) is replaced.
     *
     * @throws LogicException For `Content-Type` and `Content-Length`: they come from the body
     * @throws InvalidArgumentException If the name is no valid header name or the value has line breaks or other
     *                                  control characters (header injection)
     */
    public function setHttpHeader(string $key, #[SensitiveParameter] string $value): void
    {
        if (
            strcasecmp(string1: $key, string2: AbstractCurlRequest::CONTENT_TYPE) === 0
            || strcasecmp(string1: $key, string2: AbstractCurlRequest::CONTENT_LENGTH) === 0
        ) {
            throw new LogicException(message: 'You are not allowed to overwrite the HTTP-Header ' . $key);
        }
        $this->putHeader(header: new CurlHeader(name: $key, value: $value));
    }

    /**
     * Redirects are never followed. By default, every status code of 300 or more is an error; this accepts 301, 302,
     * 303, 307 and 308 as an answer (the target is in the `Location` header of the response). Other 3xx codes
     * (e.g. 300 and 304) stay errors.
     */
    public function acceptRedirectionResponseCode(): void
    {
        $this->acceptRedirectionResponseCode = true;
    }

    /**
     * @param string $authUserNamePassword `user:password`
     *
     * @throws LogicException If the target is not HTTPS (plain HTTP is only accepted for localhost)
     */
    public function useBasicHttpAuthentication(#[SensitiveParameter] string $authUserNamePassword): void
    {
        $this->assertCredentialsAreSafeToSend();
        $this->authentication = CurlAuthentication::basic(userNameAndPassword: $authUserNamePassword);
    }

    /**
     * @throws LogicException If the target is not HTTPS (plain HTTP is only accepted for localhost)
     * @throws InvalidArgumentException If the token has spaces, line breaks or other characters that are not visible
     *                                  ASCII
     */
    public function useTokenAuthentication(#[SensitiveParameter] string $token): void
    {
        $this->assertCredentialsAreSafeToSend();
        $this->authentication = CurlAuthentication::bearer(token: $token);
    }

    /**
     * Sends the request with a new client (and a new connection). To send several requests over the same connection
     * use `CurlClient::send()`.
     */
    public function execute(?CurlClient $curlClient = null): CurlResponse
    {
        return ($curlClient ?? new CurlClient())->send(request: $this);
    }

    public function getMethod(): RequestMethodEnum
    {
        return $this->method;
    }

    public function getUrl(): string
    {
        return $this->targetUrl->url;
    }

    /**
     * @return list<CurlHeader> The headers that are sent, including the `Content-Type` of the body
     */
    public function getHttpHeaders(): array
    {
        $headers = array_values(array: $this->httpHeaders);
        if ($this->bodyType !== null) {
            $headers[] = new CurlHeader(
                name: AbstractCurlRequest::CONTENT_TYPE,
                value: $this->bodyType->getContentType(),
            );
        }

        return $headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getConnectTimeoutInSeconds(): int
    {
        return $this->connectTimeoutInSeconds;
    }

    public function getRequestTimeoutInSeconds(): int
    {
        return $this->requestTimeoutInSeconds;
    }

    public function getMaxResponseSizeInBytes(): int
    {
        return $this->maxResponseSizeInBytes;
    }

    public function isRedirectionResponseCodeAccepted(): bool
    {
        return $this->acceptRedirectionResponseCode;
    }

    /**
     * @internal
     */
    public function getAuthentication(): ?CurlAuthentication
    {
        return $this->authentication;
    }

    /**
     * Never shows the secrets (headers can carry API keys; the target URL a token).
     *
     * @return array{method: string, host: string, hasBody: bool, hasAuthentication: bool}
     */
    public function __debugInfo(): array
    {
        return [
            'method' => $this->method->value,
            'host' => $this->targetUrl->host,
            'hasBody' => $this->body !== null,
            'hasAuthentication' => $this->authentication !== null,
        ];
    }

    /**
     * @param array<array-key, mixed> $postData see `CurlFormEncoder`
     */
    protected function setPostBody(array $postData): void
    {
        $this->setBody(
            bodyType: CurlBodyTypeEnum::FORM_URLENCODED,
            content: CurlFormEncoder::encode(postData: $postData),
        );
    }

    protected function setXmlBody(string $xmlString): void
    {
        $this->setBody(bodyType: CurlBodyTypeEnum::XML, content: $xmlString);
    }

    protected function setJsonBody(string $jsonString): void
    {
        $this->setBody(bodyType: CurlBodyTypeEnum::JSON, content: $jsonString);
    }

    protected function setJsonApiBody(string $jsonString): void
    {
        $this->setBody(bodyType: CurlBodyTypeEnum::JSON_API, content: $jsonString);
    }

    protected function setPlainTextBody(string $plainText): void
    {
        $this->setBody(bodyType: CurlBodyTypeEnum::PLAIN_TEXT, content: $plainText);
    }

    private function setBody(CurlBodyTypeEnum $bodyType, string $content): void
    {
        $this->bodyType = $bodyType;
        $this->body = $content;
        foreach ($bodyType->getDefaultHeaders() as $name => $value) {
            $this->putHeader(header: new CurlHeader(name: $name, value: $value));
        }
    }

    private function putHeader(CurlHeader $header): void
    {
        $this->httpHeaders[strtolower(string: $header->name)] = $header;
    }

    private function assertCredentialsAreSafeToSend(): void
    {
        if (!$this->targetUrl->isSafeForCredentials()) {
            throw new LogicException(
                message: 'Credentials are only sent over HTTPS (plain HTTP is only accepted for localhost).',
            );
        }
    }
}
