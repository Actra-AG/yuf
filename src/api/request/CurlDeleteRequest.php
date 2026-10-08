<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api\request;

use actra\yuf\api\AbstractCurlRequest;
use actra\yuf\core\RequestMethodEnum;

final class CurlDeleteRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(method: RequestMethodEnum::DELETE, requestTargetUrl: $requestTargetUrl);
    }

    public static function create(string $requestTargetUrl): CurlDeleteRequest
    {
        return new CurlDeleteRequest(requestTargetUrl: $requestTargetUrl);
    }
}
