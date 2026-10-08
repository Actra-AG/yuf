<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\RequestMethodEnum;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

/**
 * `Form::validate(FormInput)` and `Form::isSent(FormInput)`: the request is a `FormInput`, built from a request or
 * from arrays.
 */
final class FormValidateTest extends TestCase
{
    private function createForm(
        bool $methodPost = true,
        ?string $individualSentIndicator = null,
        ?HttpRequest $httpRequest = null,
    ): Form {
        $form = new Form(
            context: FormContextFactory::create(
                httpRequest: $httpRequest,
                csrfTokenSource: new InMemoryCsrfTokenSource(token: 'tok'),
            ),
            name: 'contact',
            methodPost: $methodPost,
            individualSentIndicator: $individualSentIndicator,
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

    public function testTwoFormsWithTheSameNameAreAllowed(): void
    {
        $first = $this->createForm();
        $second = $this->createForm();

        $this->assertNotSame($first, $second);
        $this->assertSame($first->name, $second->name);
    }

    public function testInputDefaultsToThePostDataOfTheRequestOfTheContext(): void
    {
        $form = $this->createForm(
            httpRequest: HttpRequestFactory::create(
                method: RequestMethodEnum::POST,
                queryParameters: ['contact' => ''],
                postParameters: ['name' => 'Ann', 'csrftoken' => 'tok'],
            ),
        );

        $this->assertTrue($form->isSent());
        $this->assertTrue($form->validate());
        $this->assertSame('Ann', $form->getField(name: 'name')->renderValue());
    }

    public function testDefaultInputRejectsAWrongTokenOfTheRequest(): void
    {
        $form = $this->createForm(
            httpRequest: HttpRequestFactory::create(
                method: RequestMethodEnum::POST,
                queryParameters: ['contact' => ''],
                postParameters: ['name' => 'Ann', 'csrftoken' => 'wrong'],
            ),
        );

        $this->assertFalse($form->validate());
    }

    public function testInputOfAGetFormDefaultsToTheQueryStringOfTheRequest(): void
    {
        $form = $this->createForm(
            methodPost: false,
            httpRequest: HttpRequestFactory::create(
                queryParameters: ['contact' => '', 'name' => 'Ann'],
                postParameters: ['name' => 'Bob'],
            ),
        );

        $this->assertTrue($form->validate());
    }

    public function testFormIsNotSentWithoutTheSentIndicatorInTheRequest(): void
    {
        $form = $this->createForm(httpRequest: HttpRequestFactory::create(method: RequestMethodEnum::POST));

        $this->assertFalse($form->isSent());
        $this->assertFalse($form->validate());
    }

    public function testExplicitInputWinsOverTheRequestOfTheContext(): void
    {
        $form = $this->createForm(
            httpRequest: HttpRequestFactory::create(
                method: RequestMethodEnum::POST,
                queryParameters: ['contact' => ''],
            ),
        );

        $this->assertFalse($form->isSent(input: FormInput::fromArray(data: [])));
    }
}
