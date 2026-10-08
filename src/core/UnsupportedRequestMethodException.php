<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use UnexpectedValueException;

/**
 * The request uses an HTTP method that `RequestMethodEnum` does not know. A client error: `Core` answers it with
 * 405 (Method Not Allowed).
 */
final class UnsupportedRequestMethodException extends UnexpectedValueException {}
