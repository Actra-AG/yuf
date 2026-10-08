<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\html\HtmlDocument;
use actra\yuf\request\JsonRequestBody;
use actra\yuf\request\RequestBody;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;

/**
 * The request data of a view and what a view factory needs to choose and create it. One instance per request.
 */
final class ViewContext
{
    private ?JsonRequestBody $jsonRequestBody = null;

    public function __construct(
        public readonly Route $route,
        public readonly ?string $fileGroup,
        public readonly string $fileTitle,
        public readonly PathVars $pathVars,
        public readonly ContentHandler $content,
        public readonly LocaleHandler $locale,
        public readonly TemplateEngine $templateEngine,
    ) {}

    public function getHtmlDocument(): HtmlDocument
    {
        return $this->content->getHtmlDocument();
    }

    /**
     * @throws InvalidArgumentException if the request body is not valid JSON
     */
    public function getJsonRequestBody(): JsonRequestBody
    {
        if ($this->jsonRequestBody === null) {
            $this->jsonRequestBody = JsonRequestBody::fromString(json: RequestBody::getData());
        }

        return $this->jsonRequestBody;
    }
}
