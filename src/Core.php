<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf;

use actra\yuf\autoloader\Autoloader;
use actra\yuf\autoloader\AutoloaderPathModel;
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
    private static ?Core $instance = null;
    private static ?HttpResponse $httpResponse = null;
    private static array $config;

    public readonly string $documentRoot;
    public readonly string $frameworkDirectory;
    public readonly string $siteDirectory;
    public readonly string $cacheDirectory;
    public readonly string $errorDocsDirectory;
    public readonly string $logDirectory;
    public readonly string $settingsDirectory;
    public readonly string $snippetsDirectory;
    public readonly string $viewDirectory;
    public readonly array $allowedDomains;
    public readonly LanguageCollection $availableLanguages;
    public readonly bool $debug;
    public readonly CspPolicySettingsModel $cspPolicySettingsModel;
    public readonly string $robots;


    public function __construct(
        string                 $envFilePath,
        public readonly int    $copyrightYear,
        public readonly string $siteDirectoryName = 'site',
        string                 $cacheDirectoryName = 'cache',
        string                 $errorDocsDirectoryName = 'error_docs',
        string                 $logsDirectoryName = 'logs',
        string                 $settingsDirectoryName = 'settings',
        string                 $snippetsDirectoryName = 'snippets',
        public readonly string $viewDirectoryName = 'view',
        string                 $frameworkFilePathRemove = ''
    )
    {
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
        $this->frameworkDirectory = $frameworkDirectory = dirname(path: __FILE__) . DIRECTORY_SEPARATOR;
        $this->siteDirectory = $this->createIfNotExists(
            path: $this->documentRoot . $siteDirectoryName . DIRECTORY_SEPARATOR
        );
        $this->cacheDirectory = $this->createIfNotExists(
            path: $this->siteDirectory . $cacheDirectoryName . DIRECTORY_SEPARATOR
        );
        $this->errorDocsDirectory = $this->createIfNotExists(
            path: $this->siteDirectory . $errorDocsDirectoryName . DIRECTORY_SEPARATOR
        );
        $this->logDirectory = $this->createIfNotExists(
            path: $this->siteDirectory . $logsDirectoryName . DIRECTORY_SEPARATOR
        );
        $this->settingsDirectory = $this->createIfNotExists(
            path: $this->siteDirectory . $settingsDirectoryName . DIRECTORY_SEPARATOR
        );
        $this->snippetsDirectory = $this->createIfNotExists(
            path: $this->siteDirectory . $snippetsDirectoryName . DIRECTORY_SEPARATOR
        );
        $this->viewDirectory = $this->createIfNotExists(
            path: $this->siteDirectory . $viewDirectoryName . DIRECTORY_SEPARATOR
        );

        require_once $frameworkDirectory . 'autoloader' . DIRECTORY_SEPARATOR . 'Autoloader.php';
        $autoloader = Autoloader::register(cacheFilePath: $this->cacheDirectory . 'cache.autoload');
        require_once $frameworkDirectory . 'autoloader' . DIRECTORY_SEPARATOR . 'AutoloaderPathModel.php';
        $autoloader->addPath(
            autoloaderPathModel: new AutoloaderPathModel(
                name: 'yuf',
                path: __DIR__ . DIRECTORY_SEPARATOR,
                mode: AutoloaderPathModel::MODE_NAMESPACE,
                fileSuffixList: ['.class.php', '.php', '.interface.php'],
                phpFilePathRemove: 'actra/yuf/'
            )
        );
        $autoloader->addPath(
            autoloaderPathModel: new AutoloaderPathModel(
                name: 'public',
                path: $this->documentRoot,
                mode: AutoloaderPathModel::MODE_NAMESPACE,
                fileSuffixList: ['.class.php', '.php', '.interface.php'],
                phpFilePathRemove: $frameworkFilePathRemove
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
        if (!file_exists(filename: $path)) {
            mkdir(
                directory: $path,
                recursive: true
            );
        }

        return $path;
    }

    public function prepareHttpResponse(
        ?Logger                      $logger = null,
        RouteCollection              $routeCollection = new RouteCollection(),
        ?ExceptionHandler            $individualExceptionHandler = null,
        CspPolicySettingsModel       $cspPolicySettingsModel = new CspPolicySettingsModel(),
        false|AbstractSessionHandler $individualSessionHandler = new FileSessionHandler(
            sessionSettingsModel: new SessionSettingsModel()
        )
    ): HttpResponse
    {
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
