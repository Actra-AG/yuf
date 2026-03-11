<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api\request;

use actra\yuf\api\AbstractCurlRequest;

/**
 * Asks for a response identical to that of a GET request, but without the response body.
 */
class CurlHeadRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(
            requestTargetUrl: $requestTargetUrl,
            requestTypeSpecificCurlOptions: [
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
            ]
        );
    }

    public static function prepare(string $requestTargetUrl): CurlHeadRequest
    {
        return new CurlHeadRequest(requestTargetUrl: $requestTargetUrl);
    }
}