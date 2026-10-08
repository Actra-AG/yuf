<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\core\RequestMethodEnum;

/**
 * Builds a request for a test without superglobals: `https://example.com/` unless the test says otherwise.
 */
final class HttpRequestFactory
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<array-key, mixed> $queryParameters
     * @param array<array-key, mixed> $postParameters
     * @param array<array-key, mixed> $uploadedFiles
     * @param array<string, string> $serverVariables
     */
    public static function create(
        string $host = 'example.com',
        RequestMethodEnum $method = RequestMethodEnum::GET,
        string $uri = '/',
        string $queryString = '',
        ProtocolEnum $protocol = ProtocolEnum::HTTPS,
        int $port = 443,
        string $serverName = 'example.com',
        string $serverAddress = '192.0.2.10',
        string $remoteAddress = '192.0.2.1',
        array $headers = [],
        array $cookies = [],
        array $queryParameters = [],
        array $postParameters = [],
        array $uploadedFiles = [],
        string $body = '',
        array $serverVariables = [],
    ): HttpRequest {
        return new HttpRequest(
            host: $host,
            method: $method,
            uri: $uri,
            queryString: $queryString,
            protocol: $protocol,
            port: $port,
            serverName: $serverName,
            serverAddress: $serverAddress,
            remoteAddress: $remoteAddress,
            headers: $headers,
            cookies: $cookies,
            queryParameters: $queryParameters,
            postParameters: $postParameters,
            uploadedFiles: $uploadedFiles,
            body: $body,
            serverVariables: $serverVariables,
        );
    }
}
