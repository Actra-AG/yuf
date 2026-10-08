<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\response;

use actra\yuf\common\JsonUtils;
use ArrayObject;
use stdClass;

final class HttpErrorResponseContent
{
    private const string ERROR_STATUS = 'error';

    /**
     * @param stdClass|ArrayObject<array-key, mixed>|null $data
     */
    public static function createJsonResponseContent(
        string $errorMessage,
        int|string|null $errorCode = null,
        stdClass|ArrayObject|null $data = null,
    ): HttpResponseContent {
        $value = [
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $errorMessage,
            ],
        ];
        if (
            $data instanceof stdClass
            || ($data instanceof ArrayObject && $data->count() > 0)
        ) {
            $value['data'] = $data;
        }
        return new HttpResponseContent(
            content: JsonUtils::convertToJsonString(
                valueToConvert: $value,
            ),
        );
    }

    /**
     * @param ArrayObject<array-key, mixed>|null $additionalInfo
     */
    public static function createTextResponseContent(
        string $errorMessage,
        int|string|null $errorCode = null,
        ?ArrayObject $additionalInfo = null,
    ): HttpResponseContent {
        $content = [
            HttpErrorResponseContent::ERROR_STATUS . ': ' . $errorMessage . ' (' . $errorCode . ')',
        ];
        if (
            $additionalInfo !== null
            && $additionalInfo->count() > 0
        ) {
            $content[] = '';
            $content[] = print_r(
                value: $additionalInfo,
                return: true,
            );
        }
        return new HttpResponseContent(
            content: implode(
                separator: PHP_EOL,
                array: $content,
            ),
        );
    }
}
