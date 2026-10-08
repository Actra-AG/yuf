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
use actra\yuf\session\AbstractSessionHandler;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): a `Form` with its
 * default CSRF token source (the token of the session), as opposed to `FormCsrfTest` (in-memory source). The
 * behaviour tests use `seedToken()` and `storedToken()` only; the session key is pinned in `SessionCsrfTokenSourceTest`
 * and `CsrfTokenTest`.
 */
final class FormSessionCsrfTest extends TestCase
{
    private const string ENGLISH_MESSAGE = 'The form could not be submitted because of a technical problem'
        . ' (invalid CSRF token). Please try again.';

    private static int $formCounter = 0;

    #[Override]
    protected function setUp(): void
    {
        FormNameRegistry::reset();
        $_SESSION = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        FormNameRegistry::reset();
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    private function seedToken(string $token): void
    {
        $_SESSION['csrftoken'] = $token;
    }

    private function storedToken(): ?string
    {
        $token = $_SESSION['csrftoken'] ?? null;

        return is_string(value: $token) ? $token : null;
    }

    private function createForm(bool $methodPost = true): Form
    {
        return new Form(name: 'sessionForm' . FormSessionCsrfTest::$formCounter++, methodPost: $methodPost);
    }

    /**
     * @param array<string, string> $post
     */
    private function send(Form $form, array $post): bool
    {
        return $form->validate(input: FormInput::fromArray(data: $post, query: [$form->sentIndicator => '']));
    }

    public function testPostedTokenOfTheSessionIsAccepted(): void
    {
        $this->seedToken(token: 'session-token');
        $form = $this->createForm();

        $this->assertTrue($this->send(form: $form, post: ['csrftoken' => 'session-token']));
        $this->assertFalse($form->hasErrors(withChildElements: true));
    }

    public function testWrongMissingAndEmptyTokensAreRejected(): void
    {
        $this->seedToken(token: 'session-token');

        foreach ([['csrftoken' => 'wrong'], ['csrftoken' => ''], []] as $post) {
            $form = $this->createForm();

            $this->assertFalse($this->send(form: $form, post: $post));
            $this->assertSame(FormSessionCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
        }
    }

    public function testNoTokenInTheSessionRejectsEveryPostedTokenAndCreatesOne(): void
    {
        $form = $this->createForm();

        $this->assertFalse($this->send(form: $form, post: ['csrftoken' => 'anything']));

        $this->assertSame(FormSessionCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
        $this->assertNotNull($this->storedToken());
    }

    public function testFormRendersTheTokenOfTheSession(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertStringContainsString(
            '<input type="hidden" name="csrftoken" value="session-token">',
            $this->createForm()->render(),
        );
    }

    public function testRenderingCreatesTheTokenAndThePostedTokenIsAcceptedInTheNextRequest(): void
    {
        $this->createForm()->render();
        $token = $this->storedToken();
        $this->assertNotNull($token);
        FormNameRegistry::reset();

        $this->assertTrue($this->send(form: $this->createForm(), post: ['csrftoken' => $token]));
    }

    public function testTokenIsNotRenewedBySuccessfulValidationAndIsSharedByAllForms(): void
    {
        $this->seedToken(token: 'session-token');

        $this->assertTrue($this->send(form: $this->createForm(), post: ['csrftoken' => 'session-token']));
        $this->assertTrue($this->send(form: $this->createForm(), post: ['csrftoken' => 'session-token']));

        $this->assertSame('session-token', $this->storedToken());
    }

    public function testCreatingAFormDoesNotTouchTheSession(): void
    {
        $this->createForm();

        $this->assertSame([], $_SESSION);
    }

    public function testTokenIsNotCheckedWhileAnotherFieldIsInvalid(): void
    {
        $form = $this->createForm();
        $form->addField(
            formField: new TextField(
                name: 'name',
                label: HtmlText::fromHtml(html: 'Name'),
                requiredError: HtmlText::fromHtml(html: 'Required'),
            ),
        );

        $this->assertFalse($this->send(form: $form, post: ['csrftoken' => 'wrong', 'name' => '']));

        $this->assertSame([], $_SESSION);
    }

    public function testFormWithGetMethodNeverTouchesTheSession(): void
    {
        $form = $this->createForm(methodPost: false);
        $query = [$form->sentIndicator => ''];

        $this->assertTrue($form->validate(input: FormInput::fromArray(data: $query, query: $query)));
        $form->render();

        $this->assertSame([], $_SESSION);
    }

    public function testRemovedCsrfProtectionNeverTouchesTheSession(): void
    {
        $form = $this->createForm();
        $form->removeCsrfProtection();

        $this->assertTrue($this->send(form: $form, post: []));

        $this->assertSame([], $_SESSION);
    }

    /**
     * Without session a token cannot survive to the next request, so every posted token is rejected: a form with
     * CSRF protection can never be submitted successfully (`removeCsrfProtection()` is the way out).
     */
    public function testWithoutSessionEveryPostedTokenIsRejected(): void
    {
        unset($_SESSION);
        $form = $this->createForm();

        $isValid = $this->send(form: $form, post: ['csrftoken' => 'anything']);

        $this->assertFalse($isValid);
        $this->assertSame(FormSessionCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
    }

    public function testWithoutSessionAFormWithoutCsrfProtectionCanBeSubmitted(): void
    {
        unset($_SESSION);
        $form = $this->createForm();
        $form->removeCsrfProtection();

        $this->assertTrue($this->send(form: $form, post: []));
        $this->assertFalse(AbstractSessionHandler::enabled());
    }

    /**
     * Unlike `CsrfToken::renderAsHiddenPostField()` (empty without session), the field of the form renders a token
     * that is never accepted.
     */
    public function testWithoutSessionTheFormStillRendersAHiddenTokenField(): void
    {
        unset($_SESSION);

        $html = $this->createForm()->render();

        $this->assertMatchesRegularExpression(
            '~<input type="hidden" name="csrftoken" value="[A-Za-z0-9+/=]{44}">~',
            $html,
        );
    }
}
