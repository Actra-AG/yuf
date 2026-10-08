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
 * Replaces all current representations of the target resource with the request payload.
 */
final class CurlPutRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(method: RequestMethodEnum::PUT, requestTargetUrl: $requestTargetUrl);
    }

    /**
     * @param array<array-key, mixed> $postData Form fields: scalars, `null`, objects or nested arrays of them
     */
    public static function createWithPostBody(
        string $requestTargetUrl,
        array $postData,
    ): CurlPutRequest {
        $curlPutRequest = new CurlPutRequest(requestTargetUrl: $requestTargetUrl);
        $curlPutRequest->setPostBody(postData: $postData);

        return $curlPutRequest;
    }

    public static function createWithXmlBody(
        string $requestTargetUrl,
        string $xmlString,
    ): CurlPutRequest {
        $curlPutRequest = new CurlPutRequest(requestTargetUrl: $requestTargetUrl);
        $curlPutRequest->setXmlBody(xmlString: $xmlString);

        return $curlPutRequest;
    }

    public static function createWithJsonBody(
        string $requestTargetUrl,
        string $jsonString,
    ): CurlPutRequest {
        $curlPutRequest = new CurlPutRequest(requestTargetUrl: $requestTargetUrl);
        $curlPutRequest->setJsonBody(jsonString: $jsonString);

        return $curlPutRequest;
    }

    public static function createJsonApiRequest(
        string $requestTargetUrl,
        string $jsonString,
    ): CurlPutRequest {
        $curlPutRequest = new CurlPutRequest(requestTargetUrl: $requestTargetUrl);
        $curlPutRequest->setJsonApiBody(jsonString: $jsonString);

        return $curlPutRequest;
    }

    public static function createWithPlainTextBody(
        string $requestTargetUrl,
        string $plainText,
    ): CurlPutRequest {
        $curlPutRequest = new CurlPutRequest(requestTargetUrl: $requestTargetUrl);
        $curlPutRequest->setPlainTextBody(plainText: $plainText);

        return $curlPutRequest;
    }
}
