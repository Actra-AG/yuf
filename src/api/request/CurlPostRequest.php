<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api\request;

use actra\yuf\api\AbstractCurlRequest;

/**
 * Used to submit an entity to the specified resource, often causing a change in state or side effects on the server.
 */
class CurlPostRequest extends AbstractCurlRequest
{
    private function __construct(string $requestTargetUrl)
    {
        parent::__construct(
            requestTargetUrl: $requestTargetUrl,
            requestTypeSpecificCurlOptions: [CURLOPT_POST => true]
        );
    }

    public static function prepareWithPostBody(
        string $requestTargetUrl,
        array  $postData
    ): CurlPostRequest
    {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setPostBody(postData: $postData);

        return $curlPostRequest;
    }

    public static function prepareWithXmlBody(
        string $requestTargetUrl,
        string $xmlString
    ): CurlPostRequest
    {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setXmlBody(xmlString: $xmlString);

        return $curlPostRequest;
    }

    public static function prepareWithJsonBody(
        string $requestTargetUrl,
        string $jsonString
    ): CurlPostRequest
    {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setJsonBody(jsonString: $jsonString);

        return $curlPostRequest;
    }

    public static function prepareJsonApiRequest(
        string $requestTargetUrl,
        string $jsonString
    ): CurlPostRequest
    {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setJsonApiBody(jsonString: $jsonString);

        return $curlPostRequest;
    }

    public static function prepareWithPlainTextBody(
        string $requestTargetUrl,
        string $plainText
    ): CurlPostRequest
    {
        $curlPostRequest = new CurlPostRequest(requestTargetUrl: $requestTargetUrl);
        $curlPostRequest->setPlainTextBody(plainText: $plainText);

        return $curlPostRequest;
    }
}