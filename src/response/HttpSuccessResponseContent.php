<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\response;

use actra\yuf\common\JsonUtils;
use stdClass;

class HttpSuccessResponseContent extends HttpResponseContent
{
    private const string SUCCESS_STATUS = 'success';

    private function __construct(string $content)
    {
        parent::__construct(content: $content);
    }

    public static function createJsonResponseContent(stdClass $data): HttpResponseContent
    {
        return new HttpSuccessResponseContent(content: JsonUtils::convertToJsonString([
            'success' => true,
            'data' => $data,
        ]));
    }

    public static function createTextResponseContent(stdClass $data): HttpResponseContent
    {
        return new HttpSuccessResponseContent(
            content: HttpSuccessResponseContent::SUCCESS_STATUS . PHP_EOL . print_r(
                value: $data,
                return: true
            )
        );
    }
}