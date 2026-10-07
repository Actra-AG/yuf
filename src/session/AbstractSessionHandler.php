<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\Core;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\Language;
use actra\yuf\exception\UnauthorizedException;
use Exception;
use LogicException;
use SessionHandler;
use Throwable;
use UnexpectedValueException;

abstract class AbstractSessionHandler extends SessionHandler
{
    private const string SESSION_CREATED_INDICATOR = 'sessionCreated';
    private const string TRUSTED_REMOTE_ADDRESS_INDICATOR = 'trustedRemoteAddress';
    private const string TRUSTED_USER_AGENT_INDICATOR = 'trustedUserAgent';
    private const string LAST_ACTIVITY_INDICATOR = 'lastActivity';
    private const string PREFERRED_LANGUAGE_INDICATOR = 'preferredLanguage';
    /**
     * Session data that is not bound to the user: the session handler needs its own data on every request, and the
     * preferred language stays for the next user of the browser.
     */
    private const array SESSION_KEYS_WITHOUT_USER_DATA = [
        AbstractSessionHandler::SESSION_CREATED_INDICATOR,
        AbstractSessionHandler::TRUSTED_REMOTE_ADDRESS_INDICATOR,
        AbstractSessionHandler::TRUSTED_USER_AGENT_INDICATOR,
        AbstractSessionHandler::LAST_ACTIVITY_INDICATOR,
        AbstractSessionHandler::PREFERRED_LANGUAGE_INDICATOR,
    ];
    private static false|AbstractSessionHandler|null $abstractSessionHandler = null;
    public private(set) ?string $name = null {
        get {
            if ($this->name === null) {
                $this->name = AbstractSessionHandler::readSessionName();
            }

            return $this->name;
        }
    }
    public private(set) ?string $fingerprint = null {
        get {
            if ($this->fingerprint === null) {
                $this->fingerprint = hash(
                    algo: 'sha256',
                    data: $this->getID() . $this->clientUserAgent,
                );
            }

            return $this->fingerprint;
        }
    }
    private int $currentTime;
    private ?string $ID = null;
    private string $clientRemoteAddress;
    private string $clientUserAgent;

    protected function __construct(
        private readonly SessionSettings $sessionSettings,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->currentTime = $this->clock->now()->getTimestamp();
        $this->clientRemoteAddress = HttpRequest::getRemoteAddress();
        $this->clientUserAgent = HttpRequest::getUserAgent();

        $this->start();
    }

    private function start(): void
    {
        $sessionSettings = $this->sessionSettings;
        $this->setDefaultConfigurationOptions(
            gcDivisor: $sessionSettings->gcDivisor,
            maxLifeTime: $sessionSettings->maxLifeTime,
            gcProbability: $sessionSettings->gcProbability,
        );
        $this->setDefaultSecuritySettings(isSameSiteStrict: $sessionSettings->isSameSiteStrict);
        $this->setSessionName(individualName: $sessionSettings->individualName);
        $this->executePreStartActions();
        session_set_save_handler( // Named parameters are not supported for alternative prototypes: https://github.com/php/php-src/issues/17263
            $this,
            true,
        );
        try {
            session_start(options: [
                'use_strict_mode' => true,
            ]);
        } catch (Throwable $throwable) {
            if (str_contains(haystack: $throwable->getMessage(), needle: 'Permission denied')) {
                throw new UnauthorizedException();
            }
            throw $throwable;
        }
        if (!$this->isSessionCreated()) {
            $this->initDefaultSessionData(destroyCurrentSessionData: false);
        } elseif ($this->getTrustedRemoteAddress() !== $this->clientRemoteAddress || $this->getTrustedUserAgent(
        ) !== $this->clientUserAgent) {
            $this->initDefaultSessionData(destroyCurrentSessionData: true);
        } elseif ($this->isSessionExpired()) {
            // Real session lifetime and regeneration after maxLifeTime
            // See: http://stackoverflow.com/questions/520237/how-do-i-expire-a-php-session-after-30-minutes/1270960#1270960
            $this->initDefaultSessionData(destroyCurrentSessionData: true);
        } elseif ($this->isSessionOlderThan30Minutes()) {
            $this->regenerateID();
        }

        $this->setLastAction();
    }

    private function setDefaultConfigurationOptions(
        ?int $gcDivisor,
        ?int $maxLifeTime,
        ?int $gcProbability,
    ): void {
        if ($gcDivisor !== null) {
            ini_set(option: 'session.gc_divisor', value: $gcDivisor);
        }
        if ($maxLifeTime !== null) {
            ini_set(option: 'session.gc_maxlifetime', value: $maxLifeTime);
        }
        if ($gcProbability !== null) {
            ini_set(option: 'session.gc_probability', value: $gcProbability);
        }
    }

    private function setDefaultSecuritySettings(bool $isSameSiteStrict): void
    {
        // Set security-related configuration options,
        // See https://php.net/manual/en/session.configuration.php
        // -------------------------------------------
        // Send cookies only over HTTPS
        ini_set(option: 'session.cookie_secure', value: true);
        // Do not allow JS to access cookie vars (helps to reduce identity theft through XSS attacks)
        ini_set(option: 'session.cookie_httponly', value: true);
        // Prevent session fixation; very recommended
        ini_set(option: 'session.use_strict_mode', value: true);

        // Prevent cross-domain information leakage
        // See https://www.thinktecture.com/de/identity/samesite/samesite-in-a-nutshell/ for further explanations
        ini_set(option: 'session.cookie_samesite', value: $isSameSiteStrict ? 'Strict' : 'Lax');
    }

    private function setSessionName(string $individualName): void
    {
        // The session ID is only read from the cookie, never from the request input (prevents session fixation)
        if ($individualName !== '') {
            session_name(name: $individualName);
        }

        // Just generate a new session id if current from cookie contains illegal characters
        // Inspired from http://stackoverflow.com/questions/32898857/session-start-issues-regarding-illegal-characters-empty-session-id-and-failed
        $sessionName = AbstractSessionHandler::readSessionName();
        if (!array_key_exists(key: $sessionName, array: $_COOKIE)) {
            return;
        }
        $sessionID = $_COOKIE[$sessionName];
        if (!is_string(value: $sessionID) || !$this->checkSessionIdAgainstSidBitsPerChar(
            sessionId: $sessionID,
            sidBitsPerChar: (int) ini_get(option: 'session.sid_bits_per_character'),
        )) {
            unset($_COOKIE[$sessionName]);
        }
    }

    /**
     * Checks session id against valid characters based on the session.sid_bits_per_character ini setting
     * (http://php.net/manual/en/session.configuration.php#ini.session.sid-bits-per-character)
     *
     * @param string $sessionId The session id to check (for example, cookie or get value)
     * @param int $sidBitsPerChar The session.sid_bits_per_character value (4, 5 or 6)
     *
     * @return bool Returns true if session_id is valid or false if not
     */
    protected function checkSessionIdAgainstSidBitsPerChar(string $sessionId, int $sidBitsPerChar): bool
    {
        if ($sidBitsPerChar === 4 && preg_match(pattern: '/^[a-f\d]+$/', subject: $sessionId) === 0) {
            return false;
        }

        if ($sidBitsPerChar === 5 && preg_match(pattern: '/^[a-v\d]+$/', subject: $sessionId) === 0) {
            return false;
        }

        if ($sidBitsPerChar === 6 && preg_match(pattern: '/^[A-Za-z\d\-,]+$/i', subject: $sessionId) === 0) {
            return false;
        }

        return true;
    }

    abstract protected function executePreStartActions(): void;

    private function isSessionCreated(): bool
    {
        return array_key_exists(key: AbstractSessionHandler::SESSION_CREATED_INDICATOR, array: $_SESSION);
    }

    private function initDefaultSessionData(bool $destroyCurrentSessionData): void
    {
        if ($destroyCurrentSessionData) {
            try {
                if (ini_get(option: 'session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(
                        AbstractSessionHandler::readSessionName(),
                        '',
                        $this->currentTime - 42000,
                        $params['path'],
                        $params['domain'],
                        $params['secure'],
                        $params['httponly'],
                    );
                }
                session_destroy();
                session_start(options: [
                    'use_strict_mode' => true,
                ]);
                session_regenerate_id(delete_old_session: true);
                $this->ID = AbstractSessionHandler::readSessionID();
            } catch (Throwable $throwable) {
                if (!str_contains(haystack: $throwable->getMessage(), needle: 'Session object destruction failed')) {
                    throw $throwable;
                }
            }
        }
        $this->setSessionCreated();
        $this->setTrustedRemoteAddress();
        $this->setTrustedUserAgent();
    }

    private function setSessionCreated(): void
    {
        $_SESSION[AbstractSessionHandler::SESSION_CREATED_INDICATOR] = $this->currentTime;
    }

    private function setTrustedRemoteAddress(): void
    {
        $_SESSION[AbstractSessionHandler::TRUSTED_REMOTE_ADDRESS_INDICATOR] = $this->clientRemoteAddress;
    }

    public function setTrustedUserAgent(): void
    {
        $_SESSION[AbstractSessionHandler::TRUSTED_USER_AGENT_INDICATOR] = $this->clientUserAgent;
    }

    public function getTrustedRemoteAddress(): string
    {
        $trustedRemoteAddress = $_SESSION[AbstractSessionHandler::TRUSTED_REMOTE_ADDRESS_INDICATOR] ?? null;
        if (!is_string(value: $trustedRemoteAddress)) {
            throw new UnexpectedValueException(message: 'The session contains no trusted remote address.');
        }

        return $trustedRemoteAddress;
    }

    public function getTrustedUserAgent(): string
    {
        $trustedUserAgent = $_SESSION[AbstractSessionHandler::TRUSTED_USER_AGENT_INDICATOR] ?? null;
        if (!is_string(value: $trustedUserAgent)) {
            throw new UnexpectedValueException(message: 'The session contains no trusted user agent.');
        }

        return $trustedUserAgent;
    }

    private function isSessionExpired(): bool
    {
        $lastActivity = $_SESSION[AbstractSessionHandler::LAST_ACTIVITY_INDICATOR] ?? null;

        return (
            is_int(value: $lastActivity)
            && ($this->currentTime - $lastActivity > $this->sessionSettings->maxLifeTime)
        );
    }

    private function isSessionOlderThan30Minutes(): bool
    {
        return ($this->currentTime - $this->getSessionCreated() > 1800);
    }

    public function getSessionCreated(): int
    {
        $sessionCreated = $_SESSION[AbstractSessionHandler::SESSION_CREATED_INDICATOR] ?? null;
        if (!is_int(value: $sessionCreated)) {
            throw new UnexpectedValueException(message: 'The session contains no creation time.');
        }

        return $sessionCreated;
    }

    public function regenerateID(): void
    {
        session_regenerate_id(delete_old_session: true);
        $this->ID = AbstractSessionHandler::readSessionID();
        $this->setSessionCreated();
    }

    private function setLastAction(): void
    {
        $_SESSION[AbstractSessionHandler::LAST_ACTIVITY_INDICATOR] = $this->currentTime;
    }

    public static function register(false|AbstractSessionHandler $individualSessionHandler): void
    {
        if (AbstractSessionHandler::$abstractSessionHandler !== null) {
            throw new LogicException(message: 'SessionHandler handler is already registered.');
        }
        AbstractSessionHandler::$abstractSessionHandler = $individualSessionHandler;
    }

    public static function enabled(): bool
    {
        return (array_key_exists(
            key: '_SESSION',
            array: $GLOBALS,
        ));
    }

    public static function getSessionHandler(): AbstractSessionHandler
    {
        $abstractSessionHandler = AbstractSessionHandler::$abstractSessionHandler;
        if (!$abstractSessionHandler instanceof AbstractSessionHandler) {
            throw new LogicException(
                message: 'No session handler is registered. Register one with AbstractSessionHandler::register().',
            );
        }

        return $abstractSessionHandler;
    }

    /**
     * Removes all data of the user from the session, e.g. on logout (breadcrumb, table and search state, uploads, CSRF
     * token, login state, project data, …). Keeps only the data of the session handler and the preferred
     * language. Does nothing if sessions are disabled.
     */
    public static function clearUserData(): void
    {
        if (!AbstractSessionHandler::enabled()) {
            return;
        }
        $_SESSION = array_intersect_key(
            $_SESSION,
            array_flip(array: AbstractSessionHandler::SESSION_KEYS_WITHOUT_USER_DATA),
        );
    }

    public function getID(): string
    {
        if ($this->ID === null) {
            $this->ID = AbstractSessionHandler::readSessionID();
        }

        return $this->ID;
    }

    private static function readSessionID(): string
    {
        $sessionID = session_id();
        if ($sessionID === false) {
            throw new LogicException(message: 'The session ID could not be read.');
        }

        return $sessionID;
    }

    private static function readSessionName(): string
    {
        $sessionName = session_name();
        if ($sessionName === false) {
            throw new LogicException(message: 'The session name could not be read.');
        }

        return $sessionName;
    }

    public function setPreferredLanguage(Language $language): void
    {
        if (!Core::get()->availableLanguages->hasLanguage(languageCode: $language->code)) {
            throw new Exception(message: 'The preferred language ' . $language->code . ' is not available');
        }

        $_SESSION[AbstractSessionHandler::PREFERRED_LANGUAGE_INDICATOR] = $language->code;
    }

    public function getPreferredLanguageCode(): ?string
    {
        $preferredLanguageCode = $_SESSION[AbstractSessionHandler::PREFERRED_LANGUAGE_INDICATOR] ?? null;

        return is_string(value: $preferredLanguageCode) ? $preferredLanguageCode : null;
    }

    public function changeCookieSameSiteToLax(): void
    {
        if ((session_status() === PHP_SESSION_ACTIVE)) {
            // Prevent from "Session cookie parameters cannot be changed when a session is active" exception
            session_write_close();
        }
        session_set_cookie_params(['samesite' => 'Lax']);
        session_start();
    }

    public function changeCookieSameSiteToNone(): void
    {
        if ((session_status() === PHP_SESSION_ACTIVE)) {
            // Prevent from "Session cookie parameters cannot be changed when a session is active" exception
            session_write_close();
        }
        session_set_cookie_params(['samesite' => 'None']);
        session_start();
    }
}
