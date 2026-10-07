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

    private function __construct()
    {
        if (ContentHandler::$registeredInstance !== null) {
            throw new LogicException(message: 'ContentHandler is already registered.');
        }
        ContentHandler::$registeredInstance = $this;
        $requestHandler = RequestHandler::get();
        $route = $requestHandler->route;
        $this->contentType = $route->defaultContentType;
        if ($route->viewCallback !== null) {
            $this->setContent(contentString: call_user_func(callback: $route->viewCallback));
            return;
        }
        ob_start();
        ob_implicit_flush(enable: false);
        $route->loadLocalizedText(fileTitle: $requestHandler->fileTitle);
        $view = ($route->viewFactory ?? new ClassNameViewFactory())->createView(
            context: new ViewContext(
                route: $route,
                fileGroup: $requestHandler->fileGroup,
                fileTitle: $requestHandler->fileTitle,
            ),
        );
        if ($view === null) {
            if ($requestHandler->getPathVar(nr: 1) !== null) {
                throw new NotFoundException();
            }
        } else {
            if ($requestHandler->getPathVar(nr: ($view->maxAllowedPathVars + 1)) !== null) {
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
            $this->setContent(HtmlDocument::get()->render());
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
        return new ContentHandler();
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
