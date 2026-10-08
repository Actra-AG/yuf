<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\core\PathVars;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\security\CspNonce;
use actra\yuf\tests\Double\template\TemplateEngineFactory;

/**
 * Builds a ViewContext without a RequestHandler, for the given request or a default one.
 */
final class ViewContextFactory
{
    /**
     * @param list<string> $pathVars
     */
    public static function create(
        string $fileTitle = 'index',
        ?string $fileGroup = null,
        string $viewGroup = 'frontend',
        string $viewClassPrefix = 'actra\yuf\tests\Double',
        ?ContentType $contentType = null,
        array $pathVars = [],
        ?HttpRequest $httpRequest = null,
    ): ViewContext {
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());

        return new ViewContext(
            httpRequest: $httpRequest ?? HttpRequestFactory::create(),
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
        );
    }
}
