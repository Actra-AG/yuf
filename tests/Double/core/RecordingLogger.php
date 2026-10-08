<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\Logger;
use Override;
use Throwable;

/**
 * A logger that records the logged exceptions and messages instead of writing files or sending mails.
 */
final class RecordingLogger implements Logger
{
    /** @var list<Throwable> */
    public array $loggedExceptions = [];
    /** @var list<string> */
    public array $loggedMessages = [];

    #[Override]
    public function logException(Throwable $throwable): void
    {
        $this->loggedExceptions[] = $throwable;
    }

    #[Override]
    public function logMessage(string $message): void
    {
        $this->loggedMessages[] = $message;
    }
}
