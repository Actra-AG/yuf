<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\FormContext;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

final class FormContextTest extends TestCase
{
    public function testContextKeepsTheRequestAndTheTokenSource(): void
    {
        $httpRequest = HttpRequestFactory::create();
        $tokenSource = new InMemoryCsrfTokenSource();

        $context = new FormContext(httpRequest: $httpRequest, csrfTokenSource: $tokenSource);

        $this->assertSame($httpRequest, $context->httpRequest);
        $this->assertSame($tokenSource, $context->csrfTokenSource);
    }

    public function testFormKeepsItsContext(): void
    {
        $context = new FormContext(httpRequest: HttpRequestFactory::create(), csrfTokenSource: null);

        $form = new Form(context: $context, name: 'contact');

        $this->assertSame($context, $form->context);
    }

    public function testFormWithATokenSourceGetsTheCsrfFieldOfThePostForm(): void
    {
        $context = new FormContext(
            httpRequest: HttpRequestFactory::create(),
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'abc'),
        );

        $this->assertTrue(new Form(context: $context, name: 'post')->hasField(name: 'csrftoken'));
        $this->assertFalse(new Form(context: $context, name: 'get', methodPost: false)->hasField(name: 'csrftoken'));
    }

    public function testFormWithoutATokenSourceHasNoCsrfField(): void
    {
        $context = new FormContext(httpRequest: HttpRequestFactory::create(), csrfTokenSource: null);

        $this->assertFalse(new Form(context: $context, name: 'post')->hasField(name: 'csrftoken'));
    }
}
