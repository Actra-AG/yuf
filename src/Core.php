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
 * The application of a request: reads the environment settings, creates the directories, registers the autoloader and
 * the error handler, creates the request and prepares the response. One per process (the autoloader and the error
 * handler are global); the guard `$isInitialized` is the only static state.
 */
final class Core
{
    public const string APP_CLASS_PREFIX = 'app';
    private static bool $isInitialized = false;
    private ?HttpResponse $httpResponse = null;
    // Becomes a constructor argument with the explicit constructor of Core
    private ResponseSender $responseSender;
    private readonly string $logEmailRecipient;

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
    public private(set) string $baseDirectory = '';
    public private(set) string $appDirectory = '';
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

    public function __construct(
        string $envFilePath,
        public readonly int $copyrightYear,
        string $autoloaderPath = __DIR__ . '/../../autoloader/src/Autoloader.php',
        string $baseDirectory = '{DOCUMENT_ROOT}../',
        string $appDirectory = '{BASE_DIRECTORY}/app/',
        string $cacheDirectory = '{APP_DIRECTORY}cache/',
        string $errorDocsDirectory = '{APP_DIRECTORY}error_docs/',
        string $logsDirectory = '{APP_DIRECTORY}logs/',
        string $settingsDirectory = '{APP_DIRECTORY}settings/',
        string $snippetsDirectory = '{APP_DIRECTORY}snippets/',
        string $viewDirectory = '{APP_DIRECTORY}view/',
    ) {
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
        $this->environmentSettings = $environment;
        error_reporting(error_level: $environment->errorReporting);
        date_default_timezone_set(timezoneId: $environment->timeZone);
        $this->documentRoot = Core::readDocumentRoot();
        $this->frameworkDirectory = __DIR__ . DIRECTORY_SEPARATOR;
        $this->baseDirectory = $this->createIfNotExists(path: $baseDirectory);
        $this->appDirectory = $this->createIfNotExists(path: $appDirectory);
        $this->cacheDirectory = $this->createIfNotExists(path: $cacheDirectory);
        $this->errorDocsDirectory = $this->createIfNotExists(path: $errorDocsDirectory);
        $this->logDirectory = $this->createIfNotExists(path: $logsDirectory);
        $this->settingsDirectory = $this->createIfNotExists(path: $settingsDirectory);
        $this->snippetsDirectory = $this->createIfNotExists(path: $snippetsDirectory);
        $this->viewDirectory = $this->createIfNotExists(path: $viewDirectory);
        $autoloader->addPath(
            autoloaderPath: new AutoloaderPath(
                path: $this->appDirectory,
                prefix: Core::APP_CLASS_PREFIX . '\\',
            ),
        );
        $this->responseSender = new NativeResponseSender();
        new ErrorHandler()->register();
        try {
            $this->httpRequest = HttpRequest::fromGlobals();
        } catch (UnsupportedRequestMethodException) {
            header(header: HttpStatusCodeEnum::HTTP_METHOD_NOT_ALLOWED->getStatusHeader());
            exit;
        }
        $this->formContext = new FormContext(httpRequest: $this->httpRequest, csrfTokenSource: null);
        if (!$this->httpRequest->isSsl()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->httpRequest->getUrl(protocol: ProtocolEnum::HTTPS),
                httpRequest: $this->httpRequest,
            );
        }
        $this->allowedDomains = $environment->allowedDomains;
        $this->availableLanguages = new LanguageCollection();
        $this->debug = $environment->debug;
        $this->robots = $environment->robots;
        $this->logEmailRecipient = $environment->logEmailRecipient;
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
    private function createIfNotExists(string $path): string
    {
        $path = DirectoryPathResolver::resolve(
            path: $path,
            documentRoot: $this->documentRoot,
            baseDirectory: $this->baseDirectory,
            appDirectory: $this->appDirectory,
        );
        if (!is_dir(filename: $path) && !mkdir(directory: $path, recursive: true) && !is_dir(filename: $path)) {
            throw new RuntimeException(message: 'Cannot create the directory ' . $path);
        }

        return $path;
    }

    /**
     * @param list<TemplateTag> $templateTags The own tags of the project, known to views, snippets, tables and error
     *                                        pages; a name of a built-in or another own tag throws
     *
     * @throws InvalidArgumentException for an invalid template tag name
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
        $requestHandler->resolveRoute();
        $localeHandler = new LocaleHandler(
            language: $requestHandler->language,
            availableLanguages: $this->availableLanguages,
        );
        $localeHandler->applySystemLocale();
        $templateEngine = $this->createTemplateEngine(localeHandler: $localeHandler);
        $defaultContentType = $requestHandler->route->defaultContentType;
        if ($defaultContentType === null) {
            throw new LogicException(
                message: 'The route "' . $requestHandler->route->path . '" has no default content type.',
            );
        }
        $contentHandler = new ContentHandler(contentType: $defaultContentType, cspNonce: $cspNonce);
        $exceptionHandler->setContentHandler(contentHandler: $contentHandler);
        $contentHandler->processRequest(
            requestHandler: $requestHandler,
            localeHandler: $localeHandler,
            core: $this,
            templateEngine: $templateEngine,
            responseSender: $this->responseSender,
        );
        $this->httpResponse = new ContentResponseFactory(
            httpRequest: $this->httpRequest,
            cspPolicySettings: $this->cspPolicySettings,
            language: $requestHandler->language,
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
