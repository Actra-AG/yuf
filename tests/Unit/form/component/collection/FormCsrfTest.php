<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\FileField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\upload\UploadFileType;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use actra\yuf\tests\Double\form\RecordingFieldListener;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

/**
 * The CSRF protection of `Form::validate()`, with an in-memory token source (token `expected-token`) and the request
 * passed as `FormInput`.
 */
final class FormCsrfTest extends TestCase
{
    private const string ENGLISH_MESSAGE = 'The form could not be submitted because of a technical problem'
        . ' (invalid CSRF token). Please try again.';

    private static int $formCounter = 0;

    private function createForm(
        bool $methodPost = true,
        FormMessages $messages = new FormMessages(),
        ?HtmlText $globalErrorMessage = null,
        bool $csrfProtection = true,
        bool $acceptUpload = false,
    ): Form {
        return new Form(
            context: FormContextFactory::create(csrfTokenSource: new InMemoryCsrfTokenSource(token: 'expected-token')),
            name: 'csrfForm' . FormCsrfTest::$formCounter++,
            acceptUpload: $acceptUpload,
            globalErrorMessage: $globalErrorMessage,
            methodPost: $methodPost,
            messages: $messages,
            csrfProtection: $csrfProtection,
        );
    }

    /**
     * @param array<string, string> $post
     * @param array<string, string> $query
     */
    private function send(Form $form, array $post, array $query = []): bool
    {
        return $form->validate(
            input: FormInput::fromArray(data: $post, query: [$form->sentIndicator => ''] + $query),
        );
    }

    public function testFormIsNotValidatedIfItWasNotSent(): void
    {
        $form = $this->createForm();

        $this->assertFalse($form->validate(input: FormInput::fromArray(data: ['csrftoken' => 'wrong'])));
        $this->assertFalse($form->hasErrors(withChildElements: true));
    }

    public function testValidTokenIsAccepted(): void
    {
        $form = $this->createForm();

        $this->assertTrue($this->send(form: $form, post: ['csrftoken' => 'expected-token']));
        $this->assertFalse($form->hasErrors(withChildElements: true));
    }

    public function testWrongTokenAddsTheMessageToTheForm(): void
    {
        $form = $this->createForm();

        $isValid = $this->send(form: $form, post: ['csrftoken' => 'wrong']);

        $this->assertFalse($isValid);
        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testMissingTokenIsInvalid(): void
    {
        $form = $this->createForm();

        $this->assertFalse($this->send(form: $form, post: []));
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testTokenInTheQueryStringIsIgnored(): void
    {
        $form = $this->createForm();

        $isValid = $this->send(form: $form, post: [], query: ['csrftoken' => 'expected-token']);

        $this->assertFalse($isValid);
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testPostedTokenWinsOverTheQueryString(): void
    {
        $form = $this->createForm();

        $isValid = $this->send(
            form: $form,
            post: ['csrftoken' => 'wrong'],
            query: ['csrftoken' => 'expected-token'],
        );

        $this->assertFalse($isValid);
    }

    public function testFormWithGetMethodHasNoCsrfToken(): void
    {
        $form = $this->createForm(methodPost: false);
        $query = [$form->sentIndicator => ''];

        $this->assertFalse($form->hasChildComponent(childComponentName: 'csrftoken'));
        $this->assertTrue($form->validate(input: FormInput::fromArray(data: $query, query: $query)));
    }

    public function testInvalidTokenIsCheckedFirstAndNoOtherFieldIsRead(): void
    {
        $form = $this->createForm();
        $form->addField(
            formField: $name = new TextField(
                name: 'name',
                label: HtmlText::fromHtml(html: 'Name'),
                requiredError: HtmlText::fromHtml(html: 'Required'),
            ),
        );
        $listener = new RecordingFieldListener();
        $name->addListener(formFieldListener: $listener);

        $isValid = $this->send(form: $form, post: ['csrftoken' => 'wrong', 'name' => '']);

        $this->assertFalse($isValid);
        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
        $this->assertFalse($name->hasErrors(withChildElements: true));
        $this->assertSame([], $listener->calls);
    }

    public function testMissingTokenReadsNoUploadAndNoRemoveRequest(): void
    {
        $storage = new InMemoryFileUploadStorage();
        $form = $this->createForm(acceptUpload: true);
        $form->addField(
            formField: $file = new FileField(
                name: 'file',
                label: HtmlText::fromHtml(html: 'File'),
                storage: $storage,
                allowedFileTypes: [UploadFileType::plainText()],
            ),
        );
        $pointer = $file->uniqueSessFileStorePointer;

        $isValid = $this->send(form: $form, post: ['file_UID' => $pointer, 'file_removeAttachment' => 'abc']);

        $this->assertFalse($isValid);
        $this->assertSame([], $file->getRemovedValues());
        $this->assertSame(0, $storage->getExpiredRemovalCount());
        $this->assertSame([], $storage->getSavedFiles(pointer: $pointer));
    }

    public function testRemovingTheCsrfFieldDoesNotDisableTheCheck(): void
    {
        $form = $this->createForm();
        $form->removeField(name: 'csrftoken');

        $this->assertFalse($this->send(form: $form, post: []));
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testGlobalErrorMessageIsNotAddedForAnInvalidToken(): void
    {
        $form = $this->createForm(globalErrorMessage: HtmlText::fromHtml(html: 'Global'));

        $this->send(form: $form, post: ['csrftoken' => 'wrong']);

        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testGermanMessagesOfTheFormAreUsed(): void
    {
        $form = $this->createForm(messages: FormMessages::german());

        $this->send(form: $form, post: ['csrftoken' => 'wrong']);

        $this->assertSame(
            'Das Formular konnte wegen eines technischen Problems (ungültiges CSRF-Token) nicht übermittelt werden.'
            . ' Bitte versuchen Sie es erneut.',
            $form->errorCollection->getFirstError()->render(),
        );
    }

    public function testFormWithoutCsrfProtectionAcceptsAnyToken(): void
    {
        $form = $this->createForm(csrfProtection: false);

        $this->assertFalse($form->hasField(name: 'csrftoken'));
        $this->assertTrue($this->send(form: $form, post: []));
    }

    public function testFormRendersTheTokenOfTheSource(): void
    {
        $form = $this->createForm();

        $this->assertStringContainsString(
            '<input type="hidden" name="csrftoken" value="expected-token">',
            $form->render(),
        );
    }
}
