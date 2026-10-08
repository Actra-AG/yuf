<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use actra\yuf\tests\Double\core\StaticRequestHeaders;

/**
 * Stand-in for the Apache/FPM function that does not exist in the CLI. Resolved before the global function, because
 * HttpRequest calls it unqualified from this namespace. Only loaded in separate processes.
 *
 * @return array<mixed>
 */
function getallheaders(): array
{
    return StaticRequestHeaders::$headers;
}
