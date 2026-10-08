<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\exception\NotFoundException;
use actra\yuf\session\Session;
use actra\yuf\session\SessionPreferredLanguage;
use LogicException;

final class RequestHandler
{
    /** @var non-empty-list<string> */
    public readonly array $pathParts;
    public readonly int $countPathParts;
    public readonly RouteCollection $defaultRoutesByLanguage;
    // Set by resolveRoute()
    public private(set) Route $route; // @phpstan-ignore property.uninitialized
    public private(set) ?Language $language = null;
    // Set by resolveRoute()
    public private(set) string $fileTitle; // @phpstan-ignore property.uninitialized
    // Set by resolveRoute()
    public private(set) string $fileExtension; // @phpstan-ignore property.uninitialized
    public private(set) ?string $fileName = null;
    public private(set) ?string $fileGroup = null;
    /** @var array<string, string> */
    public private(set) array $routeVariables = [];
    // Set by resolveRoute()
    /** @var list<string> */
    public private(set) array $pathVars; // @phpstan-ignore property.uninitialized
    private bool $routeResolved = false;

    /**
     * Prepares the request without resolving the route: the language, the path parts, the file name and the default
     * routes are available afterwards (also for the error page of an unknown route). Call `resolveRoute()` next.
     *
     * @param list<string> $allowedDomains
     * @param ?Session $session Remembers the language of the last route that has one (`Core::$session`, `null`
     *                          without sessions)
     * @param ResponseSender $responseSender Sends the redirect of "/"
     */
    public function __construct(
        private readonly HttpRequest $httpRequest,
        private readonly RouteCollection $routeCollection,
        private readonly LanguageCollection $availableLanguages,
        private readonly array $allowedDomains,
        private readonly ?Session $session,
        private readonly ResponseSender $responseSender = new NativeResponseSender(),
    ) {
        if (!$availableLanguages->isEmpty()) {
            $this->language = $availableLanguages->getFirstLanguage();
        }
        $this->pathParts = explode(separator: '/', string: $this->httpRequest->getPath());
        $this->countPathParts = count(value: $this->pathParts);
        $this->fileName = trim(string: array_last(array: $this->pathParts));
        $this->defaultRoutesByLanguage = $this->initDefaultRoutes();
    }

    /**
     * Finds the route of the request and everything that depends on it.
     *
     * @throws NotFoundException if the domain is not allowed, the path is invalid or no route matches
     * @throws LogicException if called twice
     */
    public function resolveRoute(): void
    {
        if ($this->routeResolved) {
            throw new LogicException(message: 'The route is already resolved');
        }
        $this->routeResolved = true;
        $this->checkDomain();
        if (str_contains(
            haystack: $this->httpRequest->getPath(),
            needle: '//',
        )) {
            throw new NotFoundException();
        }
        $this->route = $this->initRoute();
        $forceFileGroup = $this->route->forceFileGroup;
        if ($forceFileGroup !== null && $forceFileGroup !== '') {
            $this->fileGroup = $forceFileGroup;
        }
        $forceFileName = $this->route->forceFileName;
        if ($forceFileName !== null && $forceFileName !== '') {
            $this->fileName = $forceFileName;
        }
        $routeLanguage = $this->route->language;
        if ($routeLanguage !== null) {
            $this->language = $routeLanguage;
            // Only a route with an explicit language tells the language of the user
            $this->rememberPreferredLanguage(language: $routeLanguage);
        }
        $requestedFileName = $this->fileName ?? '';
        $fileName = (trim(string: $requestedFileName) === '') ? $this->route->defaultFileName : $requestedFileName;
        $dotPos = strripos(haystack: $fileName, needle: '.');
        if ($dotPos === false) {
            $length = strlen(string: $fileName);
            $fileExtension = '';
        } else {
            $length = $dotPos;
            $fileExtension = substr(string: $fileName, offset: $length + 1);
        }
        $fnArr = substr(string: $fileName, offset: 0, length: $length)
                |> (fn($x) => explode(separator: '-', string: $x))
                |> (fn($x) => str_replace(search: '__DASH__', replace: '-', subject: $x));
        $this->fileName = $fileName;
        $this->fileTitle = $fnArr[0];
        $this->pathVars = $fnArr;
        $this->fileExtension = $fileExtension;
        if (
            $this->route->acceptedExtension !== null
            && $this->fileExtension !== $this->route->acceptedExtension
        ) {
            throw new NotFoundException();
        }
    }

    private function rememberPreferredLanguage(Language $language): void
    {
        if ($this->session === null) {
            return;
        }
        $preferredLanguage = new SessionPreferredLanguage(session: $this->session);
        if ($preferredLanguage->getCode() === $language->code) {
            return;
        }
        if (!$this->availableLanguages->hasLanguage(languageCode: $language->code)) {
            throw new LogicException(message: 'The preferred language ' . $language->code . ' is not available');
        }
        $preferredLanguage->set(language: $language);
    }

    private function checkDomain(): void
    {
        $host = $this->httpRequest->getHost();

        if (
            !in_array(
                needle: $host,
                haystack: $this->allowedDomains,
                strict: true,
            )
        ) {
            throw new NotFoundException(
                message: $host . ' is not set as allowed domain in your environment settings.',
            );
        }
    }

    /**
     * The first route with an available language for each language that has a default route.
     *
     * @throws LogicException if two routes are the default of the same language
     */
    private function initDefaultRoutes(): RouteCollection
    {
        $defaultRoutes = new RouteCollection();
        $usedLanguages = new LanguageCollection();
        foreach ($this->routeCollection->routes as $route) {
            if (
                !$route->isDefaultForLanguage
                || $route->language === null
            ) {
                continue;
            }
            if ($usedLanguages->hasLanguage(languageCode: $route->language->code)) {
                throw new LogicException(
                    message: 'Default route for language ' . $route->language->code . ' is already set',
                );
            }
            if ($this->availableLanguages->hasLanguage(languageCode: $route->language->code)) {
                $defaultRoutes->addRoute(route: $route);
                $usedLanguages->add(language: $route->language);
            }
        }

        return $defaultRoutes;
    }

    private function initRoute(): Route
    {
        $route = $this->findRouteOfPath();
        if ($route !== null) {
            return $route;
        }
        if ($this->httpRequest->getUri() === '/') {
            HttpResponse::redirectAndExit(
                relativeOrAbsoluteUri: $this->findRouteForRootRequest()->path,
                httpRequest: $this->httpRequest,
                responseSender: $this->responseSender,
            );
        }

        throw new NotFoundException();
    }

    /**
     * The route of the requested directory or path pattern (`/shop/${fileGroup}/${fileName}`); the variables of the
     * pattern are set as a side effect.
     */
    private function findRouteOfPath(): ?Route
    {
        $requestedDirectories = '/';
        foreach (array_slice(array: $this->pathParts, offset: 1, length: max(0, $this->countPathParts - 2)) as $part) {
            $requestedDirectories .= $part . '/';
        }
        $requestedPath = $this->httpRequest->getPath();
        foreach ($this->routeCollection->routes as $route) {
            $routePath = $route->path;
            if ($routePath === $requestedDirectories) {
                return $route;
            }
            if (preg_match_all(
                pattern: '#\${(.*?)}#',
                subject: $routePath,
                matches: $variableMatches,
            ) === 0) {
                continue;
            }
            $pattern = '#^' . str_replace(search: $variableMatches[0], replace: '(.*)', subject: $routePath) . '$#';
            if (preg_match(
                pattern: $pattern,
                subject: $requestedPath,
                matches: $valueMatches,
            ) === 0) {
                continue;
            }
            foreach ($variableMatches[1] as $index => $variableName) {
                $this->setPathVariable(
                    name: $variableName,
                    value: array_key_exists(key: $index + 1, array: $valueMatches) ? $valueMatches[$index + 1] : '',
                );
            }

            return $route;
        }

        return null;
    }

    private function setPathVariable(string $name, string $value): void
    {
        if ($name === 'fileName') {
            $this->fileName = $value;
        } elseif ($name === 'fileGroup') {
            $this->fileGroup = $value;
        } else {
            $this->routeVariables[$name] = $value;
        }
    }

    /**
     * The default route that a request of "/" is redirected to: the route of the language that the session
     * remembers, else of the first browser language that has one, else the first default route.
     *
     * @throws LogicException if there is no default route (`isDefaultForLanguage: true` with an available language)
     */
    public function findRouteForRootRequest(): Route
    {
        $defaultRoutesByLanguage = $this->defaultRoutesByLanguage;
        $preferredLanguageCode = $this->session === null
            ? null
            : new SessionPreferredLanguage(session: $this->session)->getCode();
        if ($preferredLanguageCode !== null) {
            $preferredRoute = $defaultRoutesByLanguage->getRouteForLanguage(languageCode: $preferredLanguageCode);
            if ($preferredRoute !== null) {
                return $preferredRoute;
            }
        }
        foreach ($this->httpRequest->listBrowserLanguagesByQuality() as $languageCode) {
            $routeForLanguage = $defaultRoutesByLanguage->getRouteForLanguage(languageCode: $languageCode);
            if ($routeForLanguage !== null) {
                return $routeForLanguage;
            }
        }
        // None in the accepted languages: the first default route
        if (!$defaultRoutesByLanguage->hasRoutes()) {
            throw new LogicException(
                message: 'The request of "/" has no route to be redirected to: set isDefaultForLanguage: true on a'
                    . ' route with an available language.',
            );
        }

        return $defaultRoutesByLanguage->getFirstRoute();
    }

    public function getPathVar(int $nr): ?string
    {
        $pathVars = $this->pathVars;

        return array_key_exists(key: $nr, array: $pathVars) ? trim(string: $pathVars[$nr]) : null;
    }

    public function getLanguageRoot(): string
    {
        foreach ($this->defaultRoutesByLanguage->routes as $route) {
            if ($route->language === $this->language) {
                return $route->path;
            }
        }
        return '/';
    }
}
