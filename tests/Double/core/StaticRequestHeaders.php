<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

/**
 * Request headers for the getallheaders() stand-in of the characterization tests. Static on purpose: the tests that
 * use it run in their own process (the static HttpRequest cannot receive the headers otherwise).
 */
final class StaticRequestHeaders
{
    /** @var array<mixed> */
    public static array $headers = [];
}
