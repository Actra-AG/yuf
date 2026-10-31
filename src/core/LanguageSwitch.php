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
 * The language switcher of routes with `usesRequestLanguage`: a link or a form (GET or POST) with the parameters
 * `language` and `returnTo` sets the language of the session (`SessionPreferredLanguage`) and leads back to the page.
 * `App::withLanguageSwitchRoute()` serves it at a path; `createUri()` builds the links.
 *
 * It only changes the language of the user, so it has no CSRF protection. A `RequestLanguageProvider` that returns a
 * language (e.g. of the user profile) still wins over the session.
 */
final readonly class LanguageSwitch
{
    public const string LANGUAGE_PARAMETER = 'language';
    public const string RETURN_PARAMETER = 'returnTo';

    public function __construct(
        private HttpRequest $httpRequest,
        private Session $session,
        private LanguageCollection $availableLanguages,
    ) {}

    /**
     * The link of the switcher to a language that leads back to the given page (not added if it is no local path).
     */
    public static function createUri(string $switchPath, string $languageCode, string $returnUri): string
    {
        $query = [LanguageSwitch::LANGUAGE_PARAMETER => $languageCode];
        if (LoginRedirect::isLocalPath(uri: $returnUri)) {
            $query[LanguageSwitch::RETURN_PARAMETER] = $returnUri;
        }

        return $switchPath . (str_contains(haystack: $switchPath, needle: '?') ? '&' : '?')
            . http_build_query(data: $query, encoding_type: PHP_QUERY_RFC3986);
    }

    /**
     * Sets the requested language in the session if it is available (an unknown or missing one changes nothing) and
     * returns the local path to go back to: `returnTo` if it is a local path, else `$defaultPath`.
     */
    public function apply(string $defaultPath = '/'): string
    {
        $languageCode = $this->readParameter(name: LanguageSwitch::LANGUAGE_PARAMETER);
        $language = $languageCode === null
            ? null
            : $this->availableLanguages->getLanguageByCode(languageCode: $languageCode);
        if ($language !== null) {
            $preferredLanguage = new SessionPreferredLanguage(session: $this->session);
            if ($preferredLanguage->getCode() !== $language->code) {
                $preferredLanguage->set(language: $language);
            }
        }
        $returnUri = $this->readParameter(name: LanguageSwitch::RETURN_PARAMETER);

        return $returnUri !== null && LoginRedirect::isLocalPath(uri: $returnUri) ? $returnUri : $defaultPath;
    }

    private function readParameter(string $name): ?string
    {
        return $this->httpRequest->getPostString(name: $name) ?? $this->httpRequest->getQueryString(name: $name);
    }
}
