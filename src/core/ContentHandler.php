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
    private(set) bool $suppressCspHeader = false;
    private string $content = '';
    private ContentType $contentType;

    private function __construct()
    {
        if (!is_null(value: ContentHandler::$registeredInstance)) {
            throw new LogicException(message: 'ContentHandler is already registered.');
        }
        ContentHandler::$registeredInstance = $this;
        $requestHandler = RequestHandler::get();
        $route = $requestHandler->route;
        $this->contentType = $route->defaultContentType;
        if (!is_null(value: $route->viewCallback)) {
            $this->setContent(contentString: call_user_func(callback: $route->viewCallback));
            return;
        }
        ob_start();
        ob_implicit_flush(enable: false);
        $route->loadLocalizedText(fileTitle: $requestHandler->fileTitle);
        $viewClass = $this->getViewClass();
        if ($viewClass === null) {
            if ($requestHandler->getPathVar(nr: 1) !== null) {
                throw new NotFoundException();
            }
        } else {
            if ($requestHandler->getPathVar(nr: ($viewClass->maxAllowedPathVars + 1)) !== null) {
                throw new NotFoundException();
            }
            if (!$this->hasContent()) {
                $viewClass->execute();
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

    private function getViewClass(): ?BaseView
    {
        $phpClassName = RequestHandler::get()->route->getPhpClassName();
        if (!class_exists(class: $phpClassName)) {
            return null;
        }
        if (!is_subclass_of(
            object_or_class: $phpClassName,
            class: BaseView::class
        )) {
            throw new Exception(
                message: 'The class ' . $phpClassName . ' must extend ' . BaseView::class . '.'
            );
        }

        return new $phpClassName();
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