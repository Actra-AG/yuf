<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\core\HttpRequest;
use actra\yuf\security\CsrfTokenSource;

/**
 * What a form needs from the request: the request (the input of the form) and the source of the CSRF token. A view
 * gets it as `ViewContext::$formContext`.
 *
 * Without a CSRF token source (no session, `Core` with `individualSessionHandler: false`), forms have no CSRF field
 * and do not check a token: CSRF needs a session cookie that the browser sends along on its own, without a session
 * there is nothing to abuse. The same applies to the table filter.
 */
final readonly class FormContext
{
    public function __construct(
        public HttpRequest $httpRequest,
        public ?CsrfTokenSource $csrfTokenSource,
    ) {}
}
