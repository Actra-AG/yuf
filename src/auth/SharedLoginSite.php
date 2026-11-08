<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\core\LoginRedirect;
use InvalidArgumentException;

/**
 * The pages of a site that shares the login of a login host (`SharedLogin`): its own login page, its hand-over page and
 * the hand-over page of the login host.
 */
final readonly class SharedLoginSite
{
    /**
     * @param string $loginPath The local path of the login page of this site (`/login.html`, may have a query)
     * @param string $handOverPath The local path (without query) of the hand-over page of this site
     *     (`SharedLogin::receiveOnSite()`)
     * @param string $loginHostUrl The absolute `https` URL (without query) of the hand-over page of the login host
     *     (`SharedLogin::receiveOnLoginHost()`), e.g. `https://login.example.net/login-hand-over.html`
     *
     * @throws InvalidArgumentException if a path is no local path, the hand-over path has a query or the URL is no
     *     `https` URL without query, user info and port (`LoginRedirect::findHttpsHost()`)
     */
    public function __construct(
        public string $loginPath,
        public string $handOverPath,
        public string $loginHostUrl,
    ) {
        if (!LoginRedirect::isLocalPath(uri: $loginPath)) {
            throw new InvalidArgumentException(message: 'The login path must be a local path starting with "/".');
        }
        if (!LoginHandOverStore::isPathWithoutQuery(path: $handOverPath)) {
            throw new InvalidArgumentException(message: 'The hand-over path must be a local path without query.');
        }
        if (
            LoginRedirect::findHttpsHost(url: $loginHostUrl) === null
            || str_contains(haystack: $loginHostUrl, needle: '?')
        ) {
            throw new InvalidArgumentException(
                message: 'The URL of the login host must be an https URL with a path, without query, user info or'
                    . ' port.',
            );
        }
    }
}
