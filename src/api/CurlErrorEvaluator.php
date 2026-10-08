<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use actra\yuf\core\HttpStatusCodeEnum;

/**
 * Decides whether a finished transfer is an error and describes it for the developer: a failure of cURL itself, a
 * response that is larger than the limit or an HTTP status code of 300 or more (redirects can be accepted).
 *
 * @internal
 */
final class CurlErrorEvaluator
{
    // Not defined by PHP
    private const int CURLE_LOGIN_DENIED = 67;

    public static function evaluate(
        int $curlErrorCode,
        string $curlErrorMessage,
        int $statusCode,
        bool $acceptRedirectionResponseCode,
        bool $isResponseLimitExceeded,
        int $maxResponseSizeInBytes,
    ): ?CurlResponseError {
        if (
            $curlErrorCode === CURLE_FILESIZE_EXCEEDED
            || ($curlErrorCode === CURLE_WRITE_ERROR && $isResponseLimitExceeded)
        ) {
            return new CurlResponseError(
                code: CurlResponse::ERROR_RESPONSE_TOO_LARGE,
                message: CurlResponse::class . ': The response is larger than ' . $maxResponseSizeInBytes . ' bytes.',
            );
        }
        if ($curlErrorCode !== CURLE_OK) {
            return new CurlResponseError(
                code: $curlErrorCode,
                message: CurlResponse::class . ': (' . $curlErrorCode . ') ' . $curlErrorMessage
                . CurlErrorEvaluator::describeCurlError(curlErrorCode: $curlErrorCode),
            );
        }
        if (!CurlErrorEvaluator::isBadStatusCode(
            statusCode: $statusCode,
            acceptRedirectionResponseCode: $acceptRedirectionResponseCode,
        )) {
            return null;
        }

        return new CurlResponseError(
            code: CurlResponse::ERROR_BAD_HTTP_RESPONSE_CODE,
            message: CurlResponse::class . ': Bad HTTP response code received: ' . $statusCode
            . CurlErrorEvaluator::describeStatusCode(statusCode: HttpStatusCodeEnum::tryFrom(value: $statusCode)),
        );
    }

    private static function isBadStatusCode(int $statusCode, bool $acceptRedirectionResponseCode): bool
    {
        if ($statusCode < 300 || $statusCode >= 600) {
            return false;
        }

        return !$acceptRedirectionResponseCode
            || ($statusCode !== HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY->value
                && $statusCode !== HttpStatusCodeEnum::HTTP_SEE_OTHER->value);
    }

    private static function describeCurlError(int $curlErrorCode): string
    {
        // See https://www.php.net/manual/en/function.curl-errno.php for further values of interest.
        return match ($curlErrorCode) {
            CURLE_FTP_ACCESS_DENIED => '; Hint: (Remote) Access denied.',
            CURLE_SSL_CONNECT_ERROR => '; Hint: Problem with ssl connection.',
            CURLE_HTTP_PORT_FAILED => '; Hint: Interface failed. Maybe problem with networking on server?',
            CURLE_GOT_NOTHING => '; Hint: Got no data.',
            CURLE_SSL_CERTPROBLEM => '; Hint: Problem with certificate on ssl connection.',
            CURLE_SSL_PEER_CERTIFICATE => '; Hint: Problem with CA certificate on ssl connection.'
            . ' Maybe OS update missing on server? The certificate and the host name of the server are always'
            . ' verified.',
            CURLE_UNSUPPORTED_PROTOCOL => '; Hint: Only http and https are supported.',
            CurlErrorEvaluator::CURLE_LOGIN_DENIED => '; Hint: Login denied.',
            default => '',
        };
    }

    private static function describeStatusCode(?HttpStatusCodeEnum $statusCode): string
    {
        return match ($statusCode) {
            HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY => ' ("moved permanently". Check URL/settings.)',
            HttpStatusCodeEnum::HTTP_SEE_OTHER => ' ("Redirect". Maybe HTTP-to-HTTPS? Check URL/settings.)',
            HttpStatusCodeEnum::HTTP_UNAUTHORIZED => ' ("unauthorized". Check credentials or request format.)',
            HttpStatusCodeEnum::HTTP_NOT_FOUND => ' ("not found" on server)',
            HttpStatusCodeEnum::HTTP_METHOD_NOT_ALLOWED
                => ' ("method not allowed". Check URL or request format/data.)',
            HttpStatusCodeEnum::HTTP_NOT_ACCEPTABLE => ' ("not acceptable" on server. Check request format/data.)',
            HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR => ' (remote "Server error")',
            default => '',
        };
    }
}
