<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use actra\yuf\Core;
use actra\yuf\core\BaseView;
use actra\yuf\core\ContentType;
use actra\yuf\core\Language;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewMap;
use app\view\frontend\IndexView;

// Uses the yuf sources of this repository (in a real project: vendor/actra/yuf/src/Core.php)
require __DIR__ . '/../../src/Core.php';

$core = Core::fromEnvironment(
    envFilePath: __DIR__ . '/../.env.php',
    copyrightYear: 2026,
    autoloaderPath: __DIR__ . '/../../vendor/actra/autoloader/src/Autoloader.php',
);
$english = new Language(code: 'en', locale: 'en_US.UTF-8');
$core->availableLanguages->add(language: $english);
$core->prepareHttpResponse(
    routeCollection: new RouteCollection(
        routes: [
            // "/" and "/index.html" are handled by app\view\frontend\IndexView: the ViewMap maps the file name
            // "index" to it. Without viewFactory, yuf would use the class app\view\frontend\php\index
            // (ClassNameViewFactory: the class name is built from the route and the file name).
            new Route(
                path: '/',
                viewDirectory: $core->viewDirectory,
                viewGroup: 'frontend',
                defaultFileName: 'index.html',
                defaultContentType: ContentType::createHtml(),
                language: $english,
                viewFactory: new ViewMap()->add(
                    fileTitle: 'index',
                    create: fn(ViewContext $context): BaseView => new IndexView(context: $context),
                ),
            ),
        ],
    ),
    individualSessionHandler: false,
)->sendAndExit();
