<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\ContentType;
use actra\yuf\core\HtmlFileExtensionEnum;
use actra\yuf\core\HttpResponse;
use actra\yuf\core\Language;
use actra\yuf\core\LanguageSwitch;
use actra\yuf\core\Logger;
use actra\yuf\core\LoginRedirect;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewFactory;
use actra\yuf\exception\ExceptionHandler;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\mailer\AbstractMailer;
use actra\yuf\security\CspPolicySettings;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\SessionSettings;
use actra\yuf\template\tag\TemplateTag;
use Closure;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use UnexpectedValueException;

/**
 * The short way to set up the front controller: languages and routes in one line each, everything else from
 * `.env.php`. Builds `Language`, `Route` and `RouteCollection` and calls `Core::prepareHttpResponse()`; it adds no
 * behaviour, so `$app->core` and `withRoute()` cover every special case.
 *
 * ```php
 * App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)
 *     ->withLanguage(code: 'de', locale: 'de_CH.UTF-8')
 *     ->withHtmlRoute(path: '/', viewGroup: 'frontend')
 *     ->run();
 * ```
 *
 * Every `with…` method returns a changed copy. All copies share the same `Core`, so prepare only one of them.
 */
final readonly class App
{
    /**
     * @param list<Language> $languages
     * @param list<Route> $routes
     * @param list<TemplateTag> $templateTags
     * @param ?Closure(ViewContext): NavigationItemCollection $navigationProvider
     */
    private function __construct(
        public Core $core,
        private string $viewClassPrefix,
        private array $languages = [],
        private array $routes = [],
        private ?CspPolicySettings $cspPolicySettings = new CspPolicySettings(),
        private bool $hasSession = true,
        private ?AbstractSessionHandler $sessionHandler = null,
        private ?Logger $logger = null,
        private ?ExceptionHandler $exceptionHandler = null,
        private ?string $loginPath = null,
        private array $templateTags = [],
        private ?Closure $navigationProvider = null,
        private ?RequestLanguageProvider $requestLanguageProvider = null,
    ) {}

    /**
     * Touches no globals: the factory for tests. Production code uses `fromEnvironment()`.
     *
     * @param string $viewClassPrefix The namespace of the view classes of all routes (`app\view\<viewGroup>\php\…`)
     */
    public static function fromCore(Core $core, string $viewClassPrefix = Core::APP_CLASS_PREFIX): App
    {
        return new App(core: $core, viewClassPrefix: $viewClassPrefix);
    }

    /**
     * Creates the application of this request with `Core::fromEnvironment()`: call it once in the front controller,
     * after `require 'vendor/autoload.php'`. The directory arguments are those of `Core::fromEnvironment()`.
     *
     * @param string $viewClassPrefix The namespace of the view classes of all routes (`'site'` keeps the namespace of
     *     an old project during a migration)
     *
     * @throws LogicException if called twice
     * @throws UnexpectedValueException if the environment file is invalid or `DOCUMENT_ROOT` is not set
     * @throws RuntimeException if a directory cannot be created
     */
    public static function fromEnvironment(
        string $envFilePath,
        int $copyrightYear,
        string $viewClassPrefix = Core::APP_CLASS_PREFIX,
        string $baseDirectory = '{DOCUMENT_ROOT}../',
        string $appDirectory = '{BASE_DIRECTORY}/app/',
        string $cacheDirectory = '{APP_DIRECTORY}cache/',
        string $errorDocsDirectory = '{APP_DIRECTORY}error_docs/',
        string $logsDirectory = '{APP_DIRECTORY}logs/',
        string $settingsDirectory = '{APP_DIRECTORY}settings/',
        string $snippetsDirectory = '{APP_DIRECTORY}snippets/',
        string $viewDirectory = '{APP_DIRECTORY}view/',
    ): App {
        return App::fromCore(
            core: Core::fromEnvironment(
                envFilePath: $envFilePath,
                copyrightYear: $copyrightYear,
                baseDirectory: $baseDirectory,
                appDirectory: $appDirectory,
                cacheDirectory: $cacheDirectory,
                errorDocsDirectory: $errorDocsDirectory,
                logsDirectory: $logsDirectory,
                settingsDirectory: $settingsDirectory,
                snippetsDirectory: $snippetsDirectory,
                viewDirectory: $viewDirectory,
            ),
            viewClassPrefix: $viewClassPrefix,
        );
    }

    /**
     * Adds a language of the project. The first language is the language of every route that names none.
     *
     * @throws LogicException if a route was added before or the code is already added
     */
    public function withLanguage(string $code, string $locale): App
    {
        if ($this->routes !== []) {
            throw new LogicException(
                message: 'Add the language "' . $code . '" with withLanguage() before the routes: the routes take'
                    . ' the first language as their language.',
            );
        }
        if ($this->findLanguage(code: $code) !== null) {
            throw new LogicException(message: 'The language "' . $code . '" is already added.');
        }

        return clone($this, ['languages' => [...$this->languages, new Language(code: $code, locale: $locale)]]);
    }

    /**
     * The language added with `withLanguage()`, for an own `Route` of `withRoute()`.
     *
     * @throws LogicException if the language is not added
     */
    public function getLanguage(string $code): Language
    {
        return $this->findLanguage(code: $code) ?? throw new LogicException(
            message: 'The language "' . $code . '" is not added: call withLanguage(code: \'' . $code . '\', locale:'
                . ' …) first.',
        );
    }

    /**
     * Adds a route of HTML pages: `<path><file title>.html` renders
     * `<view directory>/<viewGroup>/html/<file title>.html` with the view class
     * `<viewClassPrefix>\view\<viewGroup>\php\<file title>`, if it exists. The first HTML route of each language is the
     * one that "/" redirects to; without one, the first HTML route with `usesRequestLanguage`.
     *
     * @param string $defaultFileTitle The page of a request of the path itself (`/news/`), without extension
     * @param ?string $languageCode A language added with `withLanguage()`; `null`: the first language (if any)
     * @param ?ViewFactory $viewFactory Creates the views instead of the class name convention (e.g. a `ViewMap`)
     * @param HtmlFileExtensionEnum $fileExtension The only extension the paths of the route may have: `.html` by
     *     default, `NONE` for paths without extension (`/about`); the other one is a 404
     * @param bool $redirectDefaultFileName The default page has one URL: a request of its file name (`/home.html`) is
     *     redirected permanently to the path of the route (`/`); `false`: both URLs show the page
     * @param bool $usesRequestLanguage One path for all languages: the language is resolved per request (see
     *     `withRequestLanguageProvider()`) instead of `languageCode`
     *
     * @throws LogicException if the language is not added, the path is already used or `languageCode` is given with
     *     `usesRequestLanguage`
     */
    public function withHtmlRoute(
        string $path,
        string $viewGroup,
        string $defaultFileTitle = 'index',
        ?string $languageCode = null,
        ?ViewFactory $viewFactory = null,
        HtmlFileExtensionEnum $fileExtension = HtmlFileExtensionEnum::HTML,
        bool $redirectDefaultFileName = true,
        bool $usesRequestLanguage = false,
    ): App {
        $language = $this->resolveRouteLanguage(languageCode: $languageCode, usesRequestLanguage: $usesRequestLanguage);
        $isDefaultRoute = $usesRequestLanguage
            ? $this->findDefaultRequestLanguageRoute() === null
            : $language !== null && $this->findDefaultRoute(language: $language) === null;

        return $this->withRoute(
            route: new Route(
                path: $path,
                viewDirectory: $this->core->viewDirectory,
                viewClassPrefix: $this->viewClassPrefix,
                viewGroup: $viewGroup,
                defaultFileName: $fileExtension->createFileName(fileTitle: $defaultFileTitle),
                isDefaultForLanguage: $isDefaultRoute,
                defaultContentType: ContentType::createHtml(),
                language: $language,
                acceptedExtension: $fileExtension->value,
                viewFactory: $viewFactory,
                redirectDefaultFileName: $redirectDefaultFileName,
                usesRequestLanguage: $usesRequestLanguage,
            ),
        );
    }

    /**
     * Adds a route of other files (CSV export, PDF, JSON): only requests with the extension of the content type are
     * accepted. Never the target of "/".
     *
     * @param ?string $languageCode A language added with `withLanguage()`; `null`: the first language (if any)
     * @param bool $usesRequestLanguage The language is resolved per request (see `withRequestLanguageProvider()`)
     *     instead of `languageCode`
     *
     * @throws LogicException if the language is not added, the path is already used or `languageCode` is given with
     *     `usesRequestLanguage`
     */
    public function withFileRoute(
        string $path,
        string $viewGroup,
        ContentType $contentType,
        string $defaultFileName = '',
        ?string $languageCode = null,
        ?ViewFactory $viewFactory = null,
        bool $usesRequestLanguage = false,
    ): App {
        return $this->withRoute(
            route: new Route(
                path: $path,
                viewDirectory: $this->core->viewDirectory,
                viewClassPrefix: $this->viewClassPrefix,
                viewGroup: $viewGroup,
                defaultFileName: $defaultFileName,
                defaultContentType: $contentType,
                language: $this->resolveRouteLanguage(
                    languageCode: $languageCode,
                    usesRequestLanguage: $usesRequestLanguage,
                ),
                acceptedExtension: $contentType->type,
                viewFactory: $viewFactory,
                usesRequestLanguage: $usesRequestLanguage,
            ),
        );
    }

    /**
     * Adds a route of JSON responses whose paths have no extension (`/api/v1/domains`, a webhook, a cron call): a path
     * with an extension (`.json`, `.html`) is a 404. Never the target of "/".
     *
     * @param ?string $languageCode A language added with `withLanguage()`; `null`: the first language (if any)
     * @param bool $usesRequestLanguage The language is resolved per request (see `withRequestLanguageProvider()`)
     *     instead of `languageCode`
     *
     * @throws LogicException if the language is not added, the path is already used or `languageCode` is given with
     *     `usesRequestLanguage`
     */
    public function withJsonRoute(
        string $path,
        string $viewGroup,
        string $defaultFileName = '',
        ?string $languageCode = null,
        ?ViewFactory $viewFactory = null,
        bool $usesRequestLanguage = false,
    ): App {
        return $this->withRoute(
            route: new Route(
                path: $path,
                viewDirectory: $this->core->viewDirectory,
                viewClassPrefix: $this->viewClassPrefix,
                viewGroup: $viewGroup,
                defaultFileName: $defaultFileName,
                defaultContentType: ContentType::createJson(),
                language: $this->resolveRouteLanguage(
                    languageCode: $languageCode,
                    usesRequestLanguage: $usesRequestLanguage,
                ),
                acceptedExtension: '',
                viewFactory: $viewFactory,
                usesRequestLanguage: $usesRequestLanguage,
            ),
        );
    }

    /**
     * Adds a fully configured route (`viewCallback`, `forceFileName`, path variables, …). Its language must be the
     * object of `getLanguage()`.
     *
     * @throws LogicException if the language is not added, the language (or the language of the request) has a
     *     default route already or the path is already used
     */
    public function withRoute(Route $route): App
    {
        if (array_any(
            array: $this->routes,
            callback: static fn(Route $existingRoute): bool => $existingRoute->path === $route->path,
        )) {
            throw new LogicException(message: 'There is already a route with the path "' . $route->path . '".');
        }
        $language = $route->language;
        if ($language !== null) {
            if ($this->findLanguage(code: $language->code) !== $language) {
                throw new LogicException(
                    message: 'The language "' . $language->code . '" of the route "' . $route->path . '" is not'
                        . ' added: call withLanguage() first and give the route $app->getLanguage(code: \''
                        . $language->code . '\').',
                );
            }
            if ($route->isDefaultForLanguage && $this->findDefaultRoute(language: $language) !== null) {
                throw new LogicException(
                    message: 'The language "' . $language->code . '" has a default route already, the route "'
                        . $route->path . '" cannot be the default too.',
                );
            }
        }

        if (
            $route->usesRequestLanguage
            && $route->isDefaultForLanguage
            && $this->findDefaultRequestLanguageRoute() !== null
        ) {
            throw new LogicException(
                message: 'There is a default route with the language of the request already, the route "'
                    . $route->path . '" cannot be the default too.',
            );
        }

        return clone($this, ['routes' => [...$this->routes, $route]]);
    }

    /**
     * Adds the language switcher of the routes with `usesRequestLanguage` (`LanguageSwitch`): a request of the path
     * with `language` and `returnTo` (query or POST) sets the language of the session and redirects (303) back to
     * `returnTo`, else to `defaultPath`. Links: `LanguageSwitch::createUri(switchPath: '/language/', languageCode:
     * 'en', returnUri: $httpRequest->getUri())`.
     *
     * @throws LogicException if the path is already used
     * @throws InvalidArgumentException if the default path is no local path
     */
    public function withLanguageSwitchRoute(string $path, string $defaultPath = '/'): App
    {
        if (!LoginRedirect::isLocalPath(uri: $defaultPath)) {
            throw new InvalidArgumentException(message: 'The default path must be a local path starting with "/"');
        }
        $core = $this->core;

        return $this->withRoute(
            route: new Route(
                path: $path,
                viewDirectory: $core->viewDirectory,
                viewCallback: static function () use ($core, $defaultPath): never {
                    $session = $core->session ?? throw new LogicException(
                        message: 'The language switcher needs a session: remove withoutSession().',
                    );
                    $returnPath = new LanguageSwitch(
                        httpRequest: $core->httpRequest,
                        session: $session,
                        availableLanguages: $core->availableLanguages,
                    )->apply(defaultPath: $defaultPath);
                    $session->close();
                    HttpResponse::redirectAndExit(
                        relativeOrAbsoluteUri: $returnPath,
                        httpRequest: $core->httpRequest,
                        responseSender: $core->responseSender,
                    );
                },
                defaultContentType: ContentType::createHtml(),
                acceptedExtension: '',
            ),
        );
    }

    /**
     * Decides first about the language of the routes with `usesRequestLanguage`, e.g. the profile language of the
     * logged-in user. Without it (or if it returns `null`): the language chosen in the session
     * (`SessionPreferredLanguage`), then the browser language, then the first language (`RequestLanguageResolver`).
     */
    public function withRequestLanguageProvider(RequestLanguageProvider $requestLanguageProvider): App
    {
        return clone($this, ['requestLanguageProvider' => $requestLanguageProvider]);
    }

    /**
     * @param ?CspPolicySettings $cspPolicySettings `null`: no `Content-Security-Policy` header
     */
    public function withCsp(?CspPolicySettings $cspPolicySettings): App
    {
        return clone($this, ['cspPolicySettings' => $cspPolicySettings]);
    }

    /** No PHP session (and therefore no CSRF tokens and no login) */
    public function withoutSession(): App
    {
        return clone($this, ['hasSession' => false, 'sessionHandler' => null]);
    }

    /** Another session handler than the default `FileSessionHandler` (e.g. sessions in the database) */
    public function withSessionHandler(AbstractSessionHandler $sessionHandler): App
    {
        return clone($this, ['hasSession' => true, 'sessionHandler' => $sessionHandler]);
    }

    /**
     * The default `FileSessionHandler` with own session settings, e.g. a longer lifetime:
     * `withFileSessions(sessionSettings: new SessionSettings(maxLifeTime: 43200))`. See
     * `Core::createFileSessionHandler()`.
     */
    public function withFileSessions(SessionSettings $sessionSettings, Clock $clock = new SystemClock()): App
    {
        return $this->withSessionHandler(
            sessionHandler: $this->core->createFileSessionHandler(sessionSettings: $sessionSettings, clock: $clock),
        );
    }

    /** Another logger than the default `FileLogger` */
    public function withLogger(Logger $logger): App
    {
        return clone($this, ['logger' => $logger]);
    }

    /**
     * The default `FileLogger` with a mailer for the new issues (e.g. `SmtpMailer`, see `docs/setup.md`) or a clock.
     * See `Core::createFileLogger()`.
     *
     * @throws InvalidArgumentException for a mailer without a valid sender address
     */
    public function withFileLogger(
        ?AbstractMailer $mailer = null,
        string $mailSenderAddress = '',
        Clock $clock = new SystemClock(),
    ): App {
        return $this->withLogger(
            logger: $this->core->createFileLogger(mailer: $mailer, mailSenderAddress: $mailSenderAddress, clock: $clock),
        );
    }

    /**
     * The logger of `withLogger()` or `withFileLogger()`, for the project's own classes (e.g. a `LoginRateLimit`),
     * so they log into the same tickets. Without one, a new default `FileLogger`.
     */
    public function getLogger(): Logger
    {
        return $this->logger ?? $this->core->createFileLogger();
    }

    /** @see Core::prepareHttpResponse() */
    public function withExceptionHandler(ExceptionHandler $exceptionHandler): App
    {
        return clone($this, ['exceptionHandler' => $exceptionHandler]);
    }

    /**
     * @param string $loginPath Local path of the login page: a view with required access rights redirects a request
     *     without user there
     *
     * @throws InvalidArgumentException if the login path is no local path
     */
    public function withLoginPath(string $loginPath): App
    {
        if (!LoginRedirect::isLocalPath(uri: $loginPath)) {
            throw new InvalidArgumentException(message: 'The login path must be a local path starting with "/"');
        }

        return clone($this, ['loginPath' => $loginPath]);
    }

    /**
     * @param list<TemplateTag> $templateTags The own tags of the project
     */
    public function withTemplateTags(array $templateTags): App
    {
        return clone($this, ['templateTags' => $templateTags]);
    }

    /**
     * @param Closure(ViewContext): NavigationItemCollection $navigationProvider
     */
    public function withNavigation(Closure $navigationProvider): App
    {
        return clone($this, ['navigationProvider' => $navigationProvider]);
    }

    /**
     * Adds the languages to `Core` and prepares the response with `Core::prepareHttpResponse()`. Without any route,
     * `app/view/frontend/` is served at "/" (`withHtmlRoute(path: '/', viewGroup: 'frontend')`).
     *
     * @throws LogicException if called twice (also on another copy of this `App`)
     * @throws InvalidArgumentException for an invalid template tag name
     */
    public function prepare(): HttpResponse
    {
        $app = $this->routes === [] ? $this->withHtmlRoute(path: '/', viewGroup: 'frontend') : $this;
        foreach ($app->languages as $language) {
            if (!$app->core->availableLanguages->hasLanguage(languageCode: $language->code)) {
                $app->core->availableLanguages->add(language: $language);
            }
        }

        return $app->core->prepareHttpResponse(
            logger: $app->logger,
            routeCollection: new RouteCollection(routes: $app->routes, loginPath: $app->loginPath),
            individualExceptionHandler: $app->exceptionHandler,
            cspPolicySettings: $app->cspPolicySettings,
            individualSessionHandler: $app->hasSession ? $app->sessionHandler : false,
            templateTags: $app->templateTags,
            navigationProvider: $app->navigationProvider,
            requestLanguageProvider: $app->requestLanguageProvider,
        );
    }

    /**
     * Prepares and sends the response, the last statement of the front controller.
     */
    public function run(): never
    {
        $this->prepare()->sendAndExit();
    }

    private function findLanguage(string $code): ?Language
    {
        return array_find(
            array: $this->languages,
            callback: static fn(Language $language): bool => $language->code === $code,
        );
    }

    /**
     * @throws LogicException if the language is not added
     */
    private function resolveLanguage(?string $languageCode): ?Language
    {
        if ($languageCode === null) {
            return array_first(array: $this->languages);
        }

        return $this->getLanguage(code: $languageCode);
    }

    /**
     * @throws LogicException if the language is not added or given with `usesRequestLanguage`
     */
    private function resolveRouteLanguage(?string $languageCode, bool $usesRequestLanguage): ?Language
    {
        if (!$usesRequestLanguage) {
            return $this->resolveLanguage(languageCode: $languageCode);
        }
        if ($languageCode !== null) {
            throw new LogicException(
                message: 'A route with usesRequestLanguage has no fixed language: remove languageCode: \''
                    . $languageCode . '\'.',
            );
        }

        return null;
    }

    private function findDefaultRequestLanguageRoute(): ?Route
    {
        return array_find(
            array: $this->routes,
            callback: static fn(Route $route): bool => $route->isDefaultForLanguage && $route->usesRequestLanguage,
        );
    }

    private function findDefaultRoute(Language $language): ?Route
    {
        return array_find(
            array: $this->routes,
            callback: static fn(Route $route): bool => $route->isDefaultForLanguage && $route->language === $language,
        );
    }
}
