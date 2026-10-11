<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\common\AcceptingMailDomainResolver;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\FormContext;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

final class FormContextTest extends TestCase
{
    public function testContextKeepsTheRequestTheTokenSourceAndTheResolver(): void
    {
        $httpRequest = HttpRequestFactory::create();
        $tokenSource = new InMemoryCsrfTokenSource();
        $resolver = new AcceptingMailDomainResolver();

        $context = new FormContext(
            httpRequest: $httpRequest,
            csrfTokenSource: $tokenSource,
            mailDomainResolver: $resolver,
        );

        $this->assertSame($httpRequest, $context->httpRequest);
        $this->assertSame($tokenSource, $context->csrfTokenSource);
        $this->assertSame($resolver, $context->mailDomainResolver);
    }

    public function testFormKeepsItsContext(): void
    {
        $context = new FormContext(httpRequest: HttpRequestFactory::create(), csrfTokenSource: null, mailDomainResolver: new AcceptingMailDomainResolver());

        $form = new Form(context: $context, name: 'contact', csrfProtection: false);

        $this->assertSame($context, $form->context);
    }

    public function testFormWithATokenSourceGetsTheCsrfFieldOfThePostForm(): void
    {
        $context = new FormContext(
            httpRequest: HttpRequestFactory::create(),
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'abc'),
            mailDomainResolver: new AcceptingMailDomainResolver(),
        );

        $this->assertTrue(new Form(context: $context, name: 'post')->hasField(name: 'csrftoken'));
        $this->assertFalse(new Form(context: $context, name: 'get', methodPost: false)->hasField(name: 'csrftoken'));
    }

    public function testFormWithoutATokenSourceHasNoCsrfFieldWithTheOptOut(): void
    {
        $context = new FormContext(httpRequest: HttpRequestFactory::create(), csrfTokenSource: null, mailDomainResolver: new AcceptingMailDomainResolver());

        $form = new Form(context: $context, name: 'post', csrfProtection: false);

        $this->assertFalse($form->hasField(name: 'csrftoken'));
    }
}
