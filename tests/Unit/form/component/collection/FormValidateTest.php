<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * `Form::validate(FormInput)` and `Form::isSent(FormInput)`: the request is a `FormInput`, built from a request or
 * from arrays.
 */
final class FormValidateTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        FormNameRegistry::reset();
    }

    private function createForm(bool $methodPost = true, ?string $individualSentIndicator = null): Form
    {
        $form = new Form(
            name: 'contact',
            methodPost: $methodPost,
            individualSentIndicator: $individualSentIndicator,
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'tok'),
        );
        $form->addField(
            formField: new TextField(
                name: 'name',
                label: HtmlText::fromHtml(html: 'Name'),
                requiredError: HtmlText::fromHtml(html: 'Required'),
            ),
        );

        return $form;
    }

    public function testIsSentAsksTheQueryPartOfTheInput(): void
    {
        $form = $this->createForm();

        $this->assertTrue($form->isSent(input: FormInput::fromArray(data: [], query: ['contact' => ''])));
        $this->assertFalse($form->isSent(input: FormInput::fromArray(data: ['contact' => ''])));
    }

    public function testIsSentUsesTheIndividualSentIndicator(): void
    {
        $form = $this->createForm(individualSentIndicator: 'sent');

        $this->assertTrue($form->isSent(input: FormInput::fromArray(data: [], query: ['sent' => ''])));
        $this->assertFalse($form->isSent(input: FormInput::fromArray(data: [], query: ['contact' => ''])));
    }

    public function testIsSentReadsTheQueryStringOfTheRequest(): void
    {
        $form = $this->createForm();
        $notSent = HttpRequestFactory::create();
        $sent = HttpRequestFactory::create(queryParameters: ['contact' => '']);

        $this->assertFalse($form->isSent(input: FormInput::fromHttpRequest(httpRequest: $notSent, methodPost: true)));
        $this->assertTrue($form->isSent(input: FormInput::fromHttpRequest(httpRequest: $sent, methodPost: true)));
    }

    public function testValidateReturnsFalseWithoutErrorsIfTheFormWasNotSent(): void
    {
        $form = $this->createForm();

        $isValid = $form->validate(input: FormInput::fromArray(data: ['name' => '', 'csrftoken' => 'tok']));

        $this->assertFalse($isValid);
        $this->assertFalse($form->hasErrors(withChildElements: true));
    }

    public function testValidateReadsTheValuesOfTheInput(): void
    {
        $form = $this->createForm();

        $isValid = $form->validate(
            input: FormInput::fromArray(data: ['name' => 'Ann', 'csrftoken' => 'tok'], query: ['contact' => '']),
        );

        $this->assertTrue($isValid);
        $field = $form->getField(name: 'name');
        $this->assertInstanceOf(TextField::class, $field);
        $this->assertSame('Ann', $field->getValueAsString());
    }

    public function testValidateAddsTheErrorsOfTheFields(): void
    {
        $form = $this->createForm();

        $isValid = $form->validate(
            input: FormInput::fromArray(data: ['name' => ' ', 'csrftoken' => 'tok'], query: ['contact' => '']),
        );

        $this->assertFalse($isValid);
        $this->assertTrue($form->getField(name: 'name')->hasErrors(withChildElements: false));
    }

    public function testValidateReadsPostAndQueryOfTheRequest(): void
    {
        $form = $this->createForm();
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['contact' => ''],
            postParameters: ['name' => 'Ann', 'csrftoken' => 'tok'],
        );

        $input = FormInput::fromHttpRequest(httpRequest: $httpRequest, methodPost: true);

        $this->assertTrue($form->validate(input: $input));
    }

    public function testGetFormReadsTheValuesFromTheQueryOfTheRequest(): void
    {
        $form = $this->createForm(methodPost: false);
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['contact' => '', 'name' => 'Ann', 'csrftoken' => 'tok'],
            postParameters: ['name' => ''],
        );

        $input = FormInput::fromHttpRequest(httpRequest: $httpRequest, methodPost: false);

        $this->assertTrue($form->validate(input: $input));
    }

    public function testPostFormIgnoresTheValuesOfTheQueryOfTheRequest(): void
    {
        $form = $this->createForm();
        $httpRequest = HttpRequestFactory::create(
            queryParameters: ['contact' => '', 'name' => 'Ann', 'csrftoken' => 'tok'],
            postParameters: ['csrftoken' => 'tok'],
        );

        $input = FormInput::fromHttpRequest(httpRequest: $httpRequest, methodPost: true);

        $this->assertFalse($form->validate(input: $input));
    }

    public function testDuplicateFormNameThrows(): void
    {
        $this->createForm();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('A Form with the name "contact" has already been defined.');

        $this->createForm();
    }

    public function testFormNameCanBeUsedAgainAfterTheRegistryWasReset(): void
    {
        $this->createForm();
        FormNameRegistry::reset();

        $this->assertSame('contact', $this->createForm()->name);
    }
}
