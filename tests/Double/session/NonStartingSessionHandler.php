<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\session;

use actra\yuf\session\AbstractSessionHandler;
use Override;

/**
 * A session handler that does not start a session: for `NativeSessionStorage` tests, which only need its ID and the
 * regeneration.
 */
final class NonStartingSessionHandler extends AbstractSessionHandler
{
    public int $regenerations = 0;

    /**
     * Does not start a session.
     */
    public function __construct() {} // @phpstan-ignore constructor.missingParentCall (the parent starts a session)

    #[Override]
    protected function executePreStartActions(): void {}

    #[Override]
    protected function sessionExists(string $id): bool
    {
        return false;
    }

    #[Override]
    public function getId(): string
    {
        return 'native-test-session-' . $this->regenerations;
    }

    #[Override]
    public function regenerateId(): void
    {
        $this->regenerations++;
    }
}
