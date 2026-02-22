# What is yuf?

**yuf** (pronounced "[jʌf]" or "[jʊf]") - A smart, fast and lightweight php framework with zero dependencies.

# Basic Usage
First install it with Composer:

```shell
composer require actra/yuf
```

Or you can download the source code and include it manually.

Then you would have a basic `index.php` file like this:

```php
<?php
declare(strict_types=1);

use actra\yuf\Core;
use actra\yuf\core\ContentType;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;

require __DIR__ . '/../vendor/actra/yuf/src/Core.php';
$core = new Core(
    envFilePath: '.env.php',
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
```