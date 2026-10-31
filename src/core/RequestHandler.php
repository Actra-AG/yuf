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
    /**
     * The route with `usesRequestLanguage` and `isDefaultForLanguage`: the default route of every language without a
     * default route of its own, `null` for none.
     */
    public readonly ?Route $defaultRequestLanguageRoute;
    /**
     * The login path of the route collection (`RouteCollection::$loginPath`), `null` for none.
     */
    public readonly ?string $loginPath;
    /**
     * The language of the request: the first available language, after `resolveRoute()` the language of the route if
     * it has one, for a route with `usesRequestLanguage` the language of `RequestLanguageResolver`. Stays as far as
     * resolved when `resolveRoute()` throws, so error pages use it.
     */
    public private(set) ?Language $language = null;
    /**
     * The requested file name: the last path part, after `resolveRoute()` the resolved file name. Stays as far as
     * resolved when `resolveRoute()` throws, so error pages use it.
     */
    public private(set) ?string $fileName = null;
    private bool $routeResolved = false;

    /**
     * Prepares the request without resolving the route: the language, the path parts, the file name and the default
     * routes are available afterwards (also for the error page of an unknown route). Call `resolveRoute()` next.
     *
     * @param list<string> $allowedDomains
     * @param ?Session $session Remembers the language of the last route that has one (`rememberPreferredLanguage()`),
     *                          and the redirect of "/" reads it, but only while the session is active
     *                          (`Session::isActive()`: a visitor without a session cookie gets none; `Core::$session`,
     *                          `null` without sessions)
     * @param ResponseSender $responseSender Sends the redirect of "/"
     * @param ?RequestLanguageProvider $requestLanguageProvider Decides first about the language of a route with
     *                                                          `usesRequestLanguage` (see `RequestLanguageResolver`)
     *
     * @throws LogicException if two routes are the default of the same language or two routes with
     *                        `usesRequestLanguage` are default routes
     */
    public function __construct(
        private readonly HttpRequest $httpRequest,
        private readonly RouteCollection $routeCollection,
        private readonly LanguageCollection $availableLanguages,
        private readonly array $allowedDomains,
        private readonly ?Session $session,
        private readonly ResponseSender $responseSender = new NativeResponseSender(),
        private readonly ?RequestLanguageProvider $requestLanguageProvider = null,
    ) {
        if (!$availableLanguages->isEmpty()) {
            $this->language = $availableLanguages->getFirstLanguage();
        }
        $this->pathParts = explode(separator: '/', string: $this->httpRequest->getPath());
        $this->countPathParts = count(value: $this->pathParts);
        $this->fileName = trim(string: array_last(array: $this->pathParts));
        $this->defaultRoutesByLanguage = $this->initDefaultRoutes();
        $this->defaultRequestLanguageRoute = $this->findDefaultRequestLanguageRoute();
        $this->loginPath = $routeCollection->loginPath;
    }

    /**
     * Finds the route of the request and everything that depends on it.
     *
     * The file title (the file name before the first "-" and the extension; empty for a route without file name) may
     * only contain letters, digits and "_", the file group names of letters, digits, "_" and "-" separated by "/" (no
     * empty names, no "." or ".."): otherwise the request is not found. Both come from the request path and name view
     * classes, templates and language files.
     *
     * @throws NotFoundException if the domain is not allowed, the path is invalid, the file title or the file group
     *                           is not a name, or no route matches
     * @throws LogicException if called twice
     */
    public function resolveRoute(): ResolvedRoute
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
        $pathMatch = $this->initRoute();
        $route = $pathMatch->route;
        $fileGroup = $pathMatch->fileGroup;
        if ($pathMatch->fileName !== null) {
            $this->fileName = $pathMatch->fileName;
        }
        $this->redirectDefaultFileName(route: $route, requestedFileName: $this->fileName ?? '');
        $forceFileGroup = $route->forceFileGroup;
        if ($forceFileGroup !== null && $forceFileGroup !== '') {
            $fileGroup = $forceFileGroup;
        }
        $forceFileName = $route->forceFileName;
        if ($forceFileName !== null && $forceFileName !== '') {
            $this->fileName = $forceFileName;
        }
        $routeLanguage = $route->usesRequestLanguage
            ? new RequestLanguageResolver(
                httpRequest: $this->httpRequest,
                availableLanguages: $this->availableLanguages,
                session: $this->session,
                requestLanguageProvider: $this->requestLanguageProvider,
            )->resolve()
            : $route->language;
        if ($routeLanguage !== null) {
            $this->language = $routeLanguage;
        }
        $requestedFileName = $this->fileName ?? '';
        $fileName = (trim(string: $requestedFileName) === '') ? $route->defaultFileName : $requestedFileName;
        $dotPos = strripos(haystack: $fileName, needle: '.');
        if ($dotPos === false) {
            $length = strlen(string: $fileName);
            $fileExtension = '';
        } else {
            $length = $dotPos;
            $fileExtension = substr(string: $fileName, offset: $length + 1);
        }
        $pathVars = substr(string: $fileName, offset: 0, length: $length)
                |> (fn($x) => explode(separator: '-', string: $x))
                |> (fn($x) => str_replace(search: '__DASH__', replace: '-', subject: $x));
        $this->fileName = $fileName;
        if ($route->acceptedExtension !== null && $fileExtension !== $route->acceptedExtension) {
            throw new NotFoundException();
        }
        // Both name view classes and language files: only names, never a path
        if (preg_match(pattern: '/^[A-Za-z0-9_]*$/D', subject: $pathVars[0]) !== 1) {
            throw new NotFoundException();
        }
        if ($fileGroup !== null && preg_match(
            pattern: '/^[A-Za-z0-9_-]+(?:\/[A-Za-z0-9_-]+)*$/D',
            subject: $fileGroup,
        ) !== 1) {
            throw new NotFoundException();
        }

        return new ResolvedRoute(
            route: $route,
            language: $this->language,
            fileName: $fileName,
            fileGroup: $fileGroup,
            fileTitle: $pathVars[0],
            fileExtension: $fileExtension,
            routeVariables: $pathMatch->routeVariables,
            pathVars: $pathVars,
        );
    }

    /**
     * A request of the default file name of a route with `redirectDefaultFileName` (`/home.html`) is redirected
     * permanently to the path of the route (`/`), with the query: the page has one URL. Not for routes with path
     * variables or a forced file name (their path is no URL of the default file).
     */
    private function redirectDefaultFileName(Route $route, string $requestedFileName): void
    {
        if (
            !$route->redirectDefaultFileName
            || $requestedFileName === ''
            || $requestedFileName !== $route->defaultFileName
            || ($route->forceFileName !== null && $route->forceFileName !== '')
            || str_contains(haystack: $route->path, needle: '${')
        ) {
            return;
        }
        $query = $this->httpRequest->getQuery();
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: $route->path . ($query === '' ? '' : '?' . $query),
            httpRequest: $this->httpRequest,
            httpStatusCode: HttpStatusCodeEnum::HTTP_MOVED_PERMANENTLY,
            responseSender: $this->responseSender,
        );
    }

    /**
     * Remembers the language of a route with an explicit language as preferred language of the user (the redirect of
     * "/" uses it). `Core` calls it after the view, only if the view started the session anyway: remembering the
     * language alone must not start a session (lock, file, cookie). Nothing happens without a language of the route
     * or without an active session.
     *
     * @throws LogicException if the language of the route is not available
     */
    public function rememberPreferredLanguage(ResolvedRoute $resolvedRoute): void
    {
        $language = $resolvedRoute->route->language;
        if ($language === null || $this->session === null || !$this->session->isActive()) {
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

    /**
     * @throws LogicException if two routes with `usesRequestLanguage` are default routes
     */
    private function findDefaultRequestLanguageRoute(): ?Route
    {
        $defaultRoute = null;
        foreach ($this->routeCollection->routes as $route) {
            if (!$route->isDefaultForLanguage || !$route->usesRequestLanguage) {
                continue;
            }
            if ($defaultRoute !== null) {
                throw new LogicException(
                    message: 'Default route with the language of the request is already set: ' . $defaultRoute->path,
                );
            }
            $defaultRoute = $route;
        }

        return $defaultRoute;
    }

    private function initRoute(): RoutePathMatch
    {
        $pathMatch = $this->findRouteOfPath();
        if ($pathMatch !== null) {
            return $pathMatch;
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
     * The route of the requested directory or path pattern (`/shop/${fileGroup}/${fileName}`) with the variables of
     * the pattern.
     */
    private function findRouteOfPath(): ?RoutePathMatch
    {
        $requestedDirectories = '/';
        foreach (array_slice(array: $this->pathParts, offset: 1, length: max(0, $this->countPathParts - 2)) as $part) {
            $requestedDirectories .= $part . '/';
        }
        $requestedPath = $this->httpRequest->getPath();
        foreach ($this->routeCollection->routes as $route) {
            $routePath = $route->path;
            if ($routePath === $requestedDirectories) {
                return new RoutePathMatch(route: $route);
            }
            if (preg_match_all(
                pattern: '#\${(.*?)}#',
                subject: $routePath,
                matches: $variableMatches,
            ) === 0) {
                continue;
            }
            // The text between the variables is literal: a "." of the route path matches a dot only
            $literalParts = preg_split(pattern: '#\$\{.*?\}#', subject: $routePath);
            $pattern = '#^' . implode(
                separator: '(.*)',
                array: array_map(
                    callback: static fn(string $part): string => preg_quote(str: $part, delimiter: '#'),
                    array: $literalParts === false ? [$routePath] : $literalParts,
                ),
            ) . '$#';
            if (preg_match(
                pattern: $pattern,
                subject: $requestedPath,
                matches: $valueMatches,
            ) === 0) {
                continue;
            }
            $fileName = null;
            $fileGroup = null;
            $routeVariables = [];
            foreach ($variableMatches[1] as $index => $variableName) {
                $value = array_key_exists(key: $index + 1, array: $valueMatches) ? $valueMatches[$index + 1] : '';
                if ($variableName === 'fileName') {
                    $fileName = $value;
                } elseif ($variableName === 'fileGroup') {
                    $fileGroup = $value;
                } else {
                    $routeVariables[$variableName] = $value;
                }
            }

            return new RoutePathMatch(
                route: $route,
                fileName: $fileName,
                fileGroup: $fileGroup,
                routeVariables: $routeVariables,
            );
        }

        return null;
    }

    /**
     * The default route that a request of "/" is redirected to: the route of the language that the session
     * remembers (only an active session is asked), else of the first browser language that has one, else the first
     * default route, else the default route with `usesRequestLanguage`.
     *
     * @throws LogicException if there is no default route (`isDefaultForLanguage: true` with an available language or
     *                        `usesRequestLanguage`)
     */
    public function findRouteForRootRequest(): Route
    {
        $defaultRoutesByLanguage = $this->defaultRoutesByLanguage;
        $preferredLanguageCode = $this->session === null || !$this->session->isActive()
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
            if ($this->defaultRequestLanguageRoute !== null) {
                return $this->defaultRequestLanguageRoute;
            }
            throw new LogicException(
                message: 'The request of "/" has no route to be redirected to: set isDefaultForLanguage: true on a'
                    . ' route with an available language.',
            );
        }

        return $defaultRoutesByLanguage->getFirstRoute();
    }

    /**
     * The path of the default route of the language of the request (`findDefaultRouteForLanguage()`), `/` without.
     */
    public function getLanguageRoot(): string
    {
        foreach ($this->defaultRoutesByLanguage->routes as $route) {
            if ($route->language === $this->language) {
                return $route->path;
            }
        }
        if ($this->language !== null && $this->defaultRequestLanguageRoute !== null) {
            return $this->defaultRequestLanguageRoute->path;
        }

        return '/';
    }

    /**
     * The default route of the language: the one with this fixed language, else the default route with
     * `usesRequestLanguage` (it serves every language), `null` for none.
     */
    public function findDefaultRouteForLanguage(string $languageCode): ?Route
    {
        return $this->defaultRoutesByLanguage->getRouteForLanguage(languageCode: $languageCode)
            ?? $this->defaultRequestLanguageRoute;
    }
}
