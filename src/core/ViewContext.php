<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\auth\AuthSession;
use actra\yuf\form\FormContext;
use actra\yuf\html\HtmlDocument;
use actra\yuf\request\JsonRequestBody;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\Session;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;

/**
 * The request data of a view and what a view factory needs to choose and create it. One instance per request.
 * `session`, `sessionHandler` (for the SameSite change of a login redirect) and `authSession` are `null` without
 * sessions (`individualSessionHandler: false`); `formContext` is what every form needs (the request and, with a
 * session, the CSRF token source).
 */
final class ViewContext
{
    private ?JsonRequestBody $jsonRequestBody = null;

    public function __construct(
        public readonly HttpRequest $httpRequest,
        public readonly ?Session $session,
        public readonly ?AbstractSessionHandler $sessionHandler,
        public readonly ?AuthSession $authSession,
        public readonly FormContext $formContext,
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
            $this->jsonRequestBody = JsonRequestBody::fromString(json: $this->httpRequest->getBody());
        }

        return $this->jsonRequestBody;
    }
}
