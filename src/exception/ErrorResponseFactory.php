<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\response\HttpErrorResponseContent;
use actra\yuf\security\CspPolicySettings;
use ArrayObject;

/**
 * Builds the response of an error: JSON or text for machine-readable requests, the rendered error page for all other
 * requests. The page is rendered by the caller (only needed for the HTML format).
 *
 * @internal
 */
final readonly class ErrorResponseFactory
{
    public function __construct(
        private HttpRequest $httpRequest,
        private ?CspPolicySettings $cspPolicySettings,
        private string $cspNonce,
        private Clock $clock = new SystemClock(),
    ) {}

    /**
     * @param array<string, string> $additionalInfo Shown in the JSON `data` and below the text (debug mode only)
     * @param string $htmlContent The rendered error page, used for the format `HTML` only
     * @param ?string $languageCode The language of the page (`Content-Language`)
     */
    public function create(
        ErrorOutputFormatEnum $format,
        ContentType $contentType,
        HttpStatusCodeEnum $httpStatusCode,
        string $errorMessage,
        int|string $errorCode,
        array $additionalInfo,
        string $htmlContent,
        ?string $languageCode,
    ): HttpResponse {
        return match ($format) {
            ErrorOutputFormatEnum::JSON => HttpResponse::createResponseFromString(
                httpStatusCode: $httpStatusCode,
                contentString: HttpErrorResponseContent::createJsonResponseContent(
                    errorMessage: $errorMessage,
                    errorCode: $errorCode,
                    data: ErrorResponseFactory::toArrayObject(values: $additionalInfo),
                )->content,
                contentType: $contentType,
                httpRequest: $this->httpRequest,
                clock: $this->clock,
            ),
            ErrorOutputFormatEnum::TEXT => HttpResponse::createResponseFromString(
                httpStatusCode: $httpStatusCode,
                contentString: HttpErrorResponseContent::createTextResponseContent(
                    errorMessage: $errorMessage,
                    errorCode: $errorCode,
                    additionalInfo: ErrorResponseFactory::toArrayObject(values: $additionalInfo),
                )->content,
                contentType: $contentType,
                httpRequest: $this->httpRequest,
                clock: $this->clock,
            ),
            ErrorOutputFormatEnum::HTML => HttpResponse::createHtmlResponse(
                httpStatusCode: $httpStatusCode,
                htmlContent: $htmlContent,
                cspPolicySettings: $this->cspPolicySettings,
                nonce: $this->cspNonce,
                httpRequest: $this->httpRequest,
                clock: $this->clock,
                languageCode: $languageCode,
            ),
        };
    }

    /**
     * @param array<string, string> $values
     *
     * @return ArrayObject<array-key, mixed> `ArrayObject` is invariant, the response content takes any values
     */
    private static function toArrayObject(array $values): ArrayObject
    {
        /** @var ArrayObject<array-key, mixed> $arrayObject */
        $arrayObject = new ArrayObject();
        foreach ($values as $key => $value) {
            $arrayObject[$key] = $value;
        }

        return $arrayObject;
    }
}
