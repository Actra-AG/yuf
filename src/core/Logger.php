<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use Throwable;

/**
 * Extension point: where the exceptions and messages of the application are logged. `FileLogger` is the
 * implementation of yuf; a project or a test provides its own (`prepareHttpResponse(logger: …)`).
 */
interface Logger
{
    public function logException(Throwable $throwable): void;

    public function logMessage(string $message): void;
}
