<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use actra\yuf\core\HttpStatusCodeEnum;
use CurlHandle;
use RuntimeException;

/**
 * Sends requests. A client keeps its cURL handle for its lifetime, so several requests to the same server through the
 * same client reuse the connection; options never carry over from one request to the next. A request pinned to IP
 * addresses (`AbstractCurlRequest::pinToIpAddresses()`) gets a handle of its own, so it shares no connection and no
 * DNS entry with the other requests. Not safe to use from several
 * threads at the same time (PHP has none).
 */
final class CurlClient
{
    private readonly CurlHandle $curlHandle;

    public function __construct()
    {
        $this->curlHandle = CurlClient::createHandle();
    }

    private static function createHandle(): CurlHandle
    {
        $curlHandle = curl_init();
        // curl_init() returns false when cURL cannot be initialized (PHP versions before 8 threw nothing)
        if ($curlHandle === false) {
            throw new RuntimeException(message: 'cURL could not be initialized.');
        }

        return $curlHandle;
    }

    /**
     * Sends the request. Failures of the transfer (no connection, timeout, bad certificate) and HTTP status codes of
     * 300 or more are not thrown but reported in the response (`hasErrors()`).
     *
     * @throws RuntimeException If cURL does not accept the options of the request
     */
    public function send(AbstractCurlRequest $request): CurlResponse
    {
        $collector = new CurlResponseCollector(maxBodyBytes: $request->getMaxResponseSizeInBytes());
        // CURLOPT_RESOLVE entries stay in the DNS cache of a handle (also after curl_reset())
        $curlHandle = $request->getPinnedIpAddresses() === [] ? $this->curlHandle : CurlClient::createHandle();
        curl_reset(handle: $curlHandle);
        $isConfigured = curl_setopt_array(
            handle: $curlHandle,
            options: CurlOptionsBuilder::build(request: $request, collector: $collector),
        );
        if (!$isConfigured) {
            throw new RuntimeException(message: 'cURL does not accept the options of the request.');
        }

        $transferSucceeded = curl_exec(handle: $curlHandle) !== false;
        $curlInfo = curl_getinfo(handle: $curlHandle);
        if ($curlInfo === false) {
            throw new RuntimeException(message: 'cURL does not report the result of the transfer.');
        }
        $statusCode = $curlInfo['http_code'];
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: curl_errno(handle: $curlHandle),
            curlErrorMessage: curl_error(handle: $curlHandle),
            statusCode: $statusCode,
            acceptRedirectionResponseCode: $request->isRedirectionResponseCodeAccepted(),
            isResponseLimitExceeded: $collector->isLimitExceeded(),
            maxResponseSizeInBytes: $request->getMaxResponseSizeInBytes(),
        );

        return new CurlResponse(
            rawResponseBody: $transferSucceeded ? $collector->getBody() : false,
            curlInfo: $curlInfo,
            responseHttpCode: HttpStatusCodeEnum::tryFrom(value: $statusCode) ?? HttpStatusCodeEnum::HTTP_UNKNOWN,
            totalRequestTime: $curlInfo['total_time'],
            errorCode: $error === null ? CURLE_OK : $error->code,
            errorMessage: $error === null ? '' : $error->message,
            headers: $collector->getHeaders(),
        );
    }
}
