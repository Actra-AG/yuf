<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\auth\AuthSession;
use actra\yuf\Core;
use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use actra\yuf\html\HtmlDocumentSettings;
use actra\yuf\security\CspNonce;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;
use LogicException;

final class ContentHandler
{
    public HttpStatusCodeEnum $httpStatusCode = HttpStatusCodeEnum::HTTP_OK;
    public private(set) bool $suppressCspHeader = false;
    private string $content = '';
    private ContentType $contentType;
    private ?HtmlDocument $htmlDocument = null;
    private ?RequestHandler $requestHandler = null;
    private ?Core $core = null;
    private ?TemplateEngine $templateEngine = null;

    public function __construct(
        ContentType $contentType,
        public readonly CspNonce $cspNonce,
    ) {
        $this->contentType = $contentType;
    }

    /**
     * The HTML document of the request, created on first access.
     *
     * @throws LogicException if the request is not processed yet
     */
    public function getHtmlDocument(): HtmlDocument
    {
        if ($this->htmlDocument === null) {
            if ($this->requestHandler === null || $this->core === null || $this->templateEngine === null) {
                throw new LogicException(
                    message: 'The HTML document is only available while the request is processed.',
                );
            }
            $language = $this->requestHandler->language;
            $this->htmlDocument = new HtmlDocument(
                settings: new HtmlDocumentSettings(
                    viewDirectory: $this->requestHandler->route->viewDirectory,
                    fileGroup: $this->requestHandler->fileGroup,
                    fileTitle: $this->requestHandler->fileTitle,
                    fileName: $this->requestHandler->fileName,
                    languageCode: $language === null ? '' : $language->code,
                    copyright: $this->core->renderCopyrightYear(),
                    robots: $this->core->robots,
                ),
                cspNonce: $this->cspNonce,
                templateEngine: $this->templateEngine,
                csrfTokenSource: $this->core->formContext->csrfTokenSource,
            );
        }

        return $this->htmlDocument;
    }

    /**
     * Runs the view of the resolved route and sets the content of the response.
     *
     * @throws LogicException if called twice
     */
    public function processRequest(
        RequestHandler $requestHandler,
        LocaleHandler $localeHandler,
        Core $core,
        TemplateEngine $templateEngine,
    ): void {
        if ($this->requestHandler !== null) {
            throw new LogicException(message: 'The request is already processed.');
        }
        $this->requestHandler = $requestHandler;
        $this->core = $core;
        $this->templateEngine = $templateEngine;
        $route = $requestHandler->route;
        if ($route->viewCallback !== null) {
            $this->setContent(contentString: ($route->viewCallback)());
            return;
        }
        ob_start();
        ob_implicit_flush(enable: false);
        $route->loadLocalizedText(
            fileTitle: $requestHandler->fileTitle,
            localeHandler: $localeHandler,
        );
        $context = new ViewContext(
            httpRequest: $core->httpRequest,
            session: $core->session,
            sessionHandler: $core->sessionHandler,
            authSession: $core->session === null ? null : new AuthSession(session: $core->session),
            formContext: $core->formContext,
            route: $route,
            fileGroup: $requestHandler->fileGroup,
            fileTitle: $requestHandler->fileTitle,
            pathVars: new PathVars(values: $requestHandler->pathVars),
            content: $this,
            locale: $localeHandler,
            templateEngine: $templateEngine,
        );
        $view = ($route->viewFactory ?? new ClassNameViewFactory())->createView(context: $context);
        if ($view === null) {
            if ($context->pathVars->get(nr: 1) !== null) {
                throw new NotFoundException();
            }
        } else {
            if ($context->pathVars->get(nr: ($view->maxAllowedPathVars + 1)) !== null) {
                throw new NotFoundException();
            }
            if (!$this->hasContent()) {
                $view->execute();
            }
        }
        if (
            !$this->hasContent()
            && $this->contentType->isHtml()
        ) {
            $this->setContent(contentString: $this->getHtmlDocument()->render());
        }
        $outputBuffer = ob_get_clean();
        if ($outputBuffer === false) {
            throw new LogicException(message: 'The output buffer of the view was closed by the view.');
        }
        $outputBufferContents = trim(string: $outputBuffer);
        if ($outputBufferContents !== '') {
            $this->setContent(contentString: $outputBufferContents);
        }
    }

    public function hasContent(): bool
    {
        return trim(string: $this->content) !== '';
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    /**
     * @throws InvalidArgumentException if the content type has no charset (only text types can be the content type of
     *                                  a response of a view)
     */
    public function setContentType(ContentType $contentType): void
    {
        if ($contentType->charset === null) {
            throw new InvalidArgumentException(
                message: 'The content type "' . $contentType->type . '" has no charset and cannot be set as content'
                    . ' type of the response; use a content type with a charset, e.g. ContentType::createJson().',
            );
        }
        $this->contentType = $contentType;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $contentString): void
    {
        if ($this->hasContent()) {
            throw new LogicException(message: 'Content is already set. You are not allowed to overwrite it.');
        }
        $this->content = $contentString;
    }

    public function suppressCspHeader(): void
    {
        $this->suppressCspHeader = true;
    }
}
