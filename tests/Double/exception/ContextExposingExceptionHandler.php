<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\exception;

use actra\yuf\exception\ExceptionHandler;
use actra\yuf\exception\ExceptionHandlerContext;

/**
 * An exception handler that exposes the protected context, as a project's own handler would read it.
 */
final class ContextExposingExceptionHandler extends ExceptionHandler
{
    public function context(): ExceptionHandlerContext
    {
        return $this->getContext();
    }
}
