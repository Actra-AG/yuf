<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\session;

use actra\yuf\session\AbstractSessionHandler;
use Override;
use ReflectionProperty;

/**
 * A session handler that does not start a session and only counts the regenerations of the session ID. Code that
 * reaches the handler through the static `AbstractSessionHandler::getSessionHandler()` needs it registered: call
 * `install()` in `setUp()` and `uninstall()` in `tearDown()` (removed with the static handler holder in
 * docs/session/plan.md, step 2).
 */
final class NonStartingSessionHandler extends AbstractSessionHandler
{
    public int $regenerations = 0;

    /**
     * Does not start a session.
     */
    public function __construct() {} // @phpstan-ignore constructor.missingParentCall (the parent starts a session)

    /**
     * Registers a new handler in the static holder of `AbstractSessionHandler` (through reflection, because the
     * holder has no reset method).
     */
    public static function install(): NonStartingSessionHandler
    {
        $sessionHandler = new NonStartingSessionHandler();
        NonStartingSessionHandler::holder()->setValue(null, $sessionHandler);

        return $sessionHandler;
    }

    public static function uninstall(): void
    {
        NonStartingSessionHandler::holder()->setValue(null, null);
    }

    private static function holder(): ReflectionProperty
    {
        return new ReflectionProperty(class: AbstractSessionHandler::class, property: 'abstractSessionHandler');
    }

    #[Override]
    protected function executePreStartActions(): void {}

    #[Override]
    public function regenerateId(): void
    {
        $this->regenerations++;
    }
}
