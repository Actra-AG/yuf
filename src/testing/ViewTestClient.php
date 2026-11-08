<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\testing;

use actra\yuf\auth\AuthSession;
use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\AcceptingMailDomainResolver;
use actra\yuf\common\MailDomainResolver;
use actra\yuf\Core;
use actra\yuf\core\BaseView;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HtmlFileExtensionEnum;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\PathVars;
use actra\yuf\core\RecordingResponseSender;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\core\ResolvedRoute;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\ResponseSentException;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewFactory;
use actra\yuf\form\FormContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use Closure;
use LogicException;
use Override;

/**
 * Runs the views of an HTML route of a project in tests, like a browser: one session for all requests (multi-step
 * forms, CSRF tokens, logins), a request per call (host, method, path, query, post, cookies) and the real view
 * pipeline (`ContentHandler::processRequest()`: language files, path variables, `execute()`, rendering of the
 * `HtmlDocument`). Redirects and other responses that end the script are recorded, the work registered with
 * `afterResponse()` runs after the view (docs/testing.md).
 */
final class ViewTestClient
{
    public readonly Session $session;
    private readonly string $documentRoot;

    /**
     * @param string $viewDirectory The view directory of the project (`Core::$viewDirectory`, e.g.
     *     `<root>/app/view/`), with a trailing slash; the views of the group are in `<viewDirectory><viewGroup>/`
     * @param string $viewGroup The view group of the route (`App::withHtmlRoute(viewGroup:)`)
     * @param string $templateBaseDirectory The base directory of the templates (`Core::$baseDirectory`)
     * @param string $cacheDirectory A directory for the compiled templates of the test, with a trailing slash
     * @param string $host The host of the requests
     * @param ?Language $language The language of the route (its language files are loaded), `null` for none
     * @param string $routePath The path of the route (`/`, `/de/`)
     * @param string $viewClassPrefix The namespace prefix of the views of `ClassNameViewFactory`
     * @param ?string $documentRoot The document root, with a trailing slash; `null`: the template base directory
     * @param ?string $snippetsDirectory The directory of the `snippet` tags; `null`: `snippets/` next to the view
     *     directory (`<root>/app/snippets/`, the default of `Core`)
     * @param list<TemplateTag> $templateTags The own template tags of the project
     * @param string $remoteAddress The IP address of the visitor
     */
    public function __construct(
        private readonly string $viewDirectory,
        private readonly string $viewGroup,
        private readonly string $templateBaseDirectory,
        private readonly string $cacheDirectory,
        private readonly string $host = 'example.com',
        private readonly ?Language $language = null,
        private readonly string $routePath = '/',
        private readonly string $viewClassPrefix = Core::APP_CLASS_PREFIX,
        ?string $documentRoot = null,
        private readonly ?string $snippetsDirectory = null,
        private readonly array $templateTags = [],
        private readonly string $remoteAddress = '192.0.2.1',
        private readonly MailDomainResolver $mailDomainResolver = new AcceptingMailDomainResolver(),
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->session = new Session(storage: new ArraySessionStorage());
        $this->documentRoot = $documentRoot ?? $templateBaseDirectory;
    }

    /**
     * The CSRF token of the session, as a form renders it (`CsrfTokenSource::FIELD_NAME`).
     */
    public function getCsrfToken(): string
    {
        return new SessionCsrfTokenSource(session: $this->session)->getToken();
    }

    /**
     * Runs a view for a request of `<routePath><fileTitle>.html`: the factory creates the view of the context (as a
     * `ViewFactory` of the route would; `null` renders the content file without view). The posted fields of a POST
     * get the CSRF token of the session unless they have one.
     *
     * @param Closure(ViewContext): ?BaseView $viewFactory
     * @param array<string, string|array<array-key, mixed>> $query
     * @param array<string, string|array<array-key, mixed>> $post
     * @param array<string, string> $cookies
     * @param list<string> $pathVars The path variables after the file title (`page-2.html`: `['2']`)
     * @param bool $runAfterResponseCallbacks Whether the work registered with `afterResponse()` runs after the view
     *
     * @throws LogicException if the view neither renders nor sends a response
     */
    public function run(
        Closure $viewFactory,
        string $fileTitle,
        RequestMethodEnum $method = RequestMethodEnum::GET,
        array $query = [],
        array $post = [],
        array $cookies = [],
        ?string $fileGroup = null,
        array $pathVars = [],
        bool $runAfterResponseCallbacks = true,
    ): ViewTestResult {
        $responseSender = new RecordingResponseSender();
        $httpRequest = $this->createRequest(
            fileTitle: $fileTitle,
            method: $method,
            query: $query,
            post: $post,
            cookies: $cookies,
            fileGroup: $fileGroup,
            pathVars: $pathVars,
        );
        $localeHandler = $this->createLocaleHandler();
        $contentHandler = new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create());
        $recordingFactory = new class ($viewFactory) implements ViewFactory {
            public ?ViewContext $context = null;

            /**
             * @param Closure(ViewContext): ?BaseView $viewFactory
             */
            public function __construct(private readonly Closure $viewFactory) {}

            #[Override]
            public function createView(ViewContext $context): ?BaseView
            {
                $this->context = $context;

                return ($this->viewFactory)($context);
            }
        };
        $fileName = HtmlFileExtensionEnum::HTML->createFileName(
            fileTitle: implode(separator: '-', array: [$fileTitle, ...$pathVars]),
        );
        try {
            $contentHandler->processRequest(
                resolvedRoute: new ResolvedRoute(
                    route: $this->createRoute(viewFactory: $recordingFactory),
                    language: $this->language,
                    fileName: $fileName,
                    fileGroup: $fileGroup,
                    fileTitle: $fileTitle,
                    fileExtension: HtmlFileExtensionEnum::HTML->value,
                    routeVariables: [],
                    pathVars: [$fileTitle, ...$pathVars],
                ),
                localeHandler: $localeHandler,
                templateEngine: $this->createTemplateEngine(localeHandler: $localeHandler),
                httpRequest: $httpRequest,
                session: $this->session,
                sessionHandler: null,
                formContext: $this->createFormContext(httpRequest: $httpRequest),
                documentRoot: $this->documentRoot,
                copyright: $this->clock->now()->format(format: 'Y'),
                robots: 'noindex, nofollow',
                responseSender: $responseSender,
            );
        } catch (ResponseSentException) {
            // The view ended the script with a response
        }
        if ($runAfterResponseCallbacks) {
            $responseSender->runAfterResponseCallbacks();
        }
        $context = $recordingFactory->context;
        if ($context === null) {
            throw new LogicException(message: 'The view factory was not called.');
        }
        if ($responseSender->sentResponse === null && !$contentHandler->hasContent()) {
            throw new LogicException(message: 'The view neither rendered content nor sent a response.');
        }

        return new ViewTestResult(
            sentResponse: $responseSender->sentResponse,
            content: $responseSender->sentResponse === null ? $contentHandler->getContent() : '',
            httpStatusCode: $contentHandler->httpStatusCode,
            context: $context,
        );
    }

    /**
     * A context of a request for code that needs one without running a view (e.g. to create a service of a view or
     * to read the login of the session). Its HTML document is not available (`ContentHandler::getHtmlDocument()`).
     *
     * @param array<string, string|array<array-key, mixed>> $query
     * @param array<string, string|array<array-key, mixed>> $post
     * @param array<string, string> $cookies
     * @param list<string> $pathVars
     */
    public function createContext(
        string $fileTitle,
        RequestMethodEnum $method = RequestMethodEnum::GET,
        array $query = [],
        array $post = [],
        array $cookies = [],
        ?string $fileGroup = null,
        array $pathVars = [],
        ResponseSender $responseSender = new RecordingResponseSender(),
    ): ViewContext {
        $httpRequest = $this->createRequest(
            fileTitle: $fileTitle,
            method: $method,
            query: $query,
            post: $post,
            cookies: $cookies,
            fileGroup: $fileGroup,
            pathVars: $pathVars,
        );
        $localeHandler = $this->createLocaleHandler();

        return new ViewContext(
            httpRequest: $httpRequest,
            session: $this->session,
            sessionHandler: null,
            authSession: new AuthSession(session: $this->session),
            formContext: $this->createFormContext(httpRequest: $httpRequest),
            route: $this->createRoute(viewFactory: null),
            fileGroup: $fileGroup,
            fileTitle: $fileTitle,
            pathVars: new PathVars(values: [$fileTitle, ...$pathVars]),
            content: new ContentHandler(contentType: ContentType::createHtml(), cspNonce: CspNonce::create()),
            locale: $localeHandler,
            templateEngine: $this->createTemplateEngine(localeHandler: $localeHandler),
            documentRoot: $this->documentRoot,
            responseSender: $responseSender,
        );
    }

    /**
     * @param array<string, string|array<array-key, mixed>> $query
     * @param array<string, string|array<array-key, mixed>> $post
     * @param array<string, string> $cookies
     * @param list<string> $pathVars
     */
    private function createRequest(
        string $fileTitle,
        RequestMethodEnum $method,
        array $query,
        array $post,
        array $cookies,
        ?string $fileGroup,
        array $pathVars,
    ): HttpRequest {
        if ($method === RequestMethodEnum::POST && !array_key_exists(key: CsrfTokenSource::FIELD_NAME, array: $post)) {
            $post[CsrfTokenSource::FIELD_NAME] = $this->getCsrfToken();
        }
        $queryString = http_build_query(data: $query, encoding_type: PHP_QUERY_RFC3986);
        $path = $this->routePath . ($fileGroup === null ? '' : $fileGroup . '/')
            . HtmlFileExtensionEnum::HTML->createFileName(
                fileTitle: implode(separator: '-', array: [$fileTitle, ...$pathVars]),
            );

        return new HttpRequest(
            host: $this->host,
            method: $method,
            uri: $path . ($queryString === '' ? '' : '?' . $queryString),
            queryString: $queryString,
            port: 443,
            serverName: $this->host,
            remoteAddress: $this->remoteAddress,
            headers: ['user-agent' => 'yuf ViewTestClient'],
            cookies: $cookies,
            queryParameters: $query,
            postParameters: $post,
        );
    }

    private function createRoute(?ViewFactory $viewFactory): Route
    {
        return new Route(
            path: $this->routePath,
            viewDirectory: $this->viewDirectory,
            viewClassPrefix: $this->viewClassPrefix,
            viewGroup: $this->viewGroup,
            defaultFileName: 'index.html',
            defaultContentType: ContentType::createHtml(),
            language: $this->language,
            acceptedExtension: HtmlFileExtensionEnum::HTML->value,
            viewFactory: $viewFactory,
        );
    }

    private function createLocaleHandler(): LocaleHandler
    {
        return new LocaleHandler(
            language: $this->language,
            availableLanguages: new LanguageCollection(languages: $this->language === null ? [] : [$this->language]),
        );
    }

    private function createTemplateEngine(LocaleHandler $localeHandler): TemplateEngine
    {
        return new TemplateEngine(
            cache: new DirectoryTemplateCache(
                cacheDirectory: $this->cacheDirectory,
                templateBaseDirectory: $this->templateBaseDirectory,
                checkTemplateChanges: true,
            ),
            tags: TemplateTagCollection::createDefault(
                localeHandler: $localeHandler,
                snippetsDirectory: $this->snippetsDirectory ?? dirname(path: $this->viewDirectory) . '/snippets/',
                clock: $this->clock,
                ownTags: $this->templateTags,
            ),
        );
    }

    private function createFormContext(HttpRequest $httpRequest): FormContext
    {
        return new FormContext(
            httpRequest: $httpRequest,
            csrfTokenSource: new SessionCsrfTokenSource(session: $this->session),
            mailDomainResolver: $this->mailDomainResolver,
        );
    }
}
