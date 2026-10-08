<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\CoreSettings;
use actra\yuf\core\EnvironmentSettings;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A fresh temporary application directory (`app/` with the directories of `Core` and a view `sample` of the group
 * `frontend` that renders `<p>Hello</p>`, and plain error pages) for one test, removed by `cleanUp()`. Its
 * `createSettings()` gives the `CoreSettings` for `new Core(…)`, so tests need no environment file and no globals.
 */
final class CoreWorkDirectory
{
    public readonly string $baseDirectory;
    public readonly string $appDirectory;
    public readonly string $viewDirectory;

    public function __construct()
    {
        $this->baseDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-core-test-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        $this->appDirectory = $this->baseDirectory . 'app' . DIRECTORY_SEPARATOR;
        $this->viewDirectory = $this->appDirectory . 'view' . DIRECTORY_SEPARATOR;
        foreach (['cache', 'error_docs', 'logs', 'settings', 'snippets', 'view/frontend/html'] as $directory) {
            mkdir(directory: $this->appDirectory . $directory, recursive: true);
        }
        file_put_contents(filename: $this->viewDirectory . 'frontend/html/sample.html', data: '<p>Hello</p>');
        foreach (['default.html', 'notFound.html', 'unauthorized.html', 'debug.html'] as $errorPage) {
            file_put_contents(
                filename: $this->appDirectory . 'error_docs/' . $errorPage,
                data: '<h1>Error page ' . $errorPage . '</h1>',
            );
        }
    }

    /**
     * @param list<string> $allowedDomains
     */
    public function createSettings(
        int $copyrightYear = 2020,
        array $allowedDomains = ['example.com'],
        bool $debug = false,
    ): CoreSettings {
        return new CoreSettings(
            environmentSettings: new EnvironmentSettings(
                errorReporting: E_ALL,
                timeZone: 'UTC',
                allowedDomains: $allowedDomains,
                logEmailRecipient: '',
                debug: $debug,
                robots: 'noindex',
                values: ['mailer.hostname' => 'mail.example.com'],
            ),
            copyrightYear: $copyrightYear,
            documentRoot: $this->baseDirectory . 'public' . DIRECTORY_SEPARATOR,
            frameworkDirectory: dirname(path: __DIR__, levels: 3) . '/src/',
            baseDirectory: $this->baseDirectory,
            appDirectory: $this->appDirectory,
            cacheDirectory: $this->appDirectory . 'cache' . DIRECTORY_SEPARATOR,
            errorDocsDirectory: $this->appDirectory . 'error_docs' . DIRECTORY_SEPARATOR,
            logDirectory: $this->appDirectory . 'logs' . DIRECTORY_SEPARATOR,
            settingsDirectory: $this->appDirectory . 'settings' . DIRECTORY_SEPARATOR,
            snippetsDirectory: $this->appDirectory . 'snippets' . DIRECTORY_SEPARATOR,
            viewDirectory: $this->viewDirectory,
        );
    }

    public function cleanUp(): void
    {
        if (!is_dir(filename: $this->baseDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            iterator: new RecursiveDirectoryIterator(
                directory: $this->baseDirectory,
                flags: FilesystemIterator::SKIP_DOTS,
            ),
            mode: RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                rmdir(directory: $entry->getPathname());
            } else {
                unlink(filename: $entry->getPathname());
            }
        }
        rmdir(directory: $this->baseDirectory);
    }
}
