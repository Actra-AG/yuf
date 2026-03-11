<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api\request;

use actra\yuf\api\AbstractCurlRequest;

class CurlDeleteRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(
            requestTargetUrl: $requestTargetUrl,
            requestTypeSpecificCurlOptions: [CURLOPT_CUSTOMREQUEST => 'DELETE']
        );
    }

    public static function prepare(string $requestTargetUrl): CurlDeleteRequest
    {
        return new CurlDeleteRequest(requestTargetUrl: $requestTargetUrl);
    }
}