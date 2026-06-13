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

class HttpErrorResponseContent extends HttpResponseContent
{
    private const string ERROR_STATUS = 'error';

    private function __construct(string $content)
    {
        parent::__construct(content: $content);
    }

    public static function createJsonResponseContent(
        string $errorMessage,
        null|int|string $errorCode = null,
        null|stdClass|ArrayObject $data = null
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
        return new HttpErrorResponseContent(
            content: JsonUtils::convertToJsonString(
                valueToConvert: $value
            )
        );
    }

    public static function createTextResponseContent(
        string $errorMessage,
        null|int|string $errorCode = null,
        null|ArrayObject $additionalInfo = null
    ): HttpResponseContent {
        $content = [
            HttpErrorResponseContent::ERROR_STATUS . ': ' . $errorMessage . ' (' . $errorCode . ')'
        ];
        if (
            !is_null(value: $additionalInfo)
            && $additionalInfo->count() > 0
        ) {
            $content[] = '';
            $content[] = print_r(
                value: $additionalInfo,
                return: true
            );
        }
        return new HttpErrorResponseContent(
            content: implode(
                separator: PHP_EOL,
                array: $content
            )
        );
    }
}