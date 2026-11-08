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
 *
 * To keep the visitor on the site (its own login page, only redirects over the login host), use `SharedLogin`.
 */
final readonly class LoginHandOver
{
    public const string REQUEST_PARAMETER = 'loginRequest';
    public const string TICKET_PARAMETER = 'loginTicket';
    public const string BROWSER_COOKIE_NAME = LoginHandOverStore::BROWSER_COOKIE_NAME;
    public const int REQUEST_LIFETIME_IN_SECONDS = 900;
    public const int TICKET_LIFETIME_IN_SECONDS = 60;
    private const string REQUEST_PREFIX = 'request';
    private const string TICKET_PREFIX = 'ticket';

    private LoginHandOverStore $store;

    /**
     * @param string $directory The directory shared by the login host and the sites, outside the document root;
     *     created with mode `0700` if missing
     * @param int $purgeProbabilityPercent Chance per `startLogin()` to delete expired files (`purgeExpired()`)
     *
     * @throws InvalidArgumentException if the purge probability is not between 0 and 100
     */
    public function __construct(
        string $directory,
        Clock $clock = new SystemClock(),
        SsoNonceCookieWriter $cookieWriter = new NativeSsoNonceCookieWriter(),
        int $purgeProbabilityPercent = 1,
    ) {
        $this->store = new LoginHandOverStore(
            directory: $directory,
            clock: $clock,
            cookieWriter: $cookieWriter,
            purgeProbabilityPercent: $purgeProbabilityPercent,
        );
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
        if (!LoginHandOverStore::isPathWithoutQuery(path: $handOverPath)) {
            throw new InvalidArgumentException(message: 'The hand-over path must be a local path without query.');
        }
        $host = strtolower(string: $httpRequest->getHost());
        if (!LoginHandOverStore::isHostName(host: $host)) {
            throw new LogicException(message: 'The hand-over of a login needs a host name without port.');
        }
        $returnPath = LoginRedirect::isLocalPath(uri: $httpRequest->getUri()) ? $httpRequest->getUri() : '/';
        $requestId = LoginHandOverStore::createId();
        $this->store->write(
            prefix: LoginHandOver::REQUEST_PREFIX,
            id: $requestId,
            record: [
                'host' => $host,
                'returnPath' => $returnPath,
                'handOverPath' => $handOverPath,
                'siteBrowserHash' => $this->store->bindBrowser(httpRequest: $httpRequest),
                'loginBrowserHash' => null,
            ],
            expiresAt: $this->store->now() + LoginHandOver::REQUEST_LIFETIME_IN_SECONDS,
        );
        $this->store->purgeByChance();

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
        LoginHandOverStore::assertAllowedHosts(allowedHosts: $allowedHosts);
        $record = $this->store->read(prefix: LoginHandOver::REQUEST_PREFIX, id: $requestId);
        $host = LoginHandOverStore::readString(record: $record, key: 'host');
        $expiresAt = LoginHandOverStore::readInt(record: $record, key: 'expiresAt');
        if (
            $record === null
            || $host === null
            || $expiresAt === null
            || !$this->isValidRequest(record: $record, allowedHosts: $allowedHosts)
        ) {
            return null;
        }
        $browserHash = $this->store->bindBrowser(httpRequest: $httpRequest);
        $loginBrowserHash = LoginHandOverStore::readString(record: $record, key: 'loginBrowserHash');
        if ($loginBrowserHash === null) {
            $this->store->write(
                prefix: LoginHandOver::REQUEST_PREFIX,
                id: $requestId,
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
        LoginHandOverStore::assertAllowedHosts(allowedHosts: $allowedHosts);
        if (!LoginHandOverStore::isId(id: $requestId)) {
            throw new UnauthorizedException(message: 'Invalid login request');
        }
        $record = $this->store->claim(prefix: LoginHandOver::REQUEST_PREFIX, id: $requestId);
        $host = LoginHandOverStore::readString(record: $record, key: 'host');
        $returnPath = LoginHandOverStore::readString(record: $record, key: 'returnPath');
        $handOverPath = LoginHandOverStore::readString(record: $record, key: 'handOverPath');
        $siteBrowserHash = LoginHandOverStore::readString(record: $record, key: 'siteBrowserHash');
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
        if (
            !$this->store->isBrowser(
                httpRequest: $httpRequest,
                browserHash: LoginHandOverStore::readString(record: $record, key: 'loginBrowserHash'),
            )
        ) {
            throw new UnauthorizedException(message: 'Login request of another browser');
        }
        $ticketId = LoginHandOverStore::createId();
        $this->store->write(
            prefix: LoginHandOver::TICKET_PREFIX,
            id: $ticketId,
            record: [
                'host' => $host,
                'returnPath' => $returnPath,
                'subject' => $subject,
                'siteBrowserHash' => $siteBrowserHash,
            ],
            expiresAt: $this->store->now() + LoginHandOver::TICKET_LIFETIME_IN_SECONDS,
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
        if ($ticketId === null || !LoginHandOverStore::isId(id: $ticketId)) {
            throw new UnauthorizedException(message: 'Missing or invalid login ticket');
        }
        $record = $this->store->claim(prefix: LoginHandOver::TICKET_PREFIX, id: $ticketId);
        if ($record === null) {
            throw new UnauthorizedException(message: 'Unknown or used login ticket');
        }
        $subject = LoginHandOverStore::readString(record: $record, key: 'subject');
        $returnPath = LoginHandOverStore::readString(record: $record, key: 'returnPath');
        if (
            $subject === null
            || $subject === ''
            || $returnPath === null
            || !LoginRedirect::isLocalPath(uri: $returnPath)
            || !$this->store->isUnexpired(record: $record)
            || LoginHandOverStore::readString(record: $record, key: 'host') !== strtolower(
                string: $httpRequest->getHost(),
            )
        ) {
            throw new UnauthorizedException(message: 'Expired login ticket or ticket of another host');
        }
        if (
            !$this->store->isBrowser(
                httpRequest: $httpRequest,
                browserHash: LoginHandOverStore::readString(record: $record, key: 'siteBrowserHash'),
            )
        ) {
            throw new UnauthorizedException(message: 'Login ticket of another browser');
        }

        return new LoginHandOverTicket(subject: $subject, returnPath: $returnPath);
    }

    /**
     * Deletes expired requests and tickets (and left-overs of interrupted writes, also of `SharedLogin`), at most
     * 1000 per call.
     *
     * @return int The number of deleted files
     */
    public function purgeExpired(): int
    {
        return $this->store->purgeExpired();
    }

    /**
     * Not expired, for an allowed host, with a hand-over path and a return path that are local paths.
     *
     * @param array<string, string|int|null> $record
     * @param list<string> $allowedHosts
     */
    private function isValidRequest(array $record, array $allowedHosts): bool
    {
        $returnPath = LoginHandOverStore::readString(record: $record, key: 'returnPath');
        $handOverPath = LoginHandOverStore::readString(record: $record, key: 'handOverPath');

        return $this->store->isUnexpired(record: $record)
            && in_array(
                needle: LoginHandOverStore::readString(record: $record, key: 'host'),
                haystack: $allowedHosts,
                strict: true,
            )
            && $returnPath !== null
            && LoginRedirect::isLocalPath(uri: $returnPath)
            && $handOverPath !== null
            && LoginHandOverStore::isPathWithoutQuery(path: $handOverPath)
            && LoginHandOverStore::readString(record: $record, key: 'siteBrowserHash') !== null;
    }
}
