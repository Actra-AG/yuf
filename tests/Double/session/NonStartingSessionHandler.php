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
use LogicException;
use Override;

/**
 * A session handler that never starts a PHP session (the test sets `$_SESSION`): for tests of
 * `NativeSessionStorage`, `Core` and the SameSite change. It records starts, closes, regenerations and SameSite changes
 * and follows the rules of the real handler (no start after the close, no change after the close).
 */
final class NonStartingSessionHandler extends AbstractSessionHandler
{
    public int $starts = 0;
    public int $closes = 0;
    public int $regenerations = 0;
    public int $sameSiteLaxChanges = 0;
    private bool $started = false;
    private bool $closed = false;

    public function __construct()
    {
        parent::__construct(httpRequest: HttpRequestFactory::create(), sessionSettings: new SessionSettings());
    }

    #[Override]
    public function ensureStarted(): void
    {
        if ($this->started) {
            return;
        }
        if ($this->closed) {
            throw new LogicException(message: 'The session cannot be started: it was closed before its first use.');
        }
        $this->started = true;
        $this->starts++;
    }

    #[Override]
    public function isStarted(): bool
    {
        return $this->started;
    }

    #[Override]
    public function isClosed(): bool
    {
        return $this->closed;
    }

    #[Override]
    public function writeClose(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->closes++;
    }

    #[Override]
    protected function executePreStartActions(): void {}

    #[Override]
    protected function sessionExists(string $id): bool
    {
        return false;
    }

    #[Override]
    public function changeCookieSameSiteToLax(): void
    {
        $this->sameSiteLaxChanges++;
    }

    #[Override]
    public function getId(): string
    {
        $this->ensureStarted();

        return 'native-test-session-' . $this->regenerations;
    }

    #[Override]
    public function regenerateId(): void
    {
        $this->ensureStarted();
        $this->regenerations++;
    }
}
