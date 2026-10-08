<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\api;

use actra\yuf\api\CurlResponse;
use LogicException;

/**
 * What `echo-server.php` received: the request as the server saw it.
 */
final readonly class EchoedRequest
{
    /**
     * @param array<string, string> $headers
     */
    private function __construct(
        public string $method,
        public string $uri,
        public array $headers,
        public string $body,
    ) {}

    public static function fromResponse(CurlResponse $response): EchoedRequest
    {
        if ($response->rawResponseBody === false) {
            throw new LogicException(message: 'The request failed: ' . $response->errorMessage);
        }

        return EchoedRequest::fromJson(json: $response->rawResponseBody);
    }

    /**
     * @param string $json The JSON object of an echoed request
     */
    public static function fromJson(string $json): EchoedRequest
    {
        $decoded = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        if (
            !is_array(value: $decoded)
            || !array_key_exists(key: 'method', array: $decoded)
            || !array_key_exists(key: 'uri', array: $decoded)
            || !array_key_exists(key: 'body', array: $decoded)
            || !array_key_exists(key: 'headers', array: $decoded)
            || !is_string(value: $decoded['method'])
            || !is_string(value: $decoded['uri'])
            || !is_string(value: $decoded['body'])
            || !is_array(value: $decoded['headers'])
        ) {
            throw new LogicException(message: 'The response is no echo of a request.');
        }
        $headers = [];
        foreach ($decoded['headers'] as $name => $value) {
            if (is_string(value: $value)) {
                $headers[(string) $name] = $value;
            }
        }

        return new EchoedRequest(
            method: $decoded['method'],
            uri: $decoded['uri'],
            headers: $headers,
            body: $decoded['body'],
        );
    }

    public function hasHeader(string $name): bool
    {
        return array_key_exists(key: $name, array: $this->headers);
    }

    public function header(string $name): string
    {
        foreach ($this->headers as $headerName => $value) {
            if ($headerName === $name) {
                return $value;
            }
        }

        throw new LogicException(message: 'The server did not receive the header ' . $name);
    }
}
