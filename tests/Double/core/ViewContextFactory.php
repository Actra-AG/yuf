<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\ContentHandler;
use actra\yuf\core\ContentType;
use actra\yuf\core\PathVars;
use actra\yuf\core\Route;
use actra\yuf\core\ViewContext;
use actra\yuf\security\CspNonce;

/**
 * Builds a ViewContext without a request: RequestHandler cannot be created in tests.
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
    ): ViewContext {
        return new ViewContext(
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
        );
    }
}
