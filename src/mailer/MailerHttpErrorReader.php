<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\api\CurlResponse;
use actra\yuf\common\JsonUtils;
use JsonException;
use UnexpectedValueException;

/**
 * Reads the error of a failed HTTP response of the Microsoft identity platform or of the Microsoft Graph API for an
 * exception message. The response comes from outside: only strings are used, white space and control characters are
 * replaced by a space and the text is cut. The messages of these services never contain the secret or the token that
 * was sent.
 *
 * Static on purpose: pure functions without state.
 *
 * @internal
 */
final readonly class MailerHttpErrorReader
{
    private const int MAX_TEXT_LENGTH = 300;

    /**
     * @return string `error: error_description` of an OAuth 2.0 error response (RFC 6749 section 5.2), or an empty
     *                string if the response has none
     */
    public static function describeOAuthError(CurlResponse $response): string
    {
        $data = MailerHttpErrorReader::decode(response: $response);
        if ($data === null) {
            return '';
        }

        return MailerHttpErrorReader::join(
            code: MailerHttpErrorReader::readText(data: $data, key: 'error'),
            message: MailerHttpErrorReader::readText(data: $data, key: 'error_description'),
        );
    }

    /**
     * @return string `code: message` of the `error` object of a Graph error response, or an empty string if the
     *                response has none
     */
    public static function describeGraphError(CurlResponse $response): string
    {
        $data = MailerHttpErrorReader::decode(response: $response);
        if ($data === null || !array_key_exists(key: 'error', array: $data) || !is_array(value: $data['error'])) {
            return '';
        }

        return MailerHttpErrorReader::join(
            code: MailerHttpErrorReader::readText(data: $data['error'], key: 'code'),
            message: MailerHttpErrorReader::readText(data: $data['error'], key: 'message'),
        );
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function decode(CurlResponse $response): ?array
    {
        if ($response->rawResponseBody === false) {
            return null;
        }
        try {
            $decoded = JsonUtils::decodeJsonString(
                jsonString: $response->rawResponseBody,
                returnAssociativeArray: true,
            );
        } catch (JsonException|UnexpectedValueException) {
            return null;
        }

        return is_array(value: $decoded) ? $decoded : null;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function readText(array $data, string $key): string
    {
        if (!array_key_exists(key: $key, array: $data) || !is_string(value: $data[$key])) {
            return '';
        }
        $text = preg_replace(pattern: '/[\p{C}\s]+/u', replacement: ' ', subject: $data[$key]);

        return mb_substr(
            string: trim(string: $text ?? ''),
            start: 0,
            length: MailerHttpErrorReader::MAX_TEXT_LENGTH,
            encoding: 'UTF-8',
        );
    }

    private static function join(string $code, string $message): string
    {
        return match (true) {
            $code !== '' && $message !== '' => $code . ': ' . $message,
            default => $code . $message,
        };
    }
}
