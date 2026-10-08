<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * What `Core` needs to know about the application: the environment settings, the resolved directories (absolute
 * paths with a trailing separator, existing) and the first year of the copyright notice. `Core::fromEnvironment()`
 * creates it from the environment file and creates the directories; tests create it directly.
 */
final readonly class CoreSettings
{
    public function __construct(
        public EnvironmentSettings $environmentSettings,
        public int $copyrightYear,
        public string $documentRoot,
        public string $frameworkDirectory,
        public string $baseDirectory,
        public string $appDirectory,
        public string $cacheDirectory,
        public string $errorDocsDirectory,
        public string $logDirectory,
        public string $settingsDirectory,
        public string $snippetsDirectory,
        public string $viewDirectory,
    ) {}
}
