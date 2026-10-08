<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use actra\yuf\common\JsonUtils;
use actra\yuf\common\SimpleXmlExtended;
use actra\yuf\core\HttpStatusCodeEnum;
use JsonException;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

/**
 * The result of one request. `$rawResponseBody` is `false` if the transfer failed (no response, timeout, response too
 * large); `$errorCode` / `$errorMessage` then say why. An HTTP status code of 300 or more is an error too
 * (`ERROR_BAD_HTTP_RESPONSE_CODE`), but the body is available.
 */
final readonly class CurlResponse
{
    public const int ERROR_BAD_HTTP_RESPONSE_CODE = 900;
    public const int ERROR_RESPONSE_TOO_LARGE = 901;

    /**
     * @param array{url: string, http_code: int, total_time: float, redirect_count: int, ...<string, mixed>} $curlInfo
     *     The result of `curl_getinfo()`
     * @param array<string, list<string>> $headers The values of the response headers by lower case name
     */
    public function __construct(
        public false|string $rawResponseBody,
        public array $curlInfo,
        public HttpStatusCodeEnum $responseHttpCode,
        public float $totalRequestTime,
        public int $errorCode,
        public string $errorMessage,
        public array $headers = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errorCode !== CURLE_OK;
    }

    /**
     * @return list<string> All values of a response header (name in any case), empty if it was not sent
     */
    public function getHeaderValues(string $name): array
    {
        $lowerCaseName = strtolower(string: $name);

        return array_key_exists(key: $lowerCaseName, array: $this->headers) ? $this->headers[$lowerCaseName] : [];
    }

    /**
     * @return ?string The first value of a response header (name in any case), `null` if it was not sent
     */
    public function getHeader(string $name): ?string
    {
        $values = $this->getHeaderValues(name: $name);

        return $values === [] ? null : $values[0];
    }

    /**
     * @return stdClass|array<array-key, mixed>
     *
     * @throws RuntimeException If the request failed and there is no body
     * @throws JsonException If the body is no valid JSON
     * @throws UnexpectedValueException If the JSON is no object and no array
     */
    public function getJsonResponse(): stdClass|array
    {
        return JsonUtils::decodeJsonString(
            jsonString: $this->requireBody(),
            returnAssociativeArray: false,
        );
    }

    /**
     * @throws RuntimeException If the request failed and there is no body
     */
    public function getXmlResponse(): SimpleXmlExtended
    {
        // LIBXML_NONET: the document must not load anything from the network; entities are not substituted
        return new SimpleXmlExtended(
            data: $this->requireBody(),
            options: LIBXML_NOCDATA | LIBXML_NONET,
        );
    }

    private function requireBody(): string
    {
        if ($this->rawResponseBody === false) {
            throw new RuntimeException(message: 'The request failed, there is no response body to read.');
        }

        return $this->rawResponseBody;
    }
}
