<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\core\Language;

/**
 * The language the user chose last: kept in the data of the session handler, so it survives
 * `Session::clearUserData()` (the next user of the browser gets the same language).
 */
final readonly class SessionPreferredLanguage
{
    private const string KEY = 'preferredLanguage';

    public function __construct(private Session $session) {}

    public function getCode(): ?string
    {
        $code = $this->session->getSection(section: SessionSectionEnum::HANDLER)[SessionPreferredLanguage::KEY] ?? null;

        return is_string(value: $code) ? $code : null;
    }

    public function set(Language $language): void
    {
        $handlerData = $this->session->getSection(section: SessionSectionEnum::HANDLER);
        $handlerData[SessionPreferredLanguage::KEY] = $language->code;
        $this->session->setSection(section: SessionSectionEnum::HANDLER, data: $handlerData);
    }
}
