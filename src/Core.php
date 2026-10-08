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
use actra\yuf\core\ErrorHandler;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\Logger;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\RouteCollection;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\ExceptionHandlerContext;
use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\FileSessionHandler;
use actra\yuf\session\SessionSettings;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use InvalidArgumentException;
use LogicException;

class Core
{
    public const string APP_CLASS_PREFIX = 'app';
    private static bool $isInitialized = false;
    private static ?HttpResponse $httpResponse = null;
    private static array $config;

    public readonly string $documentRoot;
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
    public readonly ?CspPolicySettings $cspPolicySettings;
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
        Core::$config = require_once $envFilePath;
        error_reporting(error_level: Core::$config['defaultErrorReporting']);
        date_default_timezone_set(timezoneId: Core::$config['defaultTimeZone']);
        $this->documentRoot = str_replace(
            search: DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            replace: DIRECTORY_SEPARATOR,
            subject: $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR,
        );
        $this->frameworkDirectory = __DIR__ . DIRECTORY_SEPARATOR;
        $this->baseDirectory = $this->createIfNotExists(path: $baseDirectory);
        $this->appDirectory = $this->createIfNotExists(path: $appDirectory);
        $this->cacheDirectory = $this->createIfNotExists(path: $cacheDirectory);
        $this->errorDocsDirectory = $this->createIfNotExists(path: $errorDocsDirectory);
        $this->logDirectory = $this->createIfNotExists(path: $logsDirectory);
        $this->settingsDirectory = $this->createIfNotExists(path: $settingsDirectory);
        $this->snippetsDirectory = $this->createIfNotExists(path: $snippetsDirectory);
        $this->viewDirectory = $this->createIfNotExists(path: $viewDirectory);
        require_once $autoloaderPath;
        $autoloader = Autoloader::register();
        $autoloader->addPath(
            autoloaderPath: new AutoloaderPath(
                path: __DIR__ . DIRECTORY_SEPARATOR,
                prefix: 'actra\\yuf\\',
            ),
        );
        $autoloader->addPath(
            autoloaderPath: new AutoloaderPath(
                path: $this->appDirectory,
                prefix: Core::APP_CLASS_PREFIX . '\\',
            ),
        );
        ErrorHandler::register();
        if (!HttpRequest::isSsl()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: HttpRequest::getUrl(
                    protocol: HttpRequest::PROTOCOL_HTTPS,
                ),
            );
        }
        /** @var list<string> $allowedDomains */
        $allowedDomains = Core::$config['allowedDomains'];
        $this->allowedDomains = $allowedDomains;
        $this->availableLanguages = new LanguageCollection();
        $this->debug = Core::$config['debug'];
        $this->robots = Core::$config['robots'];
    }

    private function createIfNotExists(string $path): string
    {
        $path = str_replace(
            search: [
                '{DOCUMENT_ROOT}',
                '{BASE_DIRECTORY}',
                '{APP_DIRECTORY}',
                DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            ],
            replace: [
                $this->documentRoot,
                $this->baseDirectory,
                $this->appDirectory,
                DIRECTORY_SEPARATOR,
            ],
            subject: $path,
        );
        $path = $this->getAbsolutePath(path: $path);
        if (!str_ends_with(
            haystack: $path,
            needle: DIRECTORY_SEPARATOR,
        )) {
            $path .= DIRECTORY_SEPARATOR;
        }
        if (!file_exists(filename: $path)) {
            mkdir(
                directory: $path,
                recursive: true,
            );
        }

        return $path;
    }

    private function getAbsolutePath(string $path): string
    {
        $safe = [];
        foreach (
            explode(
                separator: '/',
                string: $path,
            ) as $part
        ) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop(array: $safe);
            } else {
                $safe[] = $part;
            }
        }
        return '/' . implode(
            separator: '/',
            array: $safe,
        );
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
        if (Core::$httpResponse !== null) {
            throw new LogicException(message: 'The HttpResponse is already prepared');
        }
        if ($logger === null) {
            $logger = new Logger(
                logEmailRecipient: Core::$config['logEmailRecipient'],
                logDirectory: $this->logDirectory,
            );
        }
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
                core: $this,
            ),
        );
        if ($individualSessionHandler === null) {
            $individualSessionHandler = new FileSessionHandler(
                sessionSettings: new SessionSettings(),
                defaultSavePath: $this->cacheDirectory . 'sessions',
            );
        }
        AbstractSessionHandler::register(individualSessionHandler: $individualSessionHandler);
        if (!$routeCollection->hasRoutes()) {
            throw new LogicException(message: 'There must be at least one route');
        }
        $requestHandler = new RequestHandler(
            routeCollection: $routeCollection,
            availableLanguages: $this->availableLanguages,
            allowedDomains: $this->allowedDomains,
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
        );
        if (!$contentHandler->hasContent()) {
            throw new NotFoundException();
        }
        $content = $contentHandler->getContent();
        $httpStatusCode = $contentHandler->httpStatusCode;
        $contentType = $contentHandler->getContentType();
        if ($contentType->isHtml()) {
            return Core::$httpResponse = HttpResponse::createHtmlResponse(
                httpStatusCode: $httpStatusCode,
                htmlContent: $content,
                cspPolicySettings: $contentHandler->suppressCspHeader ? null : $this->cspPolicySettings,
                nonce: $cspNonce->value,
            );
        }
        return Core::$httpResponse = HttpResponse::createResponseFromString(
            httpStatusCode: $httpStatusCode,
            contentString: $content,
            contentType: $contentType,
        );
    }

    public static function config(string $key): mixed
    {
        return Core::$config[$key];
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
