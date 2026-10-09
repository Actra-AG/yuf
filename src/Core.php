<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf;

use actra\autoloader\Autoloader;
use actra\autoloader\AutoloaderPath;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentResponseFactory;
use actra\yuf\core\CoreSettings;
use actra\yuf\core\DirectoryPathResolver;
use actra\yuf\core\EnvironmentSettings;
use actra\yuf\core\ErrorHandler;
use actra\yuf\core\FileLogger;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\Logger;
use actra\yuf\core\NativeResponseSender;
use actra\yuf\core\ProtocolEnum;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\RouteCollection;
use actra\yuf\core\UnsupportedRequestMethodException;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\ExceptionHandlerContext;
use actra\yuf\form\FormContext;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\FileSessionHandler;
use actra\yuf\session\NativeSessionStorage;
use actra\yuf\session\Session;
use actra\yuf\session\SessionSettings;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use UnexpectedValueException;

/**
 * The application of a request: its settings, the request and the response that is prepared from them.
 *
 * Production code creates it with `Core::fromEnvironment()`, which does everything that is global and happens once per
 * process: it registers the autoloader and the error handler, reads the environment file, sets `error_reporting()`
 * and the time zone, creates the directories and the request from the PHP globals. The constructor takes explicit
 * settings and a request and touches no globals, so tests build `Core` directly. `fromEnvironment()` is not unit
 * tested for the same reason (global state that can only be set once per process); the guard `$isInitialized` is the
 * only static state.
 */
final class Core
{
    public const string APP_CLASS_PREFIX = 'app';
    private static bool $isInitialized = false;
    private ?HttpResponse $httpResponse = null;
    private readonly string $logEmailRecipient;

    public readonly int $copyrightYear;
    public readonly string $documentRoot;
    /** The typed settings of Core and, through its getters, the own keys of the project in `.env.php`. */
    public readonly EnvironmentSettings $environmentSettings;
    /** The request of this process: create other requests only in tests. */
    public readonly HttpRequest $httpRequest;
    /**
     * The session handler of the request (`null` with `individualSessionHandler: false`); set by
     * `prepareHttpResponse()`.
     */
    public private(set) ?AbstractSessionHandler $sessionHandler = null;
    /** The session of the request (`null` without sessions); set by `prepareHttpResponse()`. */
    public private(set) ?Session $session = null;
    /**
     * The request and the CSRF token source (none without sessions, and before `prepareHttpResponse()`) for the forms
     * of the request.
     */
    public private(set) FormContext $formContext;
    public readonly string $frameworkDirectory;
    public readonly string $baseDirectory;
    public readonly string $appDirectory;
    public readonly string $cacheDirectory;
    public readonly string $errorDocsDirectory;
    public readonly string $logDirectory;
    public readonly string $settingsDirectory;
    public readonly string $snippetsDirectory;
    public readonly string $viewDirectory;
    /** @var list<string> */
    public readonly array $allowedDomains;
    public readonly LanguageCollection $availableLanguages;
    public readonly bool $debug;
    public private(set) ?CspPolicySettings $cspPolicySettings = null;
    public readonly string $robots;
    /** @var list<TemplateTag> */
    private array $templateTags = [];

    /**
     * Touches no globals and no files: the settings and the request are given, so this is the constructor for tests.
     * Production code uses `fromEnvironment()`.
     *
     * @param ResponseSender $responseSender Sends the responses of the exception handler, the route "/" and the views
     */
    public function __construct(
        CoreSettings $settings,
        HttpRequest $httpRequest,
        private readonly ResponseSender $responseSender = new NativeResponseSender(),
    ) {
        $environment = $settings->environmentSettings;
        $this->environmentSettings = $environment;
        $this->copyrightYear = $settings->copyrightYear;
        $this->documentRoot = $settings->documentRoot;
        $this->frameworkDirectory = $settings->frameworkDirectory;
        $this->baseDirectory = $settings->baseDirectory;
        $this->appDirectory = $settings->appDirectory;
        $this->cacheDirectory = $settings->cacheDirectory;
        $this->errorDocsDirectory = $settings->errorDocsDirectory;
        $this->logDirectory = $settings->logDirectory;
        $this->settingsDirectory = $settings->settingsDirectory;
        $this->snippetsDirectory = $settings->snippetsDirectory;
        $this->viewDirectory = $settings->viewDirectory;
        $this->httpRequest = $httpRequest;
        $this->formContext = new FormContext(httpRequest: $httpRequest, csrfTokenSource: null);
        $this->allowedDomains = $environment->allowedDomains;
        $this->availableLanguages = new LanguageCollection();
        $this->debug = $environment->debug;
        $this->robots = $environment->robots;
        $this->logEmailRecipient = $environment->logEmailRecipient;
    }

    /**
     * Creates the application of the request of this process: registers the autoloader (own and `app` classes), reads
     * the environment file, sets `error_reporting()` and the time zone, resolves and creates the directories,
     * registers the error handler and creates the request from the PHP globals. A request with an unsupported method
     * is answered with a 405 response and ends the script. Call it once, as the first statement of the front
     * controller; it is not unit tested (global, once per process).
     *
     * The directory arguments may contain the placeholders `{DOCUMENT_ROOT}`, `{BASE_DIRECTORY}` and
     * `{APP_DIRECTORY}`.
     *
     * @throws LogicException if called twice
     * @throws UnexpectedValueException if the environment file is invalid or `DOCUMENT_ROOT` is not set
     * @throws RuntimeException if a directory cannot be created
     */
    public static function fromEnvironment(
        string $envFilePath,
        int $copyrightYear,
        string $autoloaderPath = __DIR__ . '/../../autoloader/src/Autoloader.php',
        string $baseDirectory = '{DOCUMENT_ROOT}../',
        string $appDirectory = '{BASE_DIRECTORY}/app/',
        string $cacheDirectory = '{APP_DIRECTORY}cache/',
        string $errorDocsDirectory = '{APP_DIRECTORY}error_docs/',
        string $logsDirectory = '{APP_DIRECTORY}logs/',
        string $settingsDirectory = '{APP_DIRECTORY}settings/',
        string $snippetsDirectory = '{APP_DIRECTORY}snippets/',
        string $viewDirectory = '{APP_DIRECTORY}view/',
    ): Core {
        if (Core::$isInitialized) {
            throw new LogicException(message: 'Core is already initialized');
        }
        Core::$isInitialized = true;
        // The classes of yuf are needed for the settings and the directories, so they are registered first
        require_once $autoloaderPath;
        $autoloader = Autoloader::register();
        $autoloader->addPath(
            autoloaderPath: new AutoloaderPath(
                path: __DIR__ . DIRECTORY_SEPARATOR,
                prefix: 'actra\\yuf\\',
            ),
        );
        $environment = EnvironmentSettings::fromArray(values: Core::loadEnvironmentFile(path: $envFilePath));
        error_reporting(error_level: $environment->errorReporting);
        date_default_timezone_set(timezoneId: $environment->timeZone);
        $documentRoot = Core::readDocumentRoot();
        $resolvedBaseDirectory = Core::createIfNotExists(
            path: $baseDirectory,
            documentRoot: $documentRoot,
            baseDirectory: '',
            appDirectory: '',
        );
        $resolvedAppDirectory = Core::createIfNotExists(
            path: $appDirectory,
            documentRoot: $documentRoot,
            baseDirectory: $resolvedBaseDirectory,
            appDirectory: '',
        );
        $resolve = static fn(string $path): string => Core::createIfNotExists(
            path: $path,
            documentRoot: $documentRoot,
            baseDirectory: $resolvedBaseDirectory,
            appDirectory: $resolvedAppDirectory,
        );
        $settings = new CoreSettings(
            environmentSettings: $environment,
            copyrightYear: $copyrightYear,
            documentRoot: $documentRoot,
            frameworkDirectory: __DIR__ . DIRECTORY_SEPARATOR,
            baseDirectory: $resolvedBaseDirectory,
            appDirectory: $resolvedAppDirectory,
            cacheDirectory: $resolve(path: $cacheDirectory),
            errorDocsDirectory: $resolve(path: $errorDocsDirectory),
            logDirectory: $resolve(path: $logsDirectory),
            settingsDirectory: $resolve(path: $settingsDirectory),
            snippetsDirectory: $resolve(path: $snippetsDirectory),
            viewDirectory: $resolve(path: $viewDirectory),
        );
        $autoloader->addPath(
            autoloaderPath: new AutoloaderPath(
                path: $settings->appDirectory,
                prefix: Core::APP_CLASS_PREFIX . '\\',
            ),
        );
        new ErrorHandler()->register();
        try {
            $httpRequest = HttpRequest::fromGlobals();
        } catch (UnsupportedRequestMethodException) {
            HttpResponse::createStatusResponse(httpStatusCode: HttpStatusCodeEnum::HTTP_METHOD_NOT_ALLOWED)
                ->sendAndExit();
        }

        return new Core(settings: $settings, httpRequest: $httpRequest);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws UnexpectedValueException if the file does not return an array (or was included before)
     */
    private static function loadEnvironmentFile(string $path): array
    {
        $values = require_once $path;
        if (!is_array(value: $values)) {
            throw new UnexpectedValueException(
                message: 'The environment file ' . $path . ' must return an array of settings, and must not be'
                    . ' included before Core.',
            );
        }

        return $values;
    }

    private static function readDocumentRoot(): string
    {
        if (!array_key_exists(key: 'DOCUMENT_ROOT', array: $_SERVER) || !is_string(value: $_SERVER['DOCUMENT_ROOT'])) {
            throw new UnexpectedValueException(
                message: 'The server variable DOCUMENT_ROOT is not set: Core runs in a web request only.',
            );
        }

        return str_replace(
            search: DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            replace: DIRECTORY_SEPARATOR,
            subject: $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR,
        );
    }

    /**
     * @throws RuntimeException if the directory cannot be created
     */
    private static function createIfNotExists(
        string $path,
        string $documentRoot,
        string $baseDirectory,
        string $appDirectory,
    ): string {
        $path = DirectoryPathResolver::resolve(
            path: $path,
            documentRoot: $documentRoot,
            baseDirectory: $baseDirectory,
            appDirectory: $appDirectory,
        );
        if (!is_dir(filename: $path) && !mkdir(directory: $path, recursive: true) && !is_dir(filename: $path)) {
            throw new RuntimeException(message: 'Cannot create the directory ' . $path);
        }

        return $path;
    }

    /**
     * Prepares the response of the request: a redirect to HTTPS for a request without SSL (303, nothing else is set
     * up for it), else the response of the matching route. Registers the exception handler, so call it once, as the
     * last statement of the front controller before sending the response.
     *
     * The session is not started here, only when the route, the view or a form uses it. A session that was started is
     * written and closed after the view, before the response is built: later writes (destructors, shutdown functions)
     * throw a `LogicException`.
     *
     * @param list<TemplateTag> $templateTags The own tags of the project, known to views, snippets, tables and error
     *                                        pages; a name of a built-in or another own tag throws
     *
     * @throws InvalidArgumentException for an invalid template tag name
     * @throws LogicException if called twice, without a route or for a route without default content type
     */
    public function prepareHttpResponse(
        ?Logger $logger = null,
        RouteCollection $routeCollection = new RouteCollection(),
        ?ExceptionHandler $individualExceptionHandler = null,
        ?CspPolicySettings $cspPolicySettings = new CspPolicySettings(),
        false|AbstractSessionHandler|null $individualSessionHandler = null,
        array $templateTags = [],
    ): HttpResponse {
        if ($this->httpResponse !== null) {
            throw new LogicException(message: 'The HttpResponse is already prepared');
        }
        if (!$this->httpRequest->isSsl()) {
            // Nothing else is needed for the redirect: no session, no exception handler
            $this->httpResponse = HttpResponse::createRedirectResponse(
                relativeOrAbsoluteUri: $this->httpRequest->getUrl(protocol: ProtocolEnum::HTTPS),
                httpRequest: $this->httpRequest,
            );

            return $this->httpResponse;
        }
        $logger ??= new FileLogger(
            logEmailRecipient: $this->logEmailRecipient,
            logDirectory: $this->logDirectory,
            httpRequest: $this->httpRequest,
        );
        $this->cspPolicySettings = $cspPolicySettings;
        // Checked before the exception handler exists: it needs the tags for the error pages, too
        $this->templateTags = $templateTags;
        $this->createTemplateTags(
            localeHandler: new LocaleHandler(language: null, availableLanguages: new LanguageCollection()),
        );
        $cspNonce = CspNonce::create();
        $exceptionHandler = ExceptionHandler::register(
            individualExceptionHandler: $individualExceptionHandler,
            context: new ExceptionHandlerContext(
                logger: $logger,
                cspNonce: $cspNonce,
                cspPolicySettings: $this->cspPolicySettings,
                isDebug: $this->debug,
                httpRequest: $this->httpRequest,
                errorDocsDirectory: $this->errorDocsDirectory,
                copyright: $this->renderCopyrightYear(),
                availableLanguages: $this->availableLanguages,
                createTemplateEngine: $this->createTemplateEngine(...),
                responseSender: $this->responseSender,
            ),
        );
        if ($individualSessionHandler === null) {
            $individualSessionHandler = new FileSessionHandler(
                httpRequest: $this->httpRequest,
                sessionSettings: new SessionSettings(),
                defaultSavePath: $this->cacheDirectory . 'sessions',
            );
        }
        $this->sessionHandler = $individualSessionHandler === false ? null : $individualSessionHandler;
        $this->session = $this->sessionHandler === null
            ? null
            : new Session(storage: new NativeSessionStorage(sessionHandler: $this->sessionHandler));
        $csrfTokenSource = $this->session === null ? null : new SessionCsrfTokenSource(session: $this->session);
        $this->formContext = new FormContext(httpRequest: $this->httpRequest, csrfTokenSource: $csrfTokenSource);
        $exceptionHandler->setSession(session: $this->session, csrfTokenSource: $csrfTokenSource);
        if (!$routeCollection->hasRoutes()) {
            throw new LogicException(message: 'There must be at least one route');
        }
        $requestHandler = new RequestHandler(
            httpRequest: $this->httpRequest,
            routeCollection: $routeCollection,
            availableLanguages: $this->availableLanguages,
            allowedDomains: $this->allowedDomains,
            session: $this->session,
            responseSender: $this->responseSender,
        );
        $exceptionHandler->setRequestHandler(requestHandler: $requestHandler);
        $resolvedRoute = $requestHandler->resolveRoute();
        $localeHandler = new LocaleHandler(
            language: $resolvedRoute->language,
            availableLanguages: $this->availableLanguages,
        );
        $localeHandler->applySystemLocale();
        $templateEngine = $this->createTemplateEngine(localeHandler: $localeHandler);
        $defaultContentType = $resolvedRoute->route->defaultContentType;
        if ($defaultContentType === null) {
            throw new LogicException(
                message: 'The route "' . $resolvedRoute->route->path . '" has no default content type.',
            );
        }
        $contentHandler = new ContentHandler(contentType: $defaultContentType, cspNonce: $cspNonce);
        $exceptionHandler->setContentHandler(contentHandler: $contentHandler);
        $contentHandler->processRequest(
            resolvedRoute: $resolvedRoute,
            localeHandler: $localeHandler,
            templateEngine: $templateEngine,
            httpRequest: $this->httpRequest,
            session: $this->session,
            sessionHandler: $this->sessionHandler,
            formContext: $this->formContext,
            copyright: $this->renderCopyrightYear(),
            robots: $this->robots,
            responseSender: $this->responseSender,
        );
        // Release the lock of the session before the response is built and sent: parallel requests of the user go on
        if ($this->sessionHandler !== null && $this->sessionHandler->isStarted()) {
            if (!$this->sessionHandler->isClosed()) {
                // Only a session the view started anyway: the language alone does not start one (no lock per page)
                $requestHandler->rememberPreferredLanguage(resolvedRoute: $resolvedRoute);
            }
            $this->sessionHandler->writeClose();
        }
        $this->httpResponse = new ContentResponseFactory(
            httpRequest: $this->httpRequest,
            cspPolicySettings: $this->cspPolicySettings,
            language: $resolvedRoute->language,
        )->create(contentHandler: $contentHandler);

        return $this->httpResponse;
    }

    /**
     * Creates the template engine of a request: the compiled templates are cached in the cache directory, `snippet`
     * tags read from the snippets directory and `lang` tags from the given locale handler. Create one per request;
     * the exception handler creates its own for the error pages. The own tags given to `prepareHttpResponse()` are
     * included.
     */
    public function createTemplateEngine(LocaleHandler $localeHandler): TemplateEngine
    {
        return new TemplateEngine(
            cache: new DirectoryTemplateCache(
                cacheDirectory: $this->cacheDirectory,
                templateBaseDirectory: $this->baseDirectory,
                checkTemplateChanges: $this->environmentSettings->checkTemplateChanges,
            ),
            tags: $this->createTemplateTags(localeHandler: $localeHandler),
        );
    }

    private function createTemplateTags(LocaleHandler $localeHandler): TemplateTagCollection
    {
        return TemplateTagCollection::createDefault(
            localeHandler: $localeHandler,
            snippetsDirectory: $this->snippetsDirectory,
            clock: new SystemClock(),
            ownTags: $this->templateTags,
        );
    }

    public function renderCopyrightYear(): string
    {
        $copyrightYear = $this->copyrightYear;
        if ($copyrightYear < (int) date(format: 'Y')) {
            return $copyrightYear . '-' . date(format: 'Y');
        }
        return (string) $copyrightYear;
    }
}
