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
 * Used to submit an entity to the specified resource, often causing a change in state or side effects on the server.
 */
final class CurlPostRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(method: RequestMethodEnum::POST, requestTargetUrl: $requestTargetUrl);
    }

    /**
     * @param array<array-key, mixed> $postData Form fields: scalars, `null`, objects or nested arrays of them
     */
    public static function createWithPostBody(
        string $requestTargetUrl,
        array $postData,
    ): CurlPostRequest {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setPostBody(postData: $postData);

        return $curlPostRequest;
    }

    public static function createWithXmlBody(
        string $requestTargetUrl,
        string $xmlString,
    ): CurlPostRequest {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setXmlBody(xmlString: $xmlString);

        return $curlPostRequest;
    }

    public static function createWithJsonBody(
        string $requestTargetUrl,
        string $jsonString,
    ): CurlPostRequest {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setJsonBody(jsonString: $jsonString);

        return $curlPostRequest;
    }

    public static function createJsonApiRequest(
        string $requestTargetUrl,
        string $jsonString,
    ): CurlPostRequest {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setJsonApiBody(jsonString: $jsonString);

        return $curlPostRequest;
    }

    public static function createWithPlainTextBody(
        string $requestTargetUrl,
        string $plainText,
    ): CurlPostRequest {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setPlainTextBody(plainText: $plainText);

        return $curlPostRequest;
    }
}
