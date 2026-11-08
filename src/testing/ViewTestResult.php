<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\testing;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\ViewContext;
use actra\yuf\html\HtmlDocument;
use LogicException;

/**
 * The outcome of a view run by `ViewTestClient::run()`: the response the view ended the script with (redirect,
 * download, JSON), or the content it rendered.
 */
final readonly class ViewTestResult
{
    /**
     * @param ?HttpResponse $sentResponse The response the view sent (and ended the script with), `null` if it rendered
     * @param string $content The rendered content (the page), empty if the view sent a response
     * @param HttpStatusCodeEnum $httpStatusCode The status code of the rendered content
     * @param ViewContext $context The context the view ran with (its request, session and content)
     */
    public function __construct(
        public ?HttpResponse $sentResponse,
        public string $content,
        public HttpStatusCodeEnum $httpStatusCode,
        public ViewContext $context,
    ) {}

    /**
     * The `Location` header of the sent response (a redirect), `null` for none.
     */
    public function getLocation(): ?string
    {
        return $this->sentResponse?->getHeader(key: 'Location');
    }

    /**
     * The HTML document of the page, e.g. to read its replacements.
     *
     * @throws LogicException if the view sent a response or the content is no HTML page
     */
    public function getHtmlDocument(): HtmlDocument
    {
        if ($this->sentResponse !== null) {
            throw new LogicException(message: 'The view sent a response instead of rendering a page.');
        }

        return $this->context->getHtmlDocument();
    }
}
