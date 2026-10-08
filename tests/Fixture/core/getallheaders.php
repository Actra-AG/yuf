<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use actra\yuf\tests\Double\core\StaticRequestHeaders;

/**
 * Stand-in for the Apache/FPM function that does not exist in the CLI. It is global, as the real one; only loaded in
 * separate processes, because a function cannot be removed again.
 *
 * @return array<string, string>
 */
function getallheaders(): array
{
    return StaticRequestHeaders::$headers;
}
