<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\request;

abstract class RequestBody
{
    private static null|string $data = null;

    public static function getData(): string
    {
        if (RequestBody::$data === null) {
            RequestBody::$data = (string)file_get_contents(filename: 'php://input');
        }
        return RequestBody::$data;
    }
}