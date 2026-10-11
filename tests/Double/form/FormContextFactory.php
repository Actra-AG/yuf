<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\common\MailDomainResolver;
use actra\yuf\core\HttpRequest;
use actra\yuf\form\FormContext;
use actra\yuf\security\CsrfTokenSource;
use actra\yuf\tests\Double\common\FixedMailDomainResolver;
use actra\yuf\tests\Double\core\HttpRequestFactory;

/**
 * Builds the context of a form for a test: an `https://example.com/` request, unless the test passes a token
 * source no CSRF protection (as without session), and unless the test passes a resolver one that accepts every
 * domain without network access.
 */
final class FormContextFactory
{
    public static function create(
        ?HttpRequest $httpRequest = null,
        ?CsrfTokenSource $csrfTokenSource = null,
        ?MailDomainResolver $mailDomainResolver = null,
    ): FormContext {
        return new FormContext(
            httpRequest: $httpRequest ?? HttpRequestFactory::create(),
            csrfTokenSource: $csrfTokenSource,
            mailDomainResolver: $mailDomainResolver ?? new FixedMailDomainResolver(),
        );
    }
}
