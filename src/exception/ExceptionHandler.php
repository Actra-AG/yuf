<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\Core;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\RequestHandler;
use actra\yuf\html\HtmlReplacement;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlSnippet;
use actra\yuf\response\HttpErrorResponseContent;
use actra\yuf\security\CsrfToken;
use LogicException;
use Throwable;

class ExceptionHandler
{
    private static ?ExceptionHandler $registeredInstance = null;
    protected ContentType $contentType;
    // Set by register(): the handler instance is created by the project, before the request dependencies exist
    private ?ExceptionHandlerContext $context = null;
    // Set by Core as soon as the request data exists; an exception before that uses fallback values
    private ?RequestHandler $requestHandler = null;
    private ?ContentHandler $contentHandler = null;

    public function __construct(
        protected readonly HtmlReplacementCollection $htmlReplacementCollection = new HtmlReplacementCollection(),
    ) {}

    public static function register(
        ?ExceptionHandler $individualExceptionHandler,
        ExceptionHandlerContext $context,
    ): ExceptionHandler {
        if (ExceptionHandler::$registeredInstance !== null) {
            throw new LogicException(message: 'ExceptionHandler is already registered.');
        }
        $exceptionHandler = $individualExceptionHandler === null ? new ExceptionHandler() : $individualExceptionHandler;
        $exceptionHandler->context = $context;
        ExceptionHandler::$registeredInstance = $exceptionHandler;
        set_exception_handler(callback: [
            $exceptionHandler,
            'handleException',
        ]);

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

    protected function getContext(): ExceptionHandlerContext
    {
        if ($this->context === null) {
            throw new LogicException(message: 'ExceptionHandler is not registered: the context is not available.');
        }

        return $this->context;
    }

    final public function handleException(Throwable $throwable): void
    {
        $this->contentType = $this->contentHandler === null
            ? ContentType::createHtml()
            : $this->contentHandler->getContentType();
        if ($this->getContext()->isDebug) {
            $this->sendDebugHttpResponseAndExit(throwable: $throwable);
        }
        if ($throwable instanceof NotFoundException) {
            $this->sendNotFoundHttpResponseAndExit(throwable: $throwable);
        }
        if ($throwable instanceof UnauthorizedException) {
            $this->sendUnauthorizedHttpResponseAndExit(throwable: $throwable);
        }
        $this->getContext()->logger->logException(throwable: $throwable);
        $this->sendDefaultHttpResponseAndExit(throwable: $throwable);
    }

    protected function sendDebugHttpResponseAndExit(Throwable $throwable): void
    {
        $realException = $throwable->getPrevious() === null ? $throwable : $throwable->getPrevious();
        $errorCode = $realException->getCode();
        $errorMessage = $realException->getMessage();

        if ($throwable instanceof NotFoundException) {
            $httpStatusCode = HttpStatusCodeEnum::HTTP_NOT_FOUND;
            $title = 'Page not found';
        } elseif ($throwable instanceof UnauthorizedException) {
            $httpStatusCode = HttpStatusCodeEnum::HTTP_UNAUTHORIZED;
            $title = 'Unauthorized';
        } else {
            $httpStatusCode = HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR;
            $title = 'Internal Server Error';
        }
        $this->htmlReplacementCollection->addHtml(
            identifier: 'title',
            html: $title,
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'errorType',
            html: get_class(object: $throwable),
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'errorMessage',
            html: $errorMessage,
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'errorFile',
            html: $realException->getFile(),
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'errorLine',
            html: (string) $realException->getLine(),
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'errorCode',
            html: (string) $realException->getCode(),
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'backtrace',
            html: $realException->getTraceAsString(),
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'vardump_get',
            html: isset($_GET) ? htmlentities(string: var_export(value: $_GET, return: true)) : '',
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'vardump_post',
            html: isset($_POST) ? htmlentities(
                string: var_export(value: $_POST, return: true),
            ) : '',
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'vardump_file',
            html: isset($_FILE) ? htmlentities(
                string: var_export(value: $_FILE, return: true),
            ) : '',
        );
        $this->htmlReplacementCollection->addHtml(
            identifier: 'vardump_sess',
            html: isset($_SESSION) ? htmlentities(
                string: var_export(
                    value: $_SESSION,
                    return: true,
                ),
            ) : '',
        );
        $this->sendHttpResponseAndExit(
            httpStatusCode: $httpStatusCode,
            errorMessage: $errorMessage,
            errorCode: $errorCode,
            htmlFileName: 'debug.html',
        );
    }

    final protected function sendHttpResponseAndExit(
        HttpStatusCodeEnum $httpStatusCode,
        string $errorMessage,
        string|int $errorCode,
        string $htmlFileName,
    ): void {
        $contentType = $this->contentType;
        if ($contentType->isJson()) {
            $httpResponse = HttpResponse::createResponseFromString(
                httpStatusCode: $httpStatusCode,
                contentString: HttpErrorResponseContent::createJsonResponseContent(
                    errorMessage: $errorMessage,
                    errorCode: $errorCode,
                    data: $this->htmlReplacementCollection->getArrayObject(),
                )->content,
                contentType: $contentType,
            );
            $httpResponse->sendAndExit();
        }
        if (
            $contentType->isTxt()
            || $contentType->isCsv()
        ) {
            $httpResponse = HttpResponse::createResponseFromString(
                httpStatusCode: $httpStatusCode,
                contentString: HttpErrorResponseContent::createTextResponseContent(
                    errorMessage: $errorMessage,
                    errorCode: $errorCode,
                    additionalInfo: $this->htmlReplacementCollection->getArrayObject(),
                )->content,
                contentType: $contentType,
            );
            $httpResponse->sendAndExit();
        }
        $httpResponse = HttpResponse::createHtmlResponse(
            httpStatusCode: $httpStatusCode,
            htmlContent: $this->getHtmlContent(
                htmlFileName: $htmlFileName,
            ),
            cspPolicySettings: $this->getContext()->cspPolicySettings,
            nonce: $this->getContext()->cspNonce->value,
        );
        $httpResponse->sendAndExit();
    }

    private function getHtmlContent(string $htmlFileName): string
    {
        $core = $this->getContext()->core;
        $contentPath = $core->errorDocsDirectory . $htmlFileName;
        if (!file_exists(filename: $contentPath)) {
            return 'Missing error html file ' . $contentPath;
        }
        $htmlReplacementCollection = $this->htmlReplacementCollection;
        $requestHandler = $this->requestHandler;
        $htmlReplacementCollection->addHtml(
            identifier: 'copyright',
            html: $core->renderCopyrightYear(),
        );
        $language = $requestHandler?->language;
        $htmlReplacementCollection->addHtml(
            identifier: 'language',
            html: $language === null ? 'en' : $language->code,
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'langRoot',
            html: $requestHandler === null ? '/' : $requestHandler->getLanguageRoot(),
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'charset',
            html: 'UTF-8',
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'cspNonce',
            html: $this->getContext()->cspNonce->value,
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'csrfField',
            html: CsrfToken::renderAsHiddenPostField(),
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'robots',
            html: 'noindex,nofollow',
        );
        $htmlReplacementCollection->set(
            identifier: 'pageTitle',
            htmlReplacement: $htmlReplacementCollection->has(identifier: 'title') ? $htmlReplacementCollection->get(
                identifier: 'title',
            ) : HtmlReplacement::html(html: 'Error'),
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'bodyClassName',
            html: 'body-' . pathinfo(path: $htmlFileName)['filename'],
        );
        $htmlReplacementCollection->addHtml(
            identifier: 'requestedFileName',
            html: $requestHandler?->fileName,
        );
        if (
            $requestHandler !== null
            && $core->availableLanguages->isMultiLang()
            && !LocaleHandler::isRegistered()
        ) {
            $this->loadLocalizedText(requestHandler: $requestHandler, core: $core);
        }

        return new HtmlSnippet(
            htmlSnippetFilePath: $contentPath,
            replacements: $htmlReplacementCollection,
        )->render();
    }

    private function loadLocalizedText(
        RequestHandler $requestHandler,
        Core $core,
    ): void {
        $localeHandler = new LocaleHandler(
            language: $requestHandler->language,
            availableLanguages: $core->availableLanguages,
        );
        LocaleHandler::register(localeHandler: $localeHandler);
        $defaultRouteForLanguage = $requestHandler->defaultRoutesByLanguage->getRouteForLanguage(
            languageCode: $requestHandler->language->code,
        );
        $defaultRouteForLanguage->loadLocalizedText(fileTitle: '', localeHandler: $localeHandler);
    }

    protected function sendNotFoundHttpResponseAndExit(Throwable $throwable): void
    {
        $this->sendHttpResponseAndExit(
            httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND,
            errorMessage: $throwable->getMessage(),
            errorCode: $throwable->getCode(),
            htmlFileName: 'notFound.html',
        );
    }

    protected function sendUnauthorizedHttpResponseAndExit(Throwable $throwable): void
    {
        $this->sendHttpResponseAndExit(
            httpStatusCode: HttpStatusCodeEnum::HTTP_UNAUTHORIZED,
            errorMessage: $throwable->getMessage(),
            errorCode: $throwable->getCode(),
            htmlFileName: 'unauthorized.html',
        );
    }

    protected function sendDefaultHttpResponseAndExit(Throwable $throwable): void
    {
        $this->sendHttpResponseAndExit(
            httpStatusCode: HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR,
            errorMessage: 'Internal Server Error',
            errorCode: $throwable->getCode(),
            htmlFileName: 'default.html',
        );
    }
}
