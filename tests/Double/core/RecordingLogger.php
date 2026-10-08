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
 * A logger that records the logged exceptions instead of writing files or sending mails.
 */
final class RecordingLogger extends Logger
{
    /** @var list<Throwable> */
    public array $loggedExceptions = [];

    public function __construct()
    {
        parent::__construct(
            logEmailRecipient: '',
            logDirectory: sys_get_temp_dir(),
            httpRequest: HttpRequestFactory::create(),
        );
    }

    #[Override]
    public function logException(Throwable $throwable): void
    {
        $this->loggedExceptions[] = $throwable;
    }
}
