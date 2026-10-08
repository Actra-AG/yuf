<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\response;

use actra\yuf\common\JsonUtils;
use stdClass;

final class HttpSuccessResponseContent
{
    private const string SUCCESS_STATUS = 'success';

    public static function createJsonResponseContent(stdClass $data): HttpResponseContent
    {
        return new HttpResponseContent(content: JsonUtils::convertToJsonString(valueToConvert: [
            'success' => true,
            'data' => $data,
        ]));
    }

    public static function createTextResponseContent(stdClass $data): HttpResponseContent
    {
        return new HttpResponseContent(
            content: HttpSuccessResponseContent::SUCCESS_STATUS . PHP_EOL . print_r(
                value: $data,
                return: true,
            ),
        );
    }
}
