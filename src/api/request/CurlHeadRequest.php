<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api\request;

use actra\yuf\api\AbstractCurlRequest;
use actra\yuf\core\RequestMethodEnum;

/**
 * Asks for a response identical to that of a GET request, but without the response body.
 */
final class CurlHeadRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(method: RequestMethodEnum::HEAD, requestTargetUrl: $requestTargetUrl);
    }

    public static function create(string $requestTargetUrl): CurlHeadRequest
    {
        return new CurlHeadRequest(requestTargetUrl: $requestTargetUrl);
    }
}
