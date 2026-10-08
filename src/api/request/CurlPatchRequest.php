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
 * Apply partial modifications to a resource.
 */
final class CurlPatchRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(method: RequestMethodEnum::PATCH, requestTargetUrl: $requestTargetUrl);
    }

    public static function createWithoutBody(string $requestTargetUrl): CurlPatchRequest
    {
        return new CurlPatchRequest(requestTargetUrl: $requestTargetUrl);
    }

    /**
     * @param array<array-key, mixed> $postData Form fields: scalars, `null`, objects or nested arrays of them
     */
    public static function createWithPostBody(string $requestTargetUrl, array $postData): CurlPatchRequest
    {
        $curlPatchRequest = new CurlPatchRequest(requestTargetUrl: $requestTargetUrl);
        $curlPatchRequest->setPostBody(postData: $postData);

        return $curlPatchRequest;
    }

    public static function createWithXmlBody(string $requestTargetUrl, string $xmlString): CurlPatchRequest
    {
        $curlPatchRequest = new CurlPatchRequest(requestTargetUrl: $requestTargetUrl);
        $curlPatchRequest->setXmlBody(xmlString: $xmlString);

        return $curlPatchRequest;
    }

    public static function createWithJsonBody(string $requestTargetUrl, string $jsonString): CurlPatchRequest
    {
        $curlPatchRequest = new CurlPatchRequest(requestTargetUrl: $requestTargetUrl);
        $curlPatchRequest->setJsonBody(jsonString: $jsonString);

        return $curlPatchRequest;
    }

    public static function createJsonApiRequest(string $requestTargetUrl, string $jsonString): CurlPatchRequest
    {
        $curlPatchRequest = new CurlPatchRequest(requestTargetUrl: $requestTargetUrl);
        $curlPatchRequest->setJsonApiBody(jsonString: $jsonString);

        return $curlPatchRequest;
    }

    public static function createWithPlainTextBody(string $requestTargetUrl, string $plainText): CurlPatchRequest
    {
        $curlPatchRequest = new CurlPatchRequest(requestTargetUrl: $requestTargetUrl);
        $curlPatchRequest->setPlainTextBody(plainText: $plainText);

        return $curlPatchRequest;
    }
}
