<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

/**
 * The kinds of request bodies a cURL request can send, with the headers each of them needs.
 *
 * @internal
 */
enum CurlBodyTypeEnum
{
    case FORM_URLENCODED;
    case XML;
    case JSON;
    case JSON_API;
    case PLAIN_TEXT;

    public function getContentType(): string
    {
        return match ($this) {
            CurlBodyTypeEnum::FORM_URLENCODED => 'application/x-www-form-urlencoded; charset=utf-8',
            CurlBodyTypeEnum::XML => 'text/xml; charset=utf-8',
            CurlBodyTypeEnum::JSON => 'application/json; charset=utf-8',
            CurlBodyTypeEnum::JSON_API => 'application/vnd.api+json',
            CurlBodyTypeEnum::PLAIN_TEXT => 'text/plain; charset=utf-8',
        };
    }

    /**
     * @return array<string, string> Headers (besides the content type) that a body of this type sets by default
     */
    public function getDefaultHeaders(): array
    {
        return match ($this) {
            CurlBodyTypeEnum::XML => ['HTTP_PRETTY_PRINT' => 'TRUE'],
            CurlBodyTypeEnum::JSON_API => ['Accept' => 'application/vnd.api+json'],
            default => [],
        };
    }
}
