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
 * Sites on one server share the login of a login host, but the visitor stays on the site: the login runs on the site's
 * own login page, only redirects pass the login host (on its hand-over page, which shows nothing). Same directory and
 * trust boundary as `LoginHandOver` (docs/session-and-login.md, "Shared login"):
 *
 * - **Check** (site without login, on the login page or a page that needs a login, once per browser session): site
 *   (`createCheckUrl()`) → login host (`receiveOnLoginHost()`: answers with the login it has or "none") → site
 *   (`receiveOnSite()`: logs the user in and returns to the page, or remembers the check and shows its login page).
 * - **Push** (after a login on the site): site (`createPushUrl()`) → login host (binds the step to its browser) → site
 *   (checks its browser) → login host (logs the same user in) → site (the requested page).
 * - **Logout**: `end()` on any host; every host logs out at its next request when `isEnded()` is `true`.
 *
 * URLs carry only random IDs, which change at every hop and are used once; records expire after 60 seconds, are bound
 * to the browser (cookie `__Host-yuf-login-browser`) and lead back only to stored local paths of allowed hosts.
 */
final readonly class SharedLogin
{
    public const string CHECK_PARAMETER = 'loginCheck';
    public const string ANSWER_PARAMETER = 'loginAnswer';
    public const string PUSH_PARAMETER = 'loginPush';
    public const string CHECKED_PARAMETER = 'loginChecked';
    public const string CHECKED_COOKIE_NAME = '__Host-yuf-login-checked';
    public const int STEP_LIFETIME_IN_SECONDS = 60;
    public const int CHECKED_LIFETIME_IN_SECONDS = 1800;
    private const string CHECK_PREFIX = 'check';
    private const string ANSWER_PREFIX = 'answer';
    private const string PUSH_PREFIX = 'push';
    private const string ENDED_PREFIX = 'ended';
    private const string PUSH_STARTED = 'started';
    private const string PUSH_BOUND = 'bound';
    private const string PUSH_CONFIRMED = 'confirmed';

    private LoginHandOverStore $store;

    /**
     * @param string $directory The directory shared by the login host and the sites (the one of `LoginHandOver`),
     *     outside the document root; created with mode `0700` if missing
     * @param int $loginLifetimeInSeconds The longest time a login lasts on any of the hosts (session lifetime): an
     *     ended shared login is remembered that long (`end()`)
     * @param int $purgeProbabilityPercent Chance per check and push to delete expired files
     *
     * @throws InvalidArgumentException if the login lifetime is less than one second or the purge probability is not
     *     between 0 and 100
     */
    public function __construct(
        string $directory,
        private int $loginLifetimeInSeconds,
        Clock $clock = new SystemClock(),
        SsoNonceCookieWriter $cookieWriter = new NativeSsoNonceCookieWriter(),
        int $purgeProbabilityPercent = 1,
    ) {
        if ($loginLifetimeInSeconds < 1) {
            throw new InvalidArgumentException(message: 'The login lifetime must be at least one second.');
        }
        $this->store = new LoginHandOverStore(
            directory: $directory,
            clock: $clock,
            cookieWriter: $cookieWriter,
            purgeProbabilityPercent: $purgeProbabilityPercent,
        );
    }

    /**
     * A new random ID for a login (64 hex characters): store it with every login that is not handed over (the login
     * form of a host), so it can be shared and ended (`SharedLoginUser`).
     */
    public static function createSharedLoginId(): string
    {
        return LoginHandOverStore::createId();
    }

    /**
     * Site: whether this browser was checked at the login host already (cookie `CHECKED_COOKIE_NAME`, or
     * `CHECKED_PARAMETER` in the URL for browsers without cookies): then the site shows its login page without hop.
     */
    public function isChecked(HttpRequest $httpRequest): bool
    {
        return $httpRequest->getCookie(name: SharedLogin::CHECKED_COOKIE_NAME) === '1'
            || $httpRequest->getQueryString(name: SharedLogin::CHECKED_PARAMETER) === '1';
    }

    /**
     * Site without login, on its login page or a page that needs a login (never on other pages): stores a check and
     * returns the URL of the hand-over page of the login host to redirect to. Sends the browser cookie.
     *
     * @param string $returnPath The page to return to after the login (a path that is no local path returns to "/")
     *
     * @throws LogicException if the host of the request has a port or is no host name
     * @throws RuntimeException if the directory cannot be written
     */
    public function createCheckUrl(HttpRequest $httpRequest, SharedLoginSite $site, string $returnPath): string
    {
        $checkId = LoginHandOverStore::createId();
        $this->store->write(
            prefix: SharedLogin::CHECK_PREFIX,
            id: $checkId,
            record: [
                'host' => SharedLogin::getSiteHost(httpRequest: $httpRequest),
                'returnPath' => LoginRedirect::isLocalPath(uri: $returnPath) ? $returnPath : '/',
                'handOverPath' => $site->handOverPath,
                'siteBrowserHash' => $this->store->bindBrowser(httpRequest: $httpRequest),
            ],
            expiresAt: $this->store->now() + SharedLogin::STEP_LIFETIME_IN_SECONDS,
        );
        $this->store->purgeByChance();

        return $site->loginHostUrl . '?' . SharedLogin::CHECK_PARAMETER . '=' . $checkId;
    }

    /**
     * Site, right after the login on its own login page: stores a push of the login to the login host and returns the
     * URL to redirect to. Send the session cookie as `Lax` with this redirect
     * (`redirectAndExit(sameSiteLaxSessionHandler:)`): the browser returns from the login host to the return path.
     *
     * @param string $returnPath The page to return to (a path that is no local path returns to "/")
     *
     * @throws LogicException if the host of the request has a port or is no host name
     * @throws RuntimeException if the directory cannot be written
     */
    public function createPushUrl(
        HttpRequest $httpRequest,
        SharedLoginSite $site,
        SharedLoginUser $user,
        string $returnPath,
    ): string {
        $pushId = LoginHandOverStore::createId();
        $this->store->write(
            prefix: SharedLogin::PUSH_PREFIX,
            id: $pushId,
            record: [
                'step' => SharedLogin::PUSH_STARTED,
                'host' => SharedLogin::getSiteHost(httpRequest: $httpRequest),
                'returnPath' => LoginRedirect::isLocalPath(uri: $returnPath) ? $returnPath : '/',
                'handOverPath' => $site->handOverPath,
                'loginHostUrl' => $site->loginHostUrl,
                'subject' => $user->subject,
                'sharedLoginId' => $user->sharedLoginId,
                'siteBrowserHash' => $this->store->bindBrowser(httpRequest: $httpRequest),
                'loginBrowserHash' => null,
            ],
            expiresAt: $this->store->now() + SharedLogin::STEP_LIFETIME_IN_SECONDS,
        );
        $this->store->purgeByChance();

        return $site->loginHostUrl . '?' . SharedLogin::PUSH_PARAMETER . '=' . $pushId;
    }

    /**
     * Site, on its hand-over page (a public view, GET): the answer of a check or the second step of a push. Never
     * throws for a bad step: a refused step (unknown, used, expired, other host, other browser, ended login) leads to
     * the login page with `failure` set, and is remembered as check, so there is no loop.
     *
     * @throws RuntimeException if the directory cannot be written
     */
    public function receiveOnSite(HttpRequest $httpRequest, SharedLoginSite $site): SharedLoginStep
    {
        $answerId = $httpRequest->getQueryString(name: SharedLogin::ANSWER_PARAMETER);
        if ($answerId !== null) {
            return $this->receiveAnswer(httpRequest: $httpRequest, site: $site, answerId: $answerId);
        }
        $pushId = $httpRequest->getQueryString(name: SharedLogin::PUSH_PARAMETER);
        if ($pushId !== null) {
            return $this->confirmPush(httpRequest: $httpRequest, site: $site, pushId: $pushId);
        }

        return $this->refuse(site: $site, returnPath: null, failure: 'No step of a shared login');
    }

    /**
     * Login host, on its hand-over page (a public view, GET, that shows nothing): answers a check of a site with the
     * login of this host, or runs the first or the last step of a push (the last one returns the user to log in).
     *
     * @param list<string> $allowedHosts The lowercase host names of the sites the login host serves
     * @param ?SharedLoginUser $user The login on this host, `null` without login
     *
     * @throws InvalidArgumentException if an allowed host is no lowercase host name
     * @throws UnauthorizedException if the step is missing, unknown, used, expired, for a host that is not allowed or
     *     of another browser (there is no safe page of the site to return to)
     * @throws RuntimeException if the directory cannot be written
     */
    public function receiveOnLoginHost(
        HttpRequest $httpRequest,
        array $allowedHosts,
        ?SharedLoginUser $user,
    ): SharedLoginStep {
        LoginHandOverStore::assertAllowedHosts(allowedHosts: $allowedHosts);
        $checkId = $httpRequest->getQueryString(name: SharedLogin::CHECK_PARAMETER);
        if ($checkId !== null) {
            return $this->answerCheck(checkId: $checkId, allowedHosts: $allowedHosts, user: $user);
        }
        $pushId = $httpRequest->getQueryString(name: SharedLogin::PUSH_PARAMETER);
        if ($pushId !== null) {
            return $this->continuePush(httpRequest: $httpRequest, pushId: $pushId, allowedHosts: $allowedHosts);
        }

        throw new UnauthorizedException(message: 'No step of a shared login');
    }

    /**
     * Any host, on logout: ends the shared login on all hosts (they log out at their next request, `isEnded()`).
     *
     * @throws InvalidArgumentException if the ID has another format
     * @throws RuntimeException if the directory cannot be written
     */
    public function end(string $sharedLoginId): void
    {
        if (!LoginHandOverStore::isId(id: $sharedLoginId)) {
            throw new InvalidArgumentException(message: 'Invalid shared login ID.');
        }
        $this->store->write(
            prefix: SharedLogin::ENDED_PREFIX,
            id: $sharedLoginId,
            record: [],
            expiresAt: $this->store->now() + $this->loginLifetimeInSeconds,
        );
    }

    /**
     * Any host, at every request with a login: whether the shared login was ended on a host (log out then). An ID of
     * another format counts as ended.
     */
    public function isEnded(string $sharedLoginId): bool
    {
        if (!LoginHandOverStore::isId(id: $sharedLoginId)) {
            return true;
        }

        return $this->store->isUnexpired(
            record: $this->store->read(prefix: SharedLogin::ENDED_PREFIX, id: $sharedLoginId),
        );
    }

    private function receiveAnswer(HttpRequest $httpRequest, SharedLoginSite $site, string $answerId): SharedLoginStep
    {
        $record = $this->store->claim(prefix: SharedLogin::ANSWER_PREFIX, id: $answerId);
        $returnPath = LoginHandOverStore::readString(record: $record, key: 'returnPath');
        if (
            $record === null
            || $returnPath === null
            || !LoginRedirect::isLocalPath(uri: $returnPath)
            || !$this->store->isUnexpired(record: $record)
            || !$this->isSiteHost(httpRequest: $httpRequest, record: $record)
        ) {
            return $this->refuse(site: $site, returnPath: null, failure: 'Unknown, used or expired answer');
        }
        if (
            !$this->store->isBrowser(
                httpRequest: $httpRequest,
                browserHash: LoginHandOverStore::readString(record: $record, key: 'siteBrowserHash'),
            )
        ) {
            return $this->refuse(site: $site, returnPath: $returnPath, failure: 'Answer of another browser');
        }
        $user = $this->findUser(record: $record);
        if ($user === null) {
            // No login on the login host: show the own login page
            return $this->refuse(site: $site, returnPath: $returnPath, failure: null);
        }

        return new SharedLoginStep(redirectUrl: $returnPath, logIn: $user);
    }

    private function confirmPush(HttpRequest $httpRequest, SharedLoginSite $site, string $pushId): SharedLoginStep
    {
        $record = $this->store->claim(prefix: SharedLogin::PUSH_PREFIX, id: $pushId);
        $returnPath = LoginHandOverStore::readString(record: $record, key: 'returnPath');
        $loginHostUrl = LoginHandOverStore::readString(record: $record, key: 'loginHostUrl');
        $expiresAt = LoginHandOverStore::readInt(record: $record, key: 'expiresAt');
        if (
            $record === null
            || $returnPath === null
            || !LoginRedirect::isLocalPath(uri: $returnPath)
            || $loginHostUrl === null
            || $expiresAt === null
            || LoginHandOverStore::readString(record: $record, key: 'step') !== SharedLogin::PUSH_BOUND
            || !$this->store->isUnexpired(record: $record)
            || !$this->isSiteHost(httpRequest: $httpRequest, record: $record)
        ) {
            return $this->refuse(site: $site, returnPath: null, failure: 'Unknown, used or expired push');
        }
        if (
            !$this->store->isBrowser(
                httpRequest: $httpRequest,
                browserHash: LoginHandOverStore::readString(record: $record, key: 'siteBrowserHash'),
            )
        ) {
            return $this->refuse(site: $site, returnPath: $returnPath, failure: 'Push of another browser');
        }
        $nextId = LoginHandOverStore::createId();
        $this->store->write(
            prefix: SharedLogin::PUSH_PREFIX,
            id: $nextId,
            record: [...$record, 'step' => SharedLogin::PUSH_CONFIRMED],
            expiresAt: $expiresAt,
        );

        return new SharedLoginStep(redirectUrl: $loginHostUrl . '?' . SharedLogin::PUSH_PARAMETER . '=' . $nextId);
    }

    /**
     * @param list<string> $allowedHosts
     *
     * @throws UnauthorizedException
     */
    private function answerCheck(string $checkId, array $allowedHosts, ?SharedLoginUser $user): SharedLoginStep
    {
        $record = $this->store->claim(prefix: SharedLogin::CHECK_PREFIX, id: $checkId);
        if ($record === null || !$this->isValidSiteRecord(record: $record, allowedHosts: $allowedHosts)) {
            throw new UnauthorizedException(message: 'Unknown, used or expired login check');
        }
        if ($user !== null && $this->isEnded(sharedLoginId: $user->sharedLoginId)) {
            $user = null;
        }
        $answerId = LoginHandOverStore::createId();
        $this->store->write(
            prefix: SharedLogin::ANSWER_PREFIX,
            id: $answerId,
            record: [
                'host' => LoginHandOverStore::readString(record: $record, key: 'host'),
                'returnPath' => LoginHandOverStore::readString(record: $record, key: 'returnPath'),
                'subject' => $user?->subject,
                'sharedLoginId' => $user?->sharedLoginId,
                'siteBrowserHash' => LoginHandOverStore::readString(record: $record, key: 'siteBrowserHash'),
            ],
            expiresAt: $this->store->now() + SharedLogin::STEP_LIFETIME_IN_SECONDS,
        );

        return new SharedLoginStep(
            redirectUrl: SharedLogin::createSiteUrl(record: $record, key: 'handOverPath')
                . '?' . SharedLogin::ANSWER_PARAMETER . '=' . $answerId,
        );
    }

    /**
     * @param list<string> $allowedHosts
     *
     * @throws UnauthorizedException
     */
    private function continuePush(HttpRequest $httpRequest, string $pushId, array $allowedHosts): SharedLoginStep
    {
        $record = $this->store->claim(prefix: SharedLogin::PUSH_PREFIX, id: $pushId);
        $expiresAt = LoginHandOverStore::readInt(record: $record, key: 'expiresAt');
        if (
            $record === null
            || $expiresAt === null
            || !$this->isValidSiteRecord(record: $record, allowedHosts: $allowedHosts)
        ) {
            throw new UnauthorizedException(message: 'Unknown, used or expired push');
        }
        $step = LoginHandOverStore::readString(record: $record, key: 'step');
        if ($step === SharedLogin::PUSH_STARTED) {
            $nextId = LoginHandOverStore::createId();
            $this->store->write(
                prefix: SharedLogin::PUSH_PREFIX,
                id: $nextId,
                record: [
                    ...$record,
                    'step' => SharedLogin::PUSH_BOUND,
                    'loginBrowserHash' => $this->store->bindBrowser(httpRequest: $httpRequest),
                ],
                expiresAt: $expiresAt,
            );

            return new SharedLoginStep(
                redirectUrl: SharedLogin::createSiteUrl(record: $record, key: 'handOverPath')
                    . '?' . SharedLogin::PUSH_PARAMETER . '=' . $nextId,
            );
        }
        if ($step !== SharedLogin::PUSH_CONFIRMED) {
            throw new UnauthorizedException(message: 'Push in a wrong step');
        }
        if (
            !$this->store->isBrowser(
                httpRequest: $httpRequest,
                browserHash: LoginHandOverStore::readString(record: $record, key: 'loginBrowserHash'),
            )
        ) {
            throw new UnauthorizedException(message: 'Push of another browser');
        }
        $user = $this->findUser(record: $record);
        if ($user === null) {
            throw new UnauthorizedException(message: 'Push of an ended login');
        }

        return new SharedLoginStep(
            redirectUrl: SharedLogin::createSiteUrl(record: $record, key: 'returnPath'),
            logIn: $user,
        );
    }

    /**
     * Not expired, for an allowed host, with a hand-over path and a return path that are local paths and a browser.
     *
     * @param array<string, string|int|null> $record
     * @param list<string> $allowedHosts
     */
    private function isValidSiteRecord(array $record, array $allowedHosts): bool
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

    /**
     * @param array<string, string|int|null> $record
     */
    private function isSiteHost(HttpRequest $httpRequest, array $record): bool
    {
        return LoginHandOverStore::readString(record: $record, key: 'host') === strtolower(
            string: $httpRequest->getHost(),
        );
    }

    /**
     * The user of the record, `null` if it has none or its shared login was ended.
     *
     * @param array<string, string|int|null> $record
     */
    private function findUser(array $record): ?SharedLoginUser
    {
        $subject = LoginHandOverStore::readString(record: $record, key: 'subject');
        $sharedLoginId = LoginHandOverStore::readString(record: $record, key: 'sharedLoginId');
        if (
            $subject === null
            || $subject === ''
            || $sharedLoginId === null
            || $this->isEnded(sharedLoginId: $sharedLoginId)
        ) {
            return null;
        }

        return new SharedLoginUser(subject: $subject, sharedLoginId: $sharedLoginId);
    }

    /**
     * The login page of the site, remembered as checked (cookie and URL parameter), with the return path if it is
     * known.
     */
    private function refuse(SharedLoginSite $site, ?string $returnPath, ?string $failure): SharedLoginStep
    {
        $this->store->setCookie(
            name: SharedLogin::CHECKED_COOKIE_NAME,
            value: '1',
            maxAgeInSeconds: SharedLogin::CHECKED_LIFETIME_IN_SECONDS,
        );
        $loginUri = $returnPath === null
            ? $site->loginPath
            : LoginRedirect::createLoginUri(loginPath: $site->loginPath, returnUri: $returnPath);

        return new SharedLoginStep(
            redirectUrl: $loginUri . (str_contains(haystack: $loginUri, needle: '?') ? '&' : '?')
                . SharedLogin::CHECKED_PARAMETER . '=1',
            failure: $failure,
        );
    }

    /**
     * The absolute URL of a path of a validated site record.
     *
     * @param array<string, string|int|null> $record
     */
    private static function createSiteUrl(array $record, string $key): string
    {
        return 'https://' . LoginHandOverStore::readString(record: $record, key: 'host')
            . LoginHandOverStore::readString(record: $record, key: $key);
    }

    /**
     * @throws LogicException if the host of the request has a port or is no host name
     */
    private static function getSiteHost(HttpRequest $httpRequest): string
    {
        $host = strtolower(string: $httpRequest->getHost());
        if (!LoginHandOverStore::isHostName(host: $host)) {
            throw new LogicException(message: 'A shared login needs a host name without port.');
        }

        return $host;
    }
}
