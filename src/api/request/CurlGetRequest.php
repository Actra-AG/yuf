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
 * Requests a representation of the specified resource. Requests using GET should only retrieve data.
 */
final class CurlGetRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(method: RequestMethodEnum::GET, requestTargetUrl: $requestTargetUrl);
    }

    public static function create(string $requestTargetUrl): CurlGetRequest
    {
        return new CurlGetRequest(requestTargetUrl: $requestTargetUrl);
    }
}
