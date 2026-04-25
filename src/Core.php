<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf;

use actra\autoloader\Autoloader;
use actra\autoloader\AutoloaderPath;
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
use actra\yuf\exception\NotFoundException;
use actra\yuf\security\CspNonce;
use actra\yuf\security\CspPolicySettingsModel;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\FileSessionHandler;
use actra\yuf\session\SessionSettingsModel;
use LogicException;

class Core
{
    public const string APP_CLASS_PREFIX = 'app';
    private static ?Core $instance = null;
    private static ?HttpResponse $httpResponse = null;
    private static array $config;

    public readonly string $documentRoot;
    public readonly string $frameworkDirectory;
    private(set) string $baseDirectory = '';
    private(set) string $appDirectory = '';
    public readonly string $cacheDirectory;
    public readonly string $errorDocsDirectory;
    public readonly string $logDirectory;
    public readonly string $settingsDirectory;
    public readonly string $snippetsDirectory;
    public readonly string $viewDirectory;
    public readonly array $allowedDomains;
    public readonly LanguageCollection $availableLanguages;
    public readonly bool $debug;
    public readonly ?CspPolicySettingsModel $cspPolicySettingsModel;
    public readonly string $robots;

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
        string $viewDirectory = '{APP_DIRECTORY}view/'
    ) {
        if (!is_null(value: Core::$instance)) {
            throw new LogicException(message: 'Core is already initialized');
        }
        Core::$instance = $this;
        Core::$config = require_once $envFilePath;
        error_reporting(error_level: Core::$config['defaultErrorReporting']);
        date_default_timezone_set(timezoneId: Core::$config['defaultTimeZone']);
        $this->documentRoot = str_replace(
            search: DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            replace: DIRECTORY_SEPARATOR,
            subject: $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR
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
                prefix: 'actra\\yuf\\'
            )
        );
        $autoloader->addPath(
            autoloaderPath: new AutoloaderPath(
                path: $this->appDirectory,
                prefix: Core::APP_CLASS_PREFIX . '\\'
            )
        );
        ErrorHandler::register();
        if (!HttpRequest::isSSL()) {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: HttpRequest::getURL(
                    protocol: HttpRequest::PROTOCOL_HTTPS
                )
            );
        }
        $this->allowedDomains = Core::$config['allowedDomains'];
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
            subject: $path
        );
        $path = $this->getAbsolutePath(path: $path);
        if (!str_ends_with(
            haystack: $path,
            needle: DIRECTORY_SEPARATOR
        )) {
            $path .= DIRECTORY_SEPARATOR;
        }
        if (!file_exists(filename: $path)) {
            mkdir(
                directory: $path,
                recursive: true
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
                string: $path
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
                array: $safe
            );
    }

    public function prepareHttpResponse(
        ?Logger $logger = null,
        RouteCollection $routeCollection = new RouteCollection(),
        ?ExceptionHandler $individualExceptionHandler = null,
        ?CspPolicySettingsModel $cspPolicySettingsModel = new CspPolicySettingsModel(),
        false|AbstractSessionHandler $individualSessionHandler = new FileSessionHandler(
            sessionSettingsModel: new SessionSettingsModel()
        )
    ): HttpResponse {
        if (!is_null(value: Core::$httpResponse)) {
            throw new LogicException(message: 'The HttpResponse is already prepared');
        }
        if (is_null(value: $logger)) {
            $logger = new Logger(
                logEmailRecipient: Core::$config['logEmailRecipient'],
                logDirectory: $this->logDirectory
            );
        }
        $this->cspPolicySettingsModel = $cspPolicySettingsModel;
        Logger::register(logger: $logger);
        ExceptionHandler::register(individualExceptionHandler: $individualExceptionHandler);
        AbstractSessionHandler::register(individualSessionHandler: $individualSessionHandler);
        if (!$routeCollection->hasRoutes()) {
            throw new LogicException(message: 'There must be at least one route');
        }
        RequestHandler::register(routeCollection: $routeCollection);
        LocaleHandler::register();
        $contentHandler = ContentHandler::register();
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
                cspPolicySettingsModel: $contentHandler->suppressCspHeader ? null : $this->cspPolicySettingsModel,
                nonce: CspNonce::get()
            );
        }
        return Core::$httpResponse = HttpResponse::createResponseFromString(
            httpStatusCode: $httpStatusCode,
            contentString: $content,
            contentType: $contentType
        );
    }

    public static function config(string $key): mixed
    {
        return Core::$config[$key];
    }

    public static function get(): Core
    {
        return Core::$instance;
    }

    public function renderCopyrightYear(): string
    {
        $copyrightYear = $this->copyrightYear;
        if ($copyrightYear < (int)date(format: 'Y')) {
            return $copyrightYear . '-' . date(format: 'Y');
        }
        return (string)$copyrightYear;
    }
}