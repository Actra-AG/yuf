<?php
declare(strict_types=1);

use actra\yuf\Core;
use actra\yuf\core\ContentType;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;

require __DIR__ . '/../vendor/actra/yuf/src/Core.php';
$core = new Core(
    envFilePath: __DIR__ . '/../.env.php',
    copyrightYear: 2026
);
$core->prepareHttpResponse(
    routeCollection: new RouteCollection(
        routes: [
            new Route(
                path: '/',
                viewCallback: fn() => 'Hello World!',
                defaultContentType: ContentType::createTxt()
            )
        ],
    ),
    individualSessionHandler: false
)->sendAndExit();