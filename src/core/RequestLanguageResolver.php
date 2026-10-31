<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\session\Session;
use actra\yuf\session\SessionPreferredLanguage;

/**
 * The language of a request on a route with `usesRequestLanguage: true`, the first available one of:
 * 1. the language of the `RequestLanguageProvider` of the application (e.g. the profile of the logged-in user),
 * 2. the language chosen in the session (`SessionPreferredLanguage`), only of an active session (a visitor without
 *    a session cookie gets none),
 * 3. the first browser language (`Accept-Language`) that is available,
 * 4. the first available language.
 */
final readonly class RequestLanguageResolver
{
    public function __construct(
        private HttpRequest $httpRequest,
        private LanguageCollection $availableLanguages,
        private ?Session $session,
        private ?RequestLanguageProvider $requestLanguageProvider = null,
    ) {}

    /**
     * @return ?Language `null` only without available languages
     */
    public function resolve(): ?Language
    {
        if ($this->availableLanguages->isEmpty()) {
            return null;
        }
        $providedCode = $this->requestLanguageProvider?->findLanguageCode(
            httpRequest: $this->httpRequest,
            session: $this->session,
        );
        $providedLanguage = $providedCode === null
            ? null
            : $this->availableLanguages->getLanguageByCode(languageCode: $providedCode);
        if ($providedLanguage !== null) {
            return $providedLanguage;
        }
        $sessionCode = $this->session === null || !$this->session->isActive()
            ? null
            : new SessionPreferredLanguage(session: $this->session)->getCode();
        $sessionLanguage = $sessionCode === null
            ? null
            : $this->availableLanguages->getLanguageByCode(languageCode: $sessionCode);
        if ($sessionLanguage !== null) {
            return $sessionLanguage;
        }
        foreach ($this->httpRequest->listBrowserLanguagesByQuality() as $browserCode) {
            $browserLanguage = $this->availableLanguages->getLanguageByCode(languageCode: $browserCode);
            if ($browserLanguage !== null) {
                return $browserLanguage;
            }
        }

        return $this->availableLanguages->getFirstLanguage();
    }
}
