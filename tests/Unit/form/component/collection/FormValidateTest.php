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
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * `Form::validate(?FormInput)` and `Form::isSent(?FormInput)`: the request is a `FormInput`; without one the form reads
 * the superglobals through `FormInput::fromGlobals()` (restored after each test).
 */
final class FormValidateTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $savedGet;
    /** @var array<array-key, mixed> */
    private array $savedPost;
    /** @var array<array-key, mixed> */
    private array $savedFiles;

    #[Override]
    protected function setUp(): void
    {
        FormNameRegistry::reset();
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
        $this->savedFiles = $_FILES;
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
        $_FILES = $this->savedFiles;
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

    public function testIsSentReadsTheGlobalsWithoutInput(): void
    {
        $form = $this->createForm();
        $this->assertFalse($form->isSent());

        $_GET = ['contact' => ''];

        $this->assertTrue($form->isSent());
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

    public function testValidateReadsPostAndGetGlobalsWithoutInput(): void
    {
        $form = $this->createForm();
        $_GET = ['contact' => ''];
        $_POST = ['name' => 'Ann', 'csrftoken' => 'tok'];

        $this->assertTrue($form->validate());
    }

    public function testGetFormReadsTheValuesFromTheGetGlobal(): void
    {
        $form = $this->createForm(methodPost: false);
        $_GET = ['contact' => '', 'name' => 'Ann', 'csrftoken' => 'tok'];
        $_POST = ['name' => ''];

        $this->assertTrue($form->validate());
    }

    public function testPostFormIgnoresTheValuesOfTheGetGlobal(): void
    {
        $form = $this->createForm();
        $_GET = ['contact' => '', 'name' => 'Ann', 'csrftoken' => 'tok'];
        $_POST = ['csrftoken' => 'tok'];

        $this->assertFalse($form->validate());
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
