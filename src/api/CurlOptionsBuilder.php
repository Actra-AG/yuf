<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use actra\yuf\core\RequestMethodEnum;
use CurlHandle;

/**
 * Translates a request into cURL options. The security settings are set here and nowhere else: only http and https
 * (also for redirects, which are never followed), the certificate and the host name of the server are verified, TLS
 * 1.2 or newer, the transfer has timeouts and a size limit.
 *
 * @internal
 */
final class CurlOptionsBuilder
{
    private const string ALLOWED_PROTOCOLS = 'http,https';

    /**
     * @return array<int, bool|int|string|list<string>|callable(CurlHandle, string): int>
     */
    public static function build(AbstractCurlRequest $request, CurlResponseCollector $collector): array
    {
        $options = [
            CURLOPT_URL => $request->getUrl(),
            CURLOPT_PROTOCOLS_STR => CurlOptionsBuilder::ALLOWED_PROTOCOLS,
            CURLOPT_REDIR_PROTOCOLS_STR => CurlOptionsBuilder::ALLOWED_PROTOCOLS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_CONNECTTIMEOUT => $request->getConnectTimeoutInSeconds(),
            CURLOPT_TIMEOUT => $request->getRequestTimeoutInSeconds(),
            CURLOPT_MAXFILESIZE => $request->getMaxResponseSizeInBytes(),
            CURLOPT_HEADERFUNCTION => static fn(CurlHandle $handle, string $line): int => $collector->appendHeaderLine(
                line: $line,
            ),
            CURLOPT_WRITEFUNCTION => static fn(CurlHandle $handle, string $chunk): int => $collector->appendBody(
                chunk: $chunk,
            ),
            CURLOPT_HTTPHEADER => CurlOptionsBuilder::buildHeaderLines(request: $request),
        ];
        foreach (CurlOptionsBuilder::buildMethodOptions(method: $request->getMethod()) as $option => $value) {
            $options[$option] = $value;
        }
        $body = $request->getBody();
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        $authentication = $request->getAuthentication();
        if ($authentication?->method === CurlAuthenticationMethodEnum::BASIC) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_USERPWD] = $authentication->getSecret();
        }
        if ($authentication?->method === CurlAuthenticationMethodEnum::BEARER) {
            $options[CURLOPT_HTTPAUTH] = CURLAUTH_BEARER;
            $options[CURLOPT_XOAUTH2_BEARER] = $authentication->getSecret();
        }

        return $options;
    }

    /**
     * @return array<int, bool|string>
     */
    private static function buildMethodOptions(RequestMethodEnum $method): array
    {
        return match ($method) {
            RequestMethodEnum::GET => [CURLOPT_HTTPGET => true],
            RequestMethodEnum::POST => [CURLOPT_POST => true],
            RequestMethodEnum::HEAD => [CURLOPT_NOBODY => true],
            RequestMethodEnum::PUT,
            RequestMethodEnum::PATCH,
            RequestMethodEnum::DELETE,
            RequestMethodEnum::OPTIONS => [CURLOPT_CUSTOMREQUEST => $method->value],
        };
    }

    /**
     * @return list<string>
     */
    private static function buildHeaderLines(AbstractCurlRequest $request): array
    {
        $lines = [];
        foreach ($request->getHttpHeaders() as $header) {
            $lines[] = $header->toLine();
        }

        return $lines;
    }
}
