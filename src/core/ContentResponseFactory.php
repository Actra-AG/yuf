<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CspPolicySettings;

/**
 * Creates the `HttpResponse` of the content of a processed request: an HTML response with the
 * Content-Security-Policy (unless the content handler suppressed it) or a response of another content type; a
 * request without content is a 404. An HTML response is sent with the `Content-Language` of the request language (none
 * without a language).
 *
 * @internal
 */
final readonly class ContentResponseFactory
{
    public function __construct(
        private HttpRequest $httpRequest,
        private ?CspPolicySettings $cspPolicySettings,
        private ?Language $language = null,
    ) {}

    /**
     * @throws NotFoundException if the request produced no content (answered with 404)
     */
    public function create(ContentHandler $contentHandler): HttpResponse
    {
        if (!$contentHandler->hasContent()) {
            throw new NotFoundException();
        }
        $contentType = $contentHandler->getContentType();
        if ($contentType->isHtml()) {
            return HttpResponse::createHtmlResponse(
                httpStatusCode: $contentHandler->httpStatusCode,
                htmlContent: $contentHandler->getContent(),
                cspPolicySettings: $contentHandler->suppressCspHeader ? null : $this->cspPolicySettings,
                nonce: $contentHandler->cspNonce->value,
                httpRequest: $this->httpRequest,
                languageCode: $this->language?->code,
            );
        }

        return HttpResponse::createResponseFromString(
            httpStatusCode: $contentHandler->httpStatusCode,
            contentString: $contentHandler->getContent(),
            contentType: $contentType,
            httpRequest: $this->httpRequest,
        );
    }
}
