<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use actra\yuf\App;
use actra\yuf\core\BaseView;
use actra\yuf\core\ViewContext;
use actra\yuf\core\ViewMap;
use app\view\frontend\IndexView;

// Composer loads yuf and the classes of app/ (in this repository: autoload and autoload-dev of composer.json)
require __DIR__ . '/../../vendor/autoload.php';

App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)
    ->withLanguage(code: 'en', locale: 'en_US.UTF-8')
    // "/" and "/index.html" are handled by app\view\frontend\IndexView: the ViewMap maps the file name "index" to it.
    // Without viewFactory, yuf would use the class app\view\frontend\php\index (the class name is built from the
    // route and the file name).
    ->withHtmlRoute(
        path: '/',
        viewGroup: 'frontend',
        viewFactory: new ViewMap()->add(
            fileTitle: 'index',
            create: fn(ViewContext $context): BaseView => new IndexView(context: $context),
        ),
    )
    ->withoutSession()
    ->run();
