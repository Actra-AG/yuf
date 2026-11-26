<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * Decides whether the host of a request is served. `Core::prepareHttpResponse()` asks it for every request, also for
 * plain HTTP, bots and unknown routes, before it redirects to HTTPS or resolves the route.
 *
 * The built-in policy is `HostPatterns` (`allowedDomains` of the environment settings). An own policy, e.g. one that
 * looks up the domains of tenants in a database, asks the patterns first and queries only for the other hosts.
 */
interface HostPolicy
{
    /**
     * @param string $host As returned by `HttpRequest::getHost()`: as the client sent it, with the port, if any, and
     *                     possibly in upper case
     */
    public function isAllowed(string $host): bool;
}
