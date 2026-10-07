<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use actra\yuf\Core;
use actra\yuf\core\ContentType;
use actra\yuf\core\Language;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;

// Uses the yuf sources of this repository (in a real project: vendor/actra/yuf/src/Core.php)
require __DIR__ . '/../../src/Core.php';

$core = new Core(
    envFilePath: __DIR__ . '/../.env.php',
    copyrightYear: 2026,
    autoloaderPath: __DIR__ . '/../../vendor/actra/autoloader/src/Autoloader.php',
);
$english = new Language(code: 'en', locale: 'en_US.UTF-8');
$core->availableLanguages->add(language: $english);
$core->prepareHttpResponse(
    routeCollection: new RouteCollection(
        routes: [
            // "/" and "/index.html" are handled by the view class app\view\frontend\php\index
            new Route(
                path: '/',
                viewGroup: 'frontend',
                defaultFileName: 'index.html',
                defaultContentType: ContentType::createHtml(),
                language: $english,
            ),
        ],
    ),
    individualSessionHandler: false,
)->sendAndExit();
