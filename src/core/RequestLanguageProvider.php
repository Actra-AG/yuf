<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\session\Session;

/**
 * The hook of the application that decides first about the language of a request on a route with
 * `usesRequestLanguage: true` (see `RequestLanguageResolver`), e.g. the profile language of the logged-in user.
 */
interface RequestLanguageProvider
{
    /**
     * @param ?Session $session The session of the request (`null` without sessions); reading it starts it
     *
     * @return ?string The code of the language of the request; `null` (or a code that is not available): no decision,
     *     the session, then the browser decides
     */
    public function findLanguageCode(HttpRequest $httpRequest, ?Session $session): ?string;
}
