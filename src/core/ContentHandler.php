<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\NotFoundException;
use actra\yuf\html\HtmlDocument;
use Exception;
use LogicException;

class ContentHandler
{
    private static ?ContentHandler $registeredInstance = null;

    public HttpStatusCode $httpStatusCode = HttpStatusCode::HTTP_OK;
    public private(set) bool $suppressCspHeader = false;
    private string $content = '';
    private ContentType $contentType;
    private ?HtmlDocument $htmlDocument = null;

    public function __construct(ContentType $contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * The HTML document of the request, created on first access.
     */
    public function getHtmlDocument(): HtmlDocument
    {
        if ($this->htmlDocument === null) {
            $this->htmlDocument = new HtmlDocument();
        }

        return $this->htmlDocument;
    }

    private function processRequest(RequestHandler $requestHandler): void
    {
        $route = $requestHandler->route;
        if ($route->viewCallback !== null) {
            $this->setContent(contentString: call_user_func(callback: $route->viewCallback));
            return;
        }
        ob_start();
        ob_implicit_flush(enable: false);
        $route->loadLocalizedText(fileTitle: $requestHandler->fileTitle);
        $context = new ViewContext(
            route: $route,
            fileGroup: $requestHandler->fileGroup,
            fileTitle: $requestHandler->fileTitle,
            pathVars: new PathVars(values: $requestHandler->pathVars),
            content: $this,
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
        $outputBufferContents = trim(string: ob_get_clean());
        if ($outputBufferContents !== '') {
            $this->setContent(contentString: $outputBufferContents);
        }
    }

    public static function get(): ContentHandler
    {
        return ContentHandler::$registeredInstance;
    }

    public function hasContent(): bool
    {
        return trim(string: $this->content) !== '';
    }

    public static function register(): ContentHandler
    {
        if (ContentHandler::$registeredInstance !== null) {
            throw new LogicException(message: 'ContentHandler is already registered.');
        }
        $requestHandler = RequestHandler::get();
        $route = $requestHandler->route;
        if ($route->defaultContentType === null) {
            throw new LogicException(message: 'The route "' . $route->path . '" has no default content type.');
        }
        $contentHandler = new ContentHandler(contentType: $route->defaultContentType);
        ContentHandler::$registeredInstance = $contentHandler;
        $contentHandler->processRequest(requestHandler: $requestHandler);

        return $contentHandler;
    }

    public static function isRegistered(): bool
    {
        return ContentHandler::$registeredInstance !== null;
    }

    public function getContentType(): ContentType
    {
        return $this->contentType;
    }

    public function setContentType(ContentType $contentType): void
    {
        if ($contentType->charset === null) {
            throw new Exception(message: 'Unknown contentType: ' . $contentType->type);
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
