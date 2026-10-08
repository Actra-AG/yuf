<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\RequestHandler;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\security\CsrfHiddenFieldRenderer;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\session\Session;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Answers every exception that is not caught: an error page, JSON or text (by the content type of the request).
 *
 * Production shows a fixed text per kind of error (`ErrorKindEnum`) and logs unexpected errors; the message, the file
 * and the stack trace of the exception are only shown in debug mode.
 *
 * Extension point: projects pass a subclass as `individualExceptionHandler:` to `Core::prepareHttpResponse()` to add
 * values to the error pages (`$htmlReplacementCollection`) or to answer other exceptions differently by overriding
 * `createDebugResponse()`, `createNotFoundResponse()`, `createUnauthorizedResponse()` or `createDefaultResponse()`.
 *
 * `handleException()` is registered as the global exception handler by `register()`; it does nothing but send the
 * response of `createResponse()` and end the script, so everything else can be tested.
 */
class ExceptionHandler
{
    // Set by register(): the handler instance is created by the project, before the request dependencies exist
    private ?ExceptionHandlerContext $context = null;
    // Set by Core as soon as the request data exists; an exception before that uses fallback values
    private ?RequestHandler $requestHandler = null;
    private ?ContentHandler $contentHandler = null;
    // Set by Core as soon as the session exists (`null` without sessions); an exception before that has none
    private ?Session $session = null;
    private ?CsrfTokenSource $csrfTokenSource = null;
    private bool $sessionIsSet = false;

    public function __construct(
        protected readonly HtmlReplacementCollection $htmlReplacementCollection = new HtmlReplacementCollection(),
    ) {}

    /**
     * Sets the handler as the global exception handler (a process-wide setting of PHP, so there is no state of our
     * own that could be reset: a second registration is recognised by the handler PHP reports as replaced).
     *
     * @throws LogicException if an exception handler is registered already
     */
    public static function register(
        ?ExceptionHandler $individualExceptionHandler,
        ExceptionHandlerContext $context,
    ): ExceptionHandler {
        $exceptionHandler = $individualExceptionHandler ?? new ExceptionHandler();
        $previousHandler = set_exception_handler(callback: [
            $exceptionHandler,
            'handleException',
        ]);
        if (is_array(value: $previousHandler) && $previousHandler[0] instanceof ExceptionHandler) {
            restore_exception_handler();
            throw new LogicException(message: 'ExceptionHandler is already registered.');
        }
        $exceptionHandler->context = $context;

        return $exceptionHandler;
    }

    /**
     * @throws LogicException if the request handler is already set
     */
    public function setRequestHandler(RequestHandler $requestHandler): void
    {
        if ($this->requestHandler !== null) {
            throw new LogicException(message: 'The request handler is already set.');
        }
        $this->requestHandler = $requestHandler;
    }

    /**
     * @throws LogicException if the content handler is already set
     */
    public function setContentHandler(ContentHandler $contentHandler): void
    {
        if ($this->contentHandler !== null) {
            throw new LogicException(message: 'The content handler is already set.');
        }
        $this->contentHandler = $contentHandler;
    }

    /**
     * @param ?Session $session Shown in the debug page (`null` without sessions)
     * @param ?CsrfTokenSource $csrfTokenSource Renders the `csrfField` of the error pages (`null` without sessions)
     *
     * @throws LogicException if the session is already set
     */
    public function setSession(?Session $session, ?CsrfTokenSource $csrfTokenSource): void
    {
        if ($this->sessionIsSet) {
            throw new LogicException(message: 'The session is already set.');
        }
        $this->sessionIsSet = true;
        $this->session = $session;
        $this->csrfTokenSource = $csrfTokenSource;
    }

    /**
     * @throws LogicException if the handler is not registered
     */
    protected function getContext(): ExceptionHandlerContext
    {
        if ($this->context === null) {
            throw new LogicException(message: 'ExceptionHandler is not registered: the context is not available.');
        }

        return $this->context;
    }

    /**
     * The content type of the request; HTML as long as the request is not processed.
     */
    protected function getContentType(): ContentType
    {
        return $this->contentHandler === null ? ContentType::createHtml() : $this->contentHandler->getContentType();
    }

    final public function handleException(Throwable $throwable): void
    {
        $this->createResponse(throwable: $throwable)->sendAndExit();
    }

    /**
     * The response for an exception: the debug page in debug mode, else the page of its kind. Unexpected errors are
     * logged in production (not found and unauthorized are normal requests).
     */
    final public function createResponse(Throwable $throwable): HttpResponse
    {
        $context = $this->getContext();
        if ($context->isDebug) {
            return $this->createDebugResponse(throwable: $throwable);
        }

        return match (ErrorKindEnum::fromThrowable(throwable: $throwable)) {
            ErrorKindEnum::NOT_FOUND => $this->createNotFoundResponse(throwable: $throwable),
            ErrorKindEnum::UNAUTHORIZED => $this->createUnauthorizedResponse(throwable: $throwable),
            ErrorKindEnum::INTERNAL_ERROR => $this->logAndCreateDefaultResponse(throwable: $throwable),
        };
    }

    private function logAndCreateDefaultResponse(Throwable $throwable): HttpResponse
    {
        $this->getContext()->logger->logException(throwable: $throwable);

        return $this->createDefaultResponse(throwable: $throwable);
    }

    protected function createDebugResponse(Throwable $throwable): HttpResponse
    {
        $context = $this->getContext();
        $debugInfo = ExceptionDebugInfo::create(
            throwable: $throwable,
            httpRequest: $context->httpRequest,
            session: $this->session,
        );
        $debugInfo->addTo(replacements: $this->htmlReplacementCollection);

        return $this->createErrorResponse(
            httpStatusCode: ErrorKindEnum::fromThrowable(throwable: $throwable)->getHttpStatusCode(),
            errorMessage: $debugInfo->errorMessage,
            errorCode: $debugInfo->errorCode,
            htmlFileName: 'debug.html',
            additionalInfo: $debugInfo->toArray(),
        );
    }

    protected function createNotFoundResponse(Throwable $throwable): HttpResponse
    {
        return $this->createKindResponse(errorKind: ErrorKindEnum::NOT_FOUND);
    }

    protected function createUnauthorizedResponse(Throwable $throwable): HttpResponse
    {
        return $this->createKindResponse(errorKind: ErrorKindEnum::UNAUTHORIZED);
    }

    protected function createDefaultResponse(Throwable $throwable): HttpResponse
    {
        return $this->createKindResponse(errorKind: ErrorKindEnum::INTERNAL_ERROR);
    }

    /**
     * The production answer of a kind of error: its fixed text and page, never the message of the exception.
     */
    private function createKindResponse(ErrorKindEnum $errorKind): HttpResponse
    {
        return $this->createErrorResponse(
            httpStatusCode: $errorKind->getHttpStatusCode(),
            errorMessage: $errorKind->getPublicMessage(),
            errorCode: $errorKind->getHttpStatusCode()->value,
            htmlFileName: $errorKind->getHtmlFileName(),
        );
    }

    /**
     * @param string $htmlFileName The error page in the error docs directory (no path), used for HTML requests
     * @param array<string, string> $additionalInfo Shown in the JSON `data` and below the text; debug mode only
     *
     * @throws InvalidArgumentException if the file name contains a path
     */
    final protected function createErrorResponse(
        HttpStatusCodeEnum $httpStatusCode,
        string $errorMessage,
        string|int $errorCode,
        string $htmlFileName,
        array $additionalInfo = [],
    ): HttpResponse {
        $context = $this->getContext();
        $format = ErrorOutputFormatEnum::fromContentType(contentType: $this->getContentType());
        $languageCode = $this->requestHandler?->language?->code;

        return new ErrorResponseFactory(
            httpRequest: $context->httpRequest,
            cspPolicySettings: $context->cspPolicySettings,
            cspNonce: $context->cspNonce->value,
        )->create(
            format: $format,
            contentType: $this->getContentType(),
            httpStatusCode: $httpStatusCode,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            additionalInfo: $additionalInfo,
            htmlContent: $format === ErrorOutputFormatEnum::HTML
                ? $this->renderErrorPage(htmlFileName: $htmlFileName, fallbackText: $errorMessage)
                : '',
            languageCode: $languageCode,
        );
    }

    private function renderErrorPage(string $htmlFileName, string $fallbackText): string
    {
        $context = $this->getContext();
        $requestHandler = $this->requestHandler;
        new ErrorPageValues(
            htmlFileName: $htmlFileName,
            copyright: $context->copyright,
            languageCode: $requestHandler?->language?->code,
            languageRoot: $requestHandler === null ? '/' : $requestHandler->getLanguageRoot(),
            cspNonce: $context->cspNonce->value,
            csrfFieldHtml: CsrfHiddenFieldRenderer::render(csrfTokenSource: $this->csrfTokenSource),
            requestedFileName: $requestHandler?->fileName,
        )->addTo(replacements: $this->htmlReplacementCollection);

        return new ErrorPageRenderer(
            errorDocsDirectory: $context->errorDocsDirectory,
            isDebug: $context->isDebug,
            logger: $context->logger,
        )->render(
            htmlFileName: $htmlFileName,
            replacements: $this->htmlReplacementCollection,
            templateEngine: $this->createTemplateEngine(),
            fallbackText: $fallbackText,
        );
    }

    /**
     * The template engine of the error pages, with the global texts of the default route of the requested language,
     * if the request and its language are known.
     */
    private function createTemplateEngine(): TemplateEngine
    {
        $context = $this->getContext();
        $requestHandler = $this->requestHandler;
        $language = $requestHandler?->language;
        if (
            $requestHandler === null
            || $language === null
            || !$context->availableLanguages->hasLanguage(languageCode: $language->code)
        ) {
            return ($context->createTemplateEngine)(
                new LocaleHandler(language: null, availableLanguages: $context->availableLanguages),
            );
        }
        $localeHandler = new LocaleHandler(language: $language, availableLanguages: $context->availableLanguages);
        $localeHandler->applySystemLocale();
        $defaultRouteForLanguage = $requestHandler->defaultRoutesByLanguage->getRouteForLanguage(
            languageCode: $language->code,
        );
        $defaultRouteForLanguage?->loadLocalizedText(fileTitle: '', localeHandler: $localeHandler);

        return ($context->createTemplateEngine)($localeHandler);
    }
}
