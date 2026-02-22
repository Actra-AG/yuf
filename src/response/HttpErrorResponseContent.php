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
        string                    $errorMessage,
        null|int|string           $errorCode = null,
        null|stdClass|ArrayObject $additionalInfo = null
    ): HttpResponseContent
    {
        $content = [
            'message' => $errorMessage,
            'code' => $errorCode,
        ];
        if (
            !is_null(value: $additionalInfo)
            && $additionalInfo->count() > 0
        ) {
            $content['additionalInfo'] = $additionalInfo;
        }

        return new HttpErrorResponseContent(
            content: JsonUtils::convertToJsonString(
                valueToConvert: [
                    'status' => HttpErrorResponseContent::ERROR_STATUS,
                    'error' => $content,
                ]
            )
        );
    }

    public static function createTextResponseContent(
        string           $errorMessage,
        null|int|string  $errorCode = null,
        null|ArrayObject $additionalInfo = null
    ): HttpResponseContent
    {
        $content = [
            'ERROR: ' . $errorMessage . ' (' . $errorCode . ')'
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