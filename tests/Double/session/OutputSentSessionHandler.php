<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\session;

use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\session\SessionSettings;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use Override;

/**
 * A session handler of a request whose response was sent already (in the CLI of PHPUnit, output is held back, so the
 * real state cannot be produced in a test).
 */
final class OutputSentSessionHandler extends AbstractSessionHandler
{
    public function __construct()
    {
        parent::__construct(httpRequest: HttpRequestFactory::create(), sessionSettings: new SessionSettings());
    }

    #[Override]
    protected function isOutputSent(): bool
    {
        return true;
    }

    #[Override]
    protected function executePreStartActions(): void {}

    #[Override]
    protected function sessionExists(string $id): bool
    {
        return false;
    }
}
