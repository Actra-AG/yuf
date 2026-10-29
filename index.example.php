<?php

declare(strict_types=1);

use actra\yuf\App;

require __DIR__ . '/../vendor/autoload.php';

// Serves app/view/frontend/ at "/": html/index.html, with the view class app\view\frontend\php\index if it exists.
// Add languages with withLanguage() first, then routes with withHtmlRoute() and withFileRoute() (see docs/setup.md).
App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)->run();
