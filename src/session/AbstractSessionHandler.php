<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpRequest;
use actra\yuf\exception\UnauthorizedException;
use LogicException;
use Override;
use RuntimeException;
use SessionHandler;
use SessionUpdateTimestampHandlerInterface;
use Throwable;
use UnexpectedValueException;

/**
 * Extension point: starts the PHP session of the request with the security settings of yuf and protects it. The session
 * starts lazily, on the first access (`ensureStarted()`; `NativeSessionStorage` calls it before every access), so a
 * request that never touches the session takes no lock and sends no cookie. `writeClose()` writes the session and
 * releases its lock (`Core` does it after the view, `Session::close()` can do it earlier); a closed session can still
 * be read but not written or started.
 *
 * The session is bound to the remote address and the user agent of the client that created it, expires after
 * `maxLifeTime` without activity, and gets a new ID every 30 minutes. A session that fails one of the first two checks
 * is replaced by a new, empty one (the data of the old session is never handed to another client). A project class
 * extends it to store the sessions elsewhere than in files (`executePreStartActions()`); `FileSessionHandler` is the
 * standard.
 *
 * The session cookie is `Secure` (so sessions need HTTPS), `HttpOnly` and `SameSite` (`Strict`, or `Lax` with
 * `SessionSettings::$isSameSiteStrict = false`); the session ID is only read from the cookie, never from the request
 * input, and PHP's strict mode rejects IDs this server did not issue (`validateId()`: PHP ignores the strict mode of a
 * handler that cannot tell whether an ID exists, so a handler has to implement `sessionExists()`).
 */
abstract class AbstractSessionHandler extends SessionHandler implements SessionUpdateTimestampHandlerInterface
{
    private const string SESSION_CREATED_INDICATOR = 'sessionCreated';
    private const string TRUSTED_REMOTE_ADDRESS_INDICATOR = 'trustedRemoteAddress';
    private const string TRUSTED_USER_AGENT_INDICATOR = 'trustedUserAgent';
    private const string LAST_ACTIVITY_INDICATOR = 'lastActivity';
    private const int ID_REGENERATION_INTERVAL_IN_SECONDS = 1800;
    /** Characters PHP accepts in a session ID, at most 256 of them. */
    private const string VALID_SESSION_ID_PATTERN = '/^[A-Za-z0-9,-]{1,256}$/D';
    private int $currentTime;
    private ?string $id = null;
    private bool $isStarted = false;
    private bool $isClosed = false;
    private string $clientRemoteAddress;
    private string $clientUserAgent;

    protected function __construct(
        private readonly HttpRequest $httpRequest,
        private readonly SessionSettings $sessionSettings,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->currentTime = $this->clock->now()->getTimestamp();
        $this->clientRemoteAddress = $httpRequest->getRemoteAddress();
        $this->clientUserAgent = $httpRequest->getUserAgent();
    }

    /**
     * Starts the session if that did not happen yet (settings, save handler, `session_start()` with strict mode, new /
     * untrusted / expired session, ID regeneration, last activity). Starting sets the session cookie, so it has to
     * happen before the response is sent.
     *
     * @throws LogicException if the session was closed before it was started, or the response was already sent
     * @throws UnauthorizedException if the session file belongs to another user of the server
     */
    public function ensureStarted(): void
    {
        if ($this->isStarted) {
            return;
        }
        if ($this->isClosed) {
            throw new LogicException(message: 'The session cannot be started: it was closed before its first use.');
        }
        if ($this->isOutputSent()) {
            throw new LogicException(
                message: 'The session cannot be started after the response was sent: access the session while the'
                . ' view runs.',
            );
        }
        $sessionSettings = $this->sessionSettings;
        $this->setDefaultConfigurationOptions(sessionSettings: $sessionSettings);
        $this->setDefaultSecuritySettings(isSameSiteStrict: $sessionSettings->isSameSiteStrict);
        $this->setSessionName(individualName: $sessionSettings->individualName);
        $this->executePreStartActions();
        // Named parameters are not supported for alternative prototypes: https://github.com/php/php-src/issues/17263
        session_set_save_handler($this, true);
        $this->startNativeSession();
        $this->isStarted = true;
        if (!$this->isSessionCreated()) {
            $this->initDefaultSessionData();
        } elseif (!$this->isTrustedClient() || $this->isSessionExpired()) {
            // Real session lifetime and regeneration after maxLifeTime
            // See: https://stackoverflow.com/a/1270960
            $this->replaceSession();
        } elseif ($this->isSessionOlderThanRegenerationInterval()) {
            $this->regenerateId();
        }

        $this->setLastAction();
    }

    /**
     * Whether output was sent already, so the session cookie cannot be sent any more.
     */
    protected function isOutputSent(): bool
    {
        return headers_sent();
    }

    /**
     * Whether the session was started in this request (it has a cookie then).
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * Whether the session was closed: it can be read, but not written, regenerated or started.
     */
    public function isClosed(): bool
    {
        return $this->isClosed;
    }

    /**
     * Writes the session and releases its lock, so parallel requests of the user do not wait for this one any longer.
     * Does nothing if the session is already closed. A session that was not started is not started by this, but it
     * cannot be started afterwards.
     *
     * @throws RuntimeException if the session could not be written
     */
    public function writeClose(): void
    {
        if ($this->isClosed) {
            return;
        }
        if ($this->isStarted && !session_write_close()) {
            throw new RuntimeException(message: 'The session could not be written.');
        }
        $this->isClosed = true;
    }

    /**
     * @throws LogicException if the session is closed
     */
    private function assertNotClosed(): void
    {
        if ($this->isClosed) {
            throw new LogicException(message: 'The session is closed: it cannot be changed any more.');
        }
    }

    private function startNativeSession(): void
    {
        try {
            session_start(options: [
                'use_strict_mode' => true,
            ]);
        } catch (Throwable $throwable) {
            // The session file belongs to another user of the server: not a session of this client
            if (str_contains(haystack: $throwable->getMessage(), needle: 'Permission denied')) {
                throw new UnauthorizedException();
            }
            throw $throwable;
        }
    }

    private function setDefaultConfigurationOptions(SessionSettings $sessionSettings): void
    {
        ini_set(option: 'session.gc_divisor', value: $sessionSettings->gcDivisor);
        ini_set(option: 'session.gc_maxlifetime', value: $sessionSettings->maxLifeTime);
        ini_set(option: 'session.gc_probability', value: $sessionSettings->gcProbability);
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
        // The session ID is never accepted from the URL or the request input, and never put into URLs
        ini_set(option: 'session.use_only_cookies', value: true);
        ini_set(option: 'session.use_trans_sid', value: false);

        // Prevent cross-domain information leakage
        // See https://www.thinktecture.com/de/identity/samesite/samesite-in-a-nutshell/ for further explanations
        ini_set(option: 'session.cookie_samesite', value: $isSameSiteStrict ? 'Strict' : 'Lax');
    }

    private function setSessionName(string $individualName): void
    {
        if ($individualName !== '') {
            session_name(name: $individualName);
        }

        // Just generate a new session id if current from cookie contains illegal characters
        // Inspired from https://stackoverflow.com/q/32898857
        $sessionName = AbstractSessionHandler::readSessionName();
        $sessionId = $this->httpRequest->getCookie(name: $sessionName);
        if ($sessionId === null) {
            return;
        }
        if (preg_match(pattern: AbstractSessionHandler::VALID_SESSION_ID_PATTERN, subject: $sessionId) !== 1) {
            // `session_start()` reads the session ID from `$_COOKIE` itself: the invalid one has to go from there
            unset($_COOKIE[$sessionName]);
        }
    }

    /**
     * Runs before the PHP session is started, e.g. to set the save handler options of a project handler.
     */
    abstract protected function executePreStartActions(): void;

    /**
     * Whether a session with this ID exists in the storage. An ID that does not exist is replaced by a new one
     * (session fixation: nobody can choose the ID of a session).
     */
    abstract protected function sessionExists(string $id): bool;

    #[Override]
    public function validateId(string $id): bool
    {
        return preg_match(pattern: AbstractSessionHandler::VALID_SESSION_ID_PATTERN, subject: $id) === 1
            && $this->sessionExists(id: $id);
    }

    #[Override]
    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write(id: $id, data: $data);
    }

    private function isSessionCreated(): bool
    {
        return $this->readHandlerValue(key: AbstractSessionHandler::SESSION_CREATED_INDICATOR) !== null;
    }

    /**
     * A session without trusted address or user agent counts as untrusted.
     */
    private function isTrustedClient(): bool
    {
        return $this->readHandlerValue(key: AbstractSessionHandler::TRUSTED_REMOTE_ADDRESS_INDICATOR)
            === $this->clientRemoteAddress
            && $this->readHandlerValue(key: AbstractSessionHandler::TRUSTED_USER_AGENT_INDICATOR)
            === $this->clientUserAgent;
    }

    /**
     * Throws away the data and the ID of the session (untrusted client or expired session) and starts a new one.
     *
     * @throws RuntimeException if the ID of the session could not be changed
     */
    private function replaceSession(): void
    {
        $oldId = AbstractSessionHandler::readSessionId();
        $this->expireSessionCookie();
        // Whatever happens next: the data of the old session is gone
        $_SESSION = [];
        $this->ignoreDestructionFailure(action: static fn(): bool => session_destroy());
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->startNativeSession();
        }
        $this->ignoreDestructionFailure(action: static fn(): bool => session_regenerate_id(delete_old_session: true));
        $this->id = AbstractSessionHandler::readSessionId();
        if ($this->id === $oldId) {
            throw new RuntimeException(message: 'The session could not be replaced by a new one.');
        }
        $this->initDefaultSessionData();
    }

    private function expireSessionCookie(): void
    {
        if (ini_get(option: 'session.use_cookies') !== '1') {
            return;
        }
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

    /**
     * A session whose storage is already gone (e.g. collected by the garbage collection in the meantime) cannot be
     * destroyed, which PHP reports as a warning. The session is replaced anyway.
     *
     * @param callable(): bool $action
     */
    private function ignoreDestructionFailure(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $throwable) {
            if (!str_contains(haystack: $throwable->getMessage(), needle: 'Session object destruction failed')) {
                throw $throwable;
            }
        }
    }

    private function initDefaultSessionData(): void
    {
        $this->setSessionCreated();
        $this->setTrustedRemoteAddress();
        $this->setTrustedUserAgent();
    }

    private function setSessionCreated(): void
    {
        $this->writeHandlerValue(key: AbstractSessionHandler::SESSION_CREATED_INDICATOR, value: $this->currentTime);
    }

    private function setTrustedRemoteAddress(): void
    {
        $this->writeHandlerValue(
            key: AbstractSessionHandler::TRUSTED_REMOTE_ADDRESS_INDICATOR,
            value: $this->clientRemoteAddress,
        );
    }

    private function setTrustedUserAgent(): void
    {
        $this->writeHandlerValue(
            key: AbstractSessionHandler::TRUSTED_USER_AGENT_INDICATOR,
            value: $this->clientUserAgent,
        );
    }

    public function getTrustedRemoteAddress(): string
    {
        $this->ensureStarted();
        $trustedRemoteAddress = $this->readHandlerValue(
            key: AbstractSessionHandler::TRUSTED_REMOTE_ADDRESS_INDICATOR,
        );
        if (!is_string(value: $trustedRemoteAddress)) {
            throw new UnexpectedValueException(message: 'The session contains no trusted remote address.');
        }

        return $trustedRemoteAddress;
    }

    public function getTrustedUserAgent(): string
    {
        $this->ensureStarted();
        $trustedUserAgent = $this->readHandlerValue(key: AbstractSessionHandler::TRUSTED_USER_AGENT_INDICATOR);
        if (!is_string(value: $trustedUserAgent)) {
            throw new UnexpectedValueException(message: 'The session contains no trusted user agent.');
        }

        return $trustedUserAgent;
    }

    /**
     * A session without a (readable) time of the last activity counts as expired.
     */
    private function isSessionExpired(): bool
    {
        $lastActivity = $this->readHandlerValue(key: AbstractSessionHandler::LAST_ACTIVITY_INDICATOR);

        return !is_int(value: $lastActivity)
            || $this->currentTime - $lastActivity > $this->sessionSettings->maxLifeTime;
    }

    private function isSessionOlderThanRegenerationInterval(): bool
    {
        return $this->currentTime - $this->getSessionCreated()
            > AbstractSessionHandler::ID_REGENERATION_INTERVAL_IN_SECONDS;
    }

    public function getSessionCreated(): int
    {
        $this->ensureStarted();
        $sessionCreated = $this->readHandlerValue(key: AbstractSessionHandler::SESSION_CREATED_INDICATOR);
        if (!is_int(value: $sessionCreated)) {
            throw new UnexpectedValueException(message: 'The session contains no creation time.');
        }

        return $sessionCreated;
    }

    /**
     * Gives the session a new ID and deletes the old session (on login, logout and privilege changes).
     */
    public function regenerateId(): void
    {
        $this->ensureStarted();
        $this->assertNotClosed();
        session_regenerate_id(delete_old_session: true);
        $this->id = AbstractSessionHandler::readSessionId();
        $this->setSessionCreated();
    }

    private function setLastAction(): void
    {
        $this->writeHandlerValue(key: AbstractSessionHandler::LAST_ACTIVITY_INDICATOR, value: $this->currentTime);
    }

    public function getId(): string
    {
        $this->ensureStarted();
        if ($this->id === null) {
            $this->id = AbstractSessionHandler::readSessionId();
        }

        return $this->id;
    }

    private static function readSessionId(): string
    {
        $sessionId = session_id();
        if ($sessionId === false) {
            throw new LogicException(message: 'The session ID could not be read.');
        }

        return $sessionId;
    }

    private static function readSessionName(): string
    {
        $sessionName = session_name();
        if ($sessionName === false) {
            throw new LogicException(message: 'The session name could not be read.');
        }

        return $sessionName;
    }

    private function readHandlerValue(string $key): string|int|null
    {
        $handlerData = $this->readHandlerData();
        if (!array_key_exists(key: $key, array: $handlerData)) {
            return null;
        }
        $value = $handlerData[$key];

        return is_string(value: $value) || is_int(value: $value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readYufData(): array
    {
        if (!array_key_exists(key: SessionSectionEnum::ROOT_KEY, array: $_SESSION)) {
            return [];
        }
        $yufData = $_SESSION[SessionSectionEnum::ROOT_KEY];

        return is_array(value: $yufData) ? $yufData : [];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readHandlerData(): array
    {
        $yufData = $this->readYufData();
        if (!array_key_exists(key: SessionSectionEnum::HANDLER->value, array: $yufData)) {
            return [];
        }
        $handlerData = $yufData[SessionSectionEnum::HANDLER->value];

        return is_array(value: $handlerData) ? $handlerData : [];
    }

    private function writeHandlerValue(string $key, string|int $value): void
    {
        $yufData = $this->readYufData();
        $handlerData = $this->readHandlerData();
        $handlerData[$key] = $value;
        $yufData[SessionSectionEnum::HANDLER->value] = $handlerData;
        $_SESSION[SessionSectionEnum::ROOT_KEY] = $yufData;
    }

    /**
     * Does nothing if the session was not started in this request: no session cookie is sent, so none can be changed.
     *
     * @throws LogicException if the session is closed (its cookie is sent already)
     */
    public function changeCookieSameSiteToLax(): void
    {
        $this->changeCookieSameSite(sameSite: 'Lax');
    }

    /**
     * For the return of an identity provider (form post from another site): the cookie must be sent along. Does
     * nothing if the session was not started in this request.
     *
     * @throws LogicException if the session is closed (its cookie is sent already)
     */
    public function changeCookieSameSiteToNone(): void
    {
        $this->changeCookieSameSite(sameSite: 'None');
    }

    /**
     * @param 'Lax'|'None' $sameSite
     */
    private function changeCookieSameSite(string $sameSite): void
    {
        if (!$this->isStarted) {
            return;
        }
        $this->assertNotClosed();
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Prevent from "Session cookie parameters cannot be changed when a session is active" exception
            session_write_close();
        }
        session_set_cookie_params(['samesite' => $sameSite]);
        session_start();
    }
}
