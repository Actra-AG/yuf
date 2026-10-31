<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\session\Session;
use Override;

/**
 * Returns a fixed language code (like the profile language of a logged-in user) and records the calls.
 */
final class FixedRequestLanguageProvider implements RequestLanguageProvider
{
    public int $calls = 0;
    public ?Session $receivedSession = null;

    public function __construct(private readonly ?string $languageCode) {}

    #[Override]
    public function findLanguageCode(HttpRequest $httpRequest, ?Session $session): ?string
    {
        $this->calls++;
        $this->receivedSession = $session;

        return $this->languageCode;
    }
}
