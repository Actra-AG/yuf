<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\core;

use RuntimeException;

/**
 * Thrown by `RecordingResponseSender` instead of ending the process.
 */
final class ResponseSentException extends RuntimeException {}
