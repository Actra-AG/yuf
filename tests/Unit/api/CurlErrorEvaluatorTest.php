<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\api;

use actra\yuf\api\CurlErrorEvaluator;
use actra\yuf\api\CurlResponse;
use actra\yuf\api\CurlResponseError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurlErrorEvaluatorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: int, 1: bool}>
     */
    public static function noErrorProvider(): iterable
    {
        return [
            'status 200' => [200, false],
            'status 201' => [201, false],
            'status 204' => [204, false],
            'status 299' => [299, false],
            'no status (HEAD of a local file, not an HTTP response)' => [0, false],
            'status 600 is no HTTP status' => [600, false],
            'accepted 301' => [301, true],
            'accepted 302' => [302, true],
            'accepted 303' => [303, true],
            'accepted 307' => [307, true],
            'accepted 308' => [308, true],
        ];
    }

    #[DataProvider('noErrorProvider')]
    public function testNoError(int $statusCode, bool $acceptRedirection): void
    {
        self::assertNull($this->evaluate(statusCode: $statusCode, acceptRedirection: $acceptRedirection));
    }

    /**
     * @return iterable<string, array{0: int, 1: bool}>
     */
    public static function badStatusProvider(): iterable
    {
        return [
            'status 301' => [301, false],
            'status 302' => [302, false],
            'status 303' => [303, false],
            'status 304' => [304, false],
            'status 305' => [305, false],
            'status 306' => [306, false],
            'status 307' => [307, false],
            'status 308' => [308, false],
            'status 300' => [300, false],
            '300 accepted does not count' => [300, true],
            '304 accepted does not count' => [304, true],
            '305 accepted does not count' => [305, true],
            '306 accepted does not count' => [306, true],
            'status 400' => [400, false],
            '404 accepted does not count' => [404, true],
            '418 (not in the enum of status codes)' => [418, false],
            '429 (not in the enum of status codes)' => [429, false],
            'status 599' => [599, false],
        ];
    }

    #[DataProvider('badStatusProvider')]
    public function testBadStatusCode(int $statusCode, bool $acceptRedirection): void
    {
        $error = $this->evaluate(statusCode: $statusCode, acceptRedirection: $acceptRedirection);

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(CurlResponse::ERROR_BAD_HTTP_RESPONSE_CODE, $error->code);
        self::assertStringStartsWith(
            CurlResponse::class . ': Bad HTTP response code received: ' . $statusCode,
            $error->message,
        );
    }

    public function testCurlErrorCodeAndMessage(): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_COULDNT_CONNECT,
            curlErrorMessage: 'Failed to connect to example.com port 443',
            statusCode: 0,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 100,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(CURLE_COULDNT_CONNECT, $error->code);
        self::assertSame(
            CurlResponse::class . ': (7) Failed to connect to example.com port 443',
            $error->message,
        );
    }

    public function testCurlErrorWinsOverTheStatusCode(): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_OPERATION_TIMEOUTED,
            curlErrorMessage: 'Operation timed out',
            statusCode: 500,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 100,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(CURLE_OPERATION_TIMEOUTED, $error->code);
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function hintProvider(): iterable
    {
        return [
            'access denied' => [CURLE_FTP_ACCESS_DENIED, '; Hint: (Remote) Access denied.'],
            'ssl connect' => [CURLE_SSL_CONNECT_ERROR, '; Hint: Problem with ssl connection.'],
            'got nothing' => [CURLE_GOT_NOTHING, '; Hint: Got no data.'],
            'certificate problem' => [CURLE_SSL_CERTPROBLEM, '; Hint: Problem with certificate on ssl connection.'],
            'unsupported protocol' => [CURLE_UNSUPPORTED_PROTOCOL, '; Hint: Only http and https are supported.'],
            'login denied' => [67, '; Hint: Login denied.'],
            'no hint' => [CURLE_COULDNT_RESOLVE_HOST, ''],
        ];
    }

    #[DataProvider('hintProvider')]
    public function testHintsOfCurlErrors(int $curlErrorCode, string $expectedHint): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: $curlErrorCode,
            curlErrorMessage: 'message',
            statusCode: 0,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 100,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(
            CurlResponse::class . ': (' . $curlErrorCode . ') message' . $expectedHint,
            $error->message,
        );
    }

    public function testCertificateProblemsSayThatTheVerificationCannotBeSwitchedOff(): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_SSL_PEER_CERTIFICATE,
            curlErrorMessage: 'SSL certificate problem',
            statusCode: 0,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 100,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertStringContainsString('always verified', $error->message);
    }

    public function testResponseLargerThanTheLimitFromTheWriteCallback(): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_WRITE_ERROR,
            curlErrorMessage: 'Failure writing output to destination',
            statusCode: 200,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: true,
            maxResponseSizeInBytes: 1000,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(CurlResponse::ERROR_RESPONSE_TOO_LARGE, $error->code);
        self::assertSame(CurlResponse::class . ': The response is larger than 1000 bytes.', $error->message);
    }

    public function testResponseLargerThanTheLimitFromContentLength(): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_FILESIZE_EXCEEDED,
            curlErrorMessage: 'Maximum file size exceeded',
            statusCode: 200,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 1000,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(CurlResponse::ERROR_RESPONSE_TOO_LARGE, $error->code);
    }

    public function testWriteErrorWithoutExceededLimitStaysACurlError(): void
    {
        $error = CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_WRITE_ERROR,
            curlErrorMessage: 'Failure writing output to destination',
            statusCode: 200,
            acceptRedirectionResponseCode: false,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 1000,
        );

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(CURLE_WRITE_ERROR, $error->code);
    }

    /**
     * @return iterable<string, array{0: int, 1: string}>
     */
    public static function statusHintProvider(): iterable
    {
        return [
            'status 301' => [301, ' ("moved permanently". Check URL/settings.)'],
            'status 303' => [303, ' ("Redirect". Maybe HTTP-to-HTTPS? Check URL/settings.)'],
            'status 302' => [302, ' ("found", temporary redirect. Check URL/settings.)'],
            'status 307' => [307, ' ("temporary redirect". Check URL/settings.)'],
            'status 308' => [308, ' ("permanent redirect". Check URL/settings.)'],
            'status 401' => [401, ' ("unauthorized". Check credentials or request format.)'],
            'status 404' => [404, ' ("not found" on server)'],
            'status 405' => [405, ' ("method not allowed". Check URL or request format/data.)'],
            'status 406' => [406, ' ("not acceptable" on server. Check request format/data.)'],
            'status 500' => [500, ' (remote "Server error")'],
            'status 502' => [502, ''],
            'status 429' => [429, ''],
        ];
    }

    #[DataProvider('statusHintProvider')]
    public function testHintsOfStatusCodes(int $statusCode, string $expectedHint): void
    {
        $error = $this->evaluate(statusCode: $statusCode, acceptRedirection: false);

        self::assertInstanceOf(CurlResponseError::class, $error);
        self::assertSame(
            CurlResponse::class . ': Bad HTTP response code received: ' . $statusCode . $expectedHint,
            $error->message,
        );
    }

    private function evaluate(int $statusCode, bool $acceptRedirection): ?CurlResponseError
    {
        return CurlErrorEvaluator::evaluate(
            curlErrorCode: CURLE_OK,
            curlErrorMessage: '',
            statusCode: $statusCode,
            acceptRedirectionResponseCode: $acceptRedirection,
            isResponseLimitExceeded: false,
            maxResponseSizeInBytes: 100,
        );
    }
}
