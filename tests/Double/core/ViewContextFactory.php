<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\auth\AuthSession;
use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\PathVars;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\layout\NavigationItemCollection;
use actra\yuf\security\CspNonce;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Closure;

/**
 * Builds a ViewContext without a RequestHandler, for the given request or a default one.
 */
final class ViewContextFactory
{
    /**
     * @param list<string> $pathVars
     * @param ?Closure(ViewContext): NavigationItemCollection $navigationProvider
     */
    public static function create(
        string $fileTitle = 'index',
        ?string $fileGroup = null,
        string $viewGroup = 'frontend',
        string $viewClassPrefix = 'actra\yuf\tests\Double',
        ?ContentType $contentType = null,
        array $pathVars = [],
        ?HttpRequest $httpRequest = null,
        ?Session $session = null,
        ?ResponseSender $responseSender = null,
        ?AbstractSessionHandler $sessionHandler = null,
        ?Closure $navigationProvider = null,
    ): ViewContext {
        $httpRequest ??= HttpRequestFactory::create();
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());

        return new ViewContext(
            httpRequest: $httpRequest,
            session: $session,
            sessionHandler: $sessionHandler,
            authSession: $session === null ? null : new AuthSession(session: $session),
            formContext: FormContextFactory::create(httpRequest: $httpRequest),
            route: new Route(
                path: '/',
                viewDirectory: '/tmp/views/',
                viewClassPrefix: $viewClassPrefix,
                viewGroup: $viewGroup,
            ),
            fileGroup: $fileGroup,
            fileTitle: $fileTitle,
            pathVars: new PathVars(values: $pathVars),
            content: new ContentHandler(
                contentType: $contentType ?? ContentType::createHtml(),
                cspNonce: CspNonce::create(),
            ),
            locale: $localeHandler,
            templateEngine: TemplateEngineFactory::create(
                cacheDirectory: sys_get_temp_dir() . '/yuf-view-context-test/',
                templateBaseDirectory: sys_get_temp_dir() . '/',
                localeHandler: $localeHandler,
            ),
            responseSender: $responseSender ?? new RecordingResponseSender(),
            navigationProvider: $navigationProvider,
        );
    }
}
