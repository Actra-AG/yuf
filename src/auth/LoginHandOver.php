<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\LoginRedirect;
use actra\yuf\exception\UnauthorizedException;
use InvalidArgumentException;
use JsonException;
use LogicException;
use RuntimeException;

/**
 * Hands a login on a login host over to sites on the same server, through a directory that all of them share. URLs
 * carry only random IDs; everything else stays on the server:
 *
 * 1. Site (`startLogin()`, called by `LoginTarget::remote()`): stores a login request (its host, the requested path,
 *    its hand-over path, the browser) and redirects to the login page with `?loginRequest=<ID>`.
 * 2. Login host (`findLoginRequest()`, after the login `createHandOverUrl()`): checks the request, binds it to the
 *    browser on the login host, uses it up and stores a ticket for the user, valid 60 seconds; redirects to
 *    `https://<site><hand-over path>?loginTicket=<ID>`.
 * 3. Site (`redeemTicket()`): uses the ticket up and checks host, expiry and browser; the project logs the user in.
 *
 * Requests and tickets are bound to the browser by a random secret in the cookie `__Host-yuf-login-browser` (one per
 * host; only hashes are stored), so a ticket of another account cannot be pushed to a visitor (login CSRF). IDs are 32
 * random bytes; files are named by their SHA-256 hash, written atomically and used up by an atomic rename.
 *
 * Whoever can write the directory can log in on every site: give it to the user of the web server only (`0700`,
 * outside the document root; a directory that group or others may write is refused). Reading it reveals no usable
 * secret (only hashes of IDs and browser secrets are stored). All hosts must run on one server as the same user.
 */
final readonly class LoginHandOver
{
    public const string REQUEST_PARAMETER = 'loginRequest';
    public const string TICKET_PARAMETER = 'loginTicket';
    public const string BROWSER_COOKIE_NAME = '__Host-yuf-login-browser';
    public const int REQUEST_LIFETIME_IN_SECONDS = 900;
    public const int TICKET_LIFETIME_IN_SECONDS = 60;
    private const int ID_BYTES = 32;
    private const string ID_PATTERN = '/^[0-9a-f]{64}$/D';
    private const string FILE_PATTERN = '/^(?:request|ticket)-[0-9a-f]{64}\.json(?:\.[0-9a-f]{16}\.claimed)?$'
        . '|^\.tmp-[0-9a-f]{16}$/D';
    private const string REQUEST_PREFIX = 'request-';
    private const string TICKET_PREFIX = 'ticket-';
    private const int DIRECTORY_MODE = 0o700;
    private const int FILE_MODE = 0o600;
    private const int MAX_PURGED_FILES = 1000;

    private string $directory;

    /**
     * @param string $directory The directory shared by the login host and the sites, outside the document root;
     *     created with mode `0700` if missing
     * @param int $purgeProbabilityPercent Chance per `startLogin()` to delete expired files (`purgeExpired()`)
     *
     * @throws InvalidArgumentException if the purge probability is not between 0 and 100
     */
    public function __construct(
        string $directory,
        private Clock $clock = new SystemClock(),
        private SsoNonceCookieWriter $cookieWriter = new NativeSsoNonceCookieWriter(),
        private int $purgeProbabilityPercent = 1,
    ) {
        if ($purgeProbabilityPercent < 0 || $purgeProbabilityPercent > 100) {
            throw new InvalidArgumentException(message: 'The purge probability must be between 0 and 100.');
        }
        $this->directory = rtrim(string: $directory, characters: '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Site: stores a login request for the requested page and returns its ID for the URL of the login page
     * (`REQUEST_PARAMETER`). Sends the browser cookie. A request URI that is no local path returns to "/".
     *
     * @param string $handOverPath The local path (without query) of the page of this site that redeems the ticket
     *
     * @throws InvalidArgumentException if the hand-over path is no local path without query
     * @throws LogicException if the host of the request has a port or is no host name
     * @throws RuntimeException if the directory cannot be written
     */
    public function startLogin(HttpRequest $httpRequest, string $handOverPath): string
    {
        LoginHandOver::assertHandOverPath(handOverPath: $handOverPath);
        $host = strtolower(string: $httpRequest->getHost());
        if (!LoginHandOver::isHostName(host: $host)) {
            throw new LogicException(message: 'The hand-over of a login needs a host name without port.');
        }
        $returnPath = LoginRedirect::isLocalPath(uri: $httpRequest->getUri()) ? $httpRequest->getUri() : '/';
        $requestId = LoginHandOver::createId();
        $this->write(
            fileName: LoginHandOver::fileName(prefix: LoginHandOver::REQUEST_PREFIX, id: $requestId),
            record: [
                'host' => $host,
                'returnPath' => $returnPath,
                'handOverPath' => $handOverPath,
                'siteBrowserHash' => LoginHandOver::hash(secret: $this->issueBrowserSecret(httpRequest: $httpRequest)),
                'loginBrowserHash' => null,
            ],
            expiresAt: $this->now() + LoginHandOver::REQUEST_LIFETIME_IN_SECONDS,
        );
        if ($this->purgeProbabilityPercent > 0 && random_int(min: 1, max: 100) <= $this->purgeProbabilityPercent) {
            $this->purgeExpired();
        }

        return $requestId;
    }

    /**
     * Login host: the valid login request of the ID (from `REQUEST_PARAMETER`, carried through the steps of the
     * login), `null` if it is unknown, expired, used, for a host that is not allowed or started on the login host in
     * another browser. The first call binds the request to this browser (browser cookie of the login host).
     *
     * @param list<string> $allowedHosts The lowercase host names of the sites the login host serves
     *
     * @throws InvalidArgumentException if an allowed host is no lowercase host name
     * @throws RuntimeException if the directory cannot be written
     */
    public function findLoginRequest(HttpRequest $httpRequest, string $requestId, array $allowedHosts): ?LoginRequest
    {
        LoginHandOver::assertAllowedHosts(allowedHosts: $allowedHosts);
        if (preg_match(pattern: LoginHandOver::ID_PATTERN, subject: $requestId) !== 1) {
            return null;
        }
        $fileName = LoginHandOver::fileName(prefix: LoginHandOver::REQUEST_PREFIX, id: $requestId);
        $record = $this->read(fileName: $fileName);
        $host = LoginHandOver::readString(record: $record, key: 'host');
        $expiresAt = LoginHandOver::readInt(record: $record, key: 'expiresAt');
        if (
            $record === null
            || $host === null
            || $expiresAt === null
            || !$this->isValidRequest(record: $record, allowedHosts: $allowedHosts)
        ) {
            return null;
        }
        $browserHash = LoginHandOver::hash(secret: $this->issueBrowserSecret(httpRequest: $httpRequest));
        $loginBrowserHash = LoginHandOver::readString(record: $record, key: 'loginBrowserHash');
        if ($loginBrowserHash === null) {
            $this->write(
                fileName: $fileName,
                record: [...$record, 'loginBrowserHash' => $browserHash],
                expiresAt: $expiresAt,
            );
        } elseif (!hash_equals(known_string: $loginBrowserHash, user_string: $browserHash)) {
            return null;
        }

        return new LoginRequest(id: $requestId, host: $host, expiresAt: $expiresAt);
    }

    /**
     * Login host, after a successful login: uses the login request up, stores a ticket for the user and returns the
     * URL of the hand-over page of the site with the ticket ID (`TICKET_PARAMETER`).
     *
     * @param string $requestId The ID of `findLoginRequest()`
     * @param string $subject The ID of the user, the same on the login host and on the site
     * @param list<string> $allowedHosts The allowed hosts of `findLoginRequest()`
     *
     * @throws InvalidArgumentException if the subject is empty or an allowed host is no lowercase host name
     * @throws UnauthorizedException if the request is unknown, expired, used, for a host that is not allowed or of
     *     another browser
     * @throws RuntimeException if the directory cannot be written
     */
    public function createHandOverUrl(
        HttpRequest $httpRequest,
        string $requestId,
        string $subject,
        array $allowedHosts,
    ): string {
        if ($subject === '') {
            throw new InvalidArgumentException(message: 'The subject must not be empty.');
        }
        LoginHandOver::assertAllowedHosts(allowedHosts: $allowedHosts);
        if (preg_match(pattern: LoginHandOver::ID_PATTERN, subject: $requestId) !== 1) {
            throw new UnauthorizedException(message: 'Invalid login request');
        }
        $record = $this->claim(
            fileName: LoginHandOver::fileName(prefix: LoginHandOver::REQUEST_PREFIX, id: $requestId),
        );
        $host = LoginHandOver::readString(record: $record, key: 'host');
        $returnPath = LoginHandOver::readString(record: $record, key: 'returnPath');
        $handOverPath = LoginHandOver::readString(record: $record, key: 'handOverPath');
        $siteBrowserHash = LoginHandOver::readString(record: $record, key: 'siteBrowserHash');
        if (
            $record === null
            || $host === null
            || $returnPath === null
            || $handOverPath === null
            || $siteBrowserHash === null
            || !$this->isValidRequest(record: $record, allowedHosts: $allowedHosts)
        ) {
            throw new UnauthorizedException(message: 'Unknown, expired or used login request');
        }
        $loginBrowserHash = LoginHandOver::readString(record: $record, key: 'loginBrowserHash');
        if (
            $loginBrowserHash === null
            || !$this->isBrowser(httpRequest: $httpRequest, browserHash: $loginBrowserHash)
        ) {
            throw new UnauthorizedException(message: 'Login request of another browser');
        }
        $ticketId = LoginHandOver::createId();
        $this->write(
            fileName: LoginHandOver::fileName(prefix: LoginHandOver::TICKET_PREFIX, id: $ticketId),
            record: [
                'host' => $host,
                'returnPath' => $returnPath,
                'subject' => $subject,
                'siteBrowserHash' => $siteBrowserHash,
            ],
            expiresAt: $this->now() + LoginHandOver::TICKET_LIFETIME_IN_SECONDS,
        );

        return 'https://' . $host . $handOverPath . '?' . LoginHandOver::TICKET_PARAMETER . '=' . $ticketId;
    }

    /**
     * Site, on the hand-over page: uses the ticket of the request (`TICKET_PARAMETER`) up and returns the user and the
     * path to redirect to. Log the user in with `Authenticator::logInIdentifiedUser()` (`AuthMethodEnum::SSO`).
     *
     * @throws UnauthorizedException if the ticket is missing, unknown, used, expired, for another host or of another
     *     browser (it is used up in every case)
     * @throws RuntimeException if the directory cannot be read
     */
    public function redeemTicket(HttpRequest $httpRequest): LoginHandOverTicket
    {
        $ticketId = $httpRequest->getQueryString(name: LoginHandOver::TICKET_PARAMETER);
        if ($ticketId === null || preg_match(pattern: LoginHandOver::ID_PATTERN, subject: $ticketId) !== 1) {
            throw new UnauthorizedException(message: 'Missing or invalid login ticket');
        }
        $record = $this->claim(fileName: LoginHandOver::fileName(prefix: LoginHandOver::TICKET_PREFIX, id: $ticketId));
        if ($record === null) {
            throw new UnauthorizedException(message: 'Unknown or used login ticket');
        }
        $subject = LoginHandOver::readString(record: $record, key: 'subject');
        $returnPath = LoginHandOver::readString(record: $record, key: 'returnPath');
        $expiresAt = LoginHandOver::readInt(record: $record, key: 'expiresAt');
        $siteBrowserHash = LoginHandOver::readString(record: $record, key: 'siteBrowserHash');
        if (
            $subject === null
            || $subject === ''
            || $returnPath === null
            || !LoginRedirect::isLocalPath(uri: $returnPath)
            || $expiresAt === null
            || $expiresAt <= $this->now()
            || LoginHandOver::readString(record: $record, key: 'host') !== strtolower(string: $httpRequest->getHost())
        ) {
            throw new UnauthorizedException(message: 'Expired login ticket or ticket of another host');
        }
        if ($siteBrowserHash === null || !$this->isBrowser(httpRequest: $httpRequest, browserHash: $siteBrowserHash)) {
            throw new UnauthorizedException(message: 'Login ticket of another browser');
        }

        return new LoginHandOverTicket(subject: $subject, returnPath: $returnPath);
    }

    /**
     * Deletes expired requests and tickets (and left-overs of interrupted writes), at most 1000 per call.
     *
     * @return int The number of deleted files
     */
    public function purgeExpired(): int
    {
        if (!is_dir(filename: $this->directory)) {
            return 0;
        }
        $now = $this->now();
        $purged = 0;
        set_error_handler(callback: static fn(): bool => true);
        try {
            $directoryHandle = opendir(directory: $this->directory);
            if ($directoryHandle === false) {
                return 0;
            }
            clearstatcache();
            while (
                $purged < LoginHandOver::MAX_PURGED_FILES
                && ($fileName = readdir(dir_handle: $directoryHandle)) !== false
            ) {
                if (preg_match(pattern: LoginHandOver::FILE_PATTERN, subject: $fileName) !== 1) {
                    continue;
                }
                $expiresAt = filemtime(filename: $this->directory . $fileName);
                if ($expiresAt !== false && $expiresAt <= $now && unlink(filename: $this->directory . $fileName)) {
                    $purged++;
                }
            }
            closedir(dir_handle: $directoryHandle);
        } finally {
            restore_error_handler();
        }

        return $purged;
    }

    /**
     * Not expired, for an allowed host, with a hand-over path and a return path that are local paths.
     *
     * @param array<string, string|int|null> $record
     * @param list<string> $allowedHosts
     */
    private function isValidRequest(array $record, array $allowedHosts): bool
    {
        $expiresAt = LoginHandOver::readInt(record: $record, key: 'expiresAt');
        $host = LoginHandOver::readString(record: $record, key: 'host');
        $returnPath = LoginHandOver::readString(record: $record, key: 'returnPath');
        $handOverPath = LoginHandOver::readString(record: $record, key: 'handOverPath');

        return $expiresAt !== null
            && $expiresAt > $this->now()
            && in_array(needle: $host, haystack: $allowedHosts, strict: true)
            && $returnPath !== null
            && LoginRedirect::isLocalPath(uri: $returnPath)
            && $handOverPath !== null
            && LoginRedirect::isLocalPath(uri: $handOverPath)
            && !str_contains(haystack: $handOverPath, needle: '?')
            && LoginHandOver::readString(record: $record, key: 'siteBrowserHash') !== null;
    }

    /**
     * @param ?array<string, string|int|null> $record
     */
    private static function readString(?array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return is_string(value: $value) ? $value : null;
    }

    /**
     * @param ?array<string, string|int|null> $record
     */
    private static function readInt(?array $record, string $key): ?int
    {
        $value = $record[$key] ?? null;

        return is_int(value: $value) ? $value : null;
    }

    private function isBrowser(HttpRequest $httpRequest, string $browserHash): bool
    {
        $secret = $httpRequest->getCookie(name: LoginHandOver::BROWSER_COOKIE_NAME);

        return $secret !== null
            && preg_match(pattern: LoginHandOver::ID_PATTERN, subject: $secret) === 1
            && hash_equals(known_string: $browserHash, user_string: LoginHandOver::hash(secret: $secret));
    }

    /**
     * The browser secret of the cookie if it is valid, else a new one; the cookie is sent (again) in both cases, so
     * logins in several tabs share it.
     */
    private function issueBrowserSecret(HttpRequest $httpRequest): string
    {
        $secret = $httpRequest->getCookie(name: LoginHandOver::BROWSER_COOKIE_NAME);
        if ($secret === null || preg_match(pattern: LoginHandOver::ID_PATTERN, subject: $secret) !== 1) {
            $secret = LoginHandOver::createId();
        }
        $this->cookieWriter->set(
            name: LoginHandOver::BROWSER_COOKIE_NAME,
            value: $secret,
            maxAgeInSeconds: LoginHandOver::REQUEST_LIFETIME_IN_SECONDS,
            path: '/',
        );

        return $secret;
    }

    /**
     * @param array<string, string|int|null> $record
     *
     * @throws InvalidArgumentException if a value is no valid UTF-8
     * @throws RuntimeException
     */
    private function write(string $fileName, array $record, int $expiresAt): void
    {
        try {
            $json = json_encode(value: [...$record, 'expiresAt' => $expiresAt], flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(message: 'The login hand-over data is no valid UTF-8.');
        }
        $temporaryPath = $this->directory . '.tmp-' . bin2hex(string: random_bytes(length: 8));
        set_error_handler(callback: static fn(): bool => true);
        try {
            $this->createDirectory();
            if (
                file_put_contents(filename: $temporaryPath, data: $json) !== strlen(string: $json)
                || !chmod(filename: $temporaryPath, permissions: LoginHandOver::FILE_MODE)
                || !touch(filename: $temporaryPath, mtime: $expiresAt)
                || !rename(from: $temporaryPath, to: $this->directory . $fileName)
            ) {
                throw new RuntimeException(message: 'Cannot write to the directory "' . $this->directory . '".');
            }
        } finally {
            if (is_file(filename: $temporaryPath)) {
                unlink(filename: $temporaryPath);
            }
            restore_error_handler();
        }
    }

    /**
     * @return ?array<string, string|int|null>
     */
    private function read(string $fileName): ?array
    {
        set_error_handler(callback: static fn(): bool => true);
        try {
            $json = file_get_contents(filename: $this->directory . $fileName);
        } finally {
            restore_error_handler();
        }

        return $json === false ? null : LoginHandOver::decode(json: $json);
    }

    /**
     * Reads the file and deletes it, atomically: of two parallel calls only one gets the record.
     *
     * @return ?array<string, string|int|null>
     */
    private function claim(string $fileName): ?array
    {
        $claimedPath = $this->directory . $fileName . '.' . bin2hex(string: random_bytes(length: 8)) . '.claimed';
        set_error_handler(callback: static fn(): bool => true);
        try {
            if (!rename(from: $this->directory . $fileName, to: $claimedPath)) {
                return null;
            }
            $json = file_get_contents(filename: $claimedPath);
            unlink(filename: $claimedPath);
        } finally {
            restore_error_handler();
        }

        return $json === false ? null : LoginHandOver::decode(json: $json);
    }

    /**
     * @return ?array<string, string|int|null>
     */
    private static function decode(string $json): ?array
    {
        try {
            $data = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array(value: $data)) {
            return null;
        }
        $record = [];
        foreach ($data as $key => $value) {
            if (!is_string(value: $key) || !(is_string(value: $value) || is_int(value: $value) || $value === null)) {
                return null;
            }
            $record[$key] = $value;
        }

        return $record;
    }

    /**
     * Creates the directory (`0700`) if it is missing; refuses one that group or others may write.
     *
     * @throws RuntimeException
     */
    private function createDirectory(): void
    {
        if (is_dir(filename: $this->directory)) {
            clearstatcache(clear_realpath_cache: false, filename: $this->directory);
            $permissions = fileperms(filename: $this->directory);
            if ($permissions === false || ($permissions & 0o022) !== 0) {
                throw new RuntimeException(
                    message: 'The directory "' . $this->directory . '" must not be writable by group or others.',
                );
            }

            return;
        }
        if (
            !mkdir(directory: $this->directory, permissions: LoginHandOver::DIRECTORY_MODE, recursive: true)
            && !is_dir(filename: $this->directory)
        ) {
            throw new RuntimeException(message: 'Cannot create the directory "' . $this->directory . '".');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertHandOverPath(string $handOverPath): void
    {
        if (!LoginRedirect::isLocalPath(uri: $handOverPath) || str_contains(haystack: $handOverPath, needle: '?')) {
            throw new InvalidArgumentException(message: 'The hand-over path must be a local path without query.');
        }
    }

    /**
     * @param list<string> $allowedHosts
     *
     * @throws InvalidArgumentException
     */
    private static function assertAllowedHosts(array $allowedHosts): void
    {
        foreach ($allowedHosts as $allowedHost) {
            if (!LoginHandOver::isHostName(host: $allowedHost)) {
                throw new InvalidArgumentException(message: 'Invalid allowed host: ' . $allowedHost);
            }
        }
    }

    private static function isHostName(string $host): bool
    {
        return LoginRedirect::findHttpsHost(url: 'https://' . $host . '/') === $host;
    }

    private static function createId(): string
    {
        return bin2hex(string: random_bytes(length: LoginHandOver::ID_BYTES));
    }

    private static function fileName(string $prefix, string $id): string
    {
        return $prefix . LoginHandOver::hash(secret: $id) . '.json';
    }

    private static function hash(string $secret): string
    {
        return hash(algo: 'sha256', data: $secret);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
