<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use actra\autoloader\Autoloader;
use actra\autoloader\AutoloaderPath;

require __DIR__ . '/../vendor/autoload.php';

// yuf classes are loaded by actra/autoloader, as in production (not by Composer)
$autoloaderCacheFilePath = __DIR__ . '/../.phpunit.cache/autoloader.php';
if (file_exists(filename: $autoloaderCacheFilePath)) {
    // Avoid stale class paths after files have been moved
    unlink(filename: $autoloaderCacheFilePath);
}
$autoloader = Autoloader::register(cacheFilePath: $autoloaderCacheFilePath);
$autoloader->addPath(
    autoloaderPath: new AutoloaderPath(
        path: __DIR__ . '/../src/',
        prefix: 'actra\\yuf\\'
    )
);
$autoloader->addPath(
    autoloaderPath: new AutoloaderPath(
        path: __DIR__ . '/',
        prefix: 'actra\\yuf\\tests\\'
    )
);