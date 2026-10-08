<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

/**
 * The request headers of the `getallheaders()` stand-in (`tests/Fixture/core/getallheaders.php`). Static on purpose: a
 * global function cannot receive them otherwise; the tests that use it run in their own process.
 */
final class StaticRequestHeaders
{
    /** @var array<string, string> */
    public static array $headers = [];
}
