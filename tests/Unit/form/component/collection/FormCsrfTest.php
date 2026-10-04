<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormMessages;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

/**
 * The CSRF protection of `Form::validate()`, with an in-memory token source (token `expected-token`). `validate()`
 * still reads the superglobals, so they are restored after each test.
 */
final class FormCsrfTest extends TestCase
{
    private const string ENGLISH_MESSAGE = 'The form could not be submitted because of a technical problem'
    . ' (invalid CSRF token). Please try again.';

    private static int $formCounter = 0;

    /** @var array<array-key, mixed> */
    private array $savedGet;
    /** @var array<array-key, mixed> */
    private array $savedPost;
    /** @var array<array-key, mixed> */
    private array $savedFiles;

    protected function setUp(): void
    {
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
        $this->savedFiles = $_FILES;
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
        $_FILES = $this->savedFiles;
    }

    private function createForm(
        bool $methodPost = true,
        FormMessages $messages = new FormMessages(),
        ?HtmlText $globalErrorMessage = null
    ): Form {
        return new Form(
            name: 'csrfForm' . FormCsrfTest::$formCounter++,
            globalErrorMessage: $globalErrorMessage,
            methodPost: $methodPost,
            messages: $messages,
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'expected-token')
        );
    }

    /**
     * @param array<string, string> $post
     * @param array<string, string> $query
     */
    private function send(Form $form, array $post, array $query = []): bool
    {
        $_GET = [$form->sentIndicator => ''] + $query;
        $_POST = $post;

        return $form->validate();
    }

    public function testFormIsNotValidatedIfItWasNotSent(): void
    {
        $form = $this->createForm();
        $_GET = [];
        $_POST = ['csrftoken' => 'wrong'];

        $this->assertFalse($form->validate());
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

    public function testMissingPostedTokenFallsBackToTheQueryString(): void
    {
        $form = $this->createForm();

        $isValid = $this->send(form: $form, post: [], query: ['csrftoken' => 'expected-token']);

        $this->assertTrue($isValid);
    }

    public function testWrongTokenInTheQueryStringIsInvalid(): void
    {
        $form = $this->createForm();

        $this->assertFalse($this->send(form: $form, post: [], query: ['csrftoken' => 'wrong']));
    }

    public function testPostedTokenWinsOverTheQueryString(): void
    {
        $form = $this->createForm();

        $isValid = $this->send(
            form: $form,
            post: ['csrftoken' => 'wrong'],
            query: ['csrftoken' => 'expected-token']
        );

        $this->assertFalse($isValid);
    }

    public function testFormWithGetMethodReadsTheTokenFromTheQueryString(): void
    {
        $form = $this->createForm(methodPost: false);
        $_GET = [$form->sentIndicator => '', 'csrftoken' => 'expected-token'];
        $_POST = [];

        $this->assertTrue($form->validate());
    }

    public function testTokenIsOnlyCheckedIfTheOtherFieldsAreValid(): void
    {
        $form = $this->createForm();
        $form->addField(
            formField: new TextField(
                name: 'name',
                label: HtmlText::encoded(textContent: 'Name'),
                requiredError: HtmlText::encoded(textContent: 'Required')
            )
        );

        $isValid = $this->send(form: $form, post: ['csrftoken' => 'wrong', 'name' => '']);

        $this->assertFalse($isValid);
        $this->assertFalse($form->hasErrors(withChildElements: false));
        $this->assertTrue($form->getField(name: 'name')->hasErrors(withChildElements: false));
        $this->assertFalse($form->getField(name: 'csrftoken')->hasErrors(withChildElements: true));
    }

    public function testGlobalErrorMessageIsNotAddedForAnInvalidToken(): void
    {
        $form = $this->createForm(globalErrorMessage: HtmlText::encoded(textContent: 'Global'));

        $this->send(form: $form, post: ['csrftoken' => 'wrong']);

        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame(FormCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testGermanMessagesOfTheFormAreUsed(): void
    {
        $form = $this->createForm(messages: FormMessages::german());

        $this->send(form: $form, post: ['csrftoken' => 'wrong']);

        $this->assertSame(
            'Das Formular konnte wegen eines technischen Problems (ungültiges CSRF) nicht übermittelt werden.'
            . ' Bitte versuchen Sie es erneut.',
            $form->errorCollection->getFirstError()->render()
        );
    }

    public function testRemovedCsrfProtectionAcceptsAnyToken(): void
    {
        $form = $this->createForm();
        $form->removeCsrfProtection();

        $this->assertTrue($this->send(form: $form, post: []));
    }

    public function testFormRendersTheTokenOfTheSource(): void
    {
        $form = $this->createForm();

        $this->assertStringContainsString(
            '<input type="hidden" name="csrftoken" value="expected-token">',
            $form->render()
        );
    }
}