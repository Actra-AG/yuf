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
use actra\yuf\html\HtmlText;
use actra\yuf\security\SessionCsrfTokenSource;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\form\FormContextFactory;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * A `Form` with the CSRF token of the session (`SessionCsrfTokenSource` on an `ArraySessionStorage`), as opposed to
 * `FormCsrfTest` (in-memory source), and a `Form` without token source (no session): no CSRF field, no token check.
 * The behaviour tests use `seedToken()` and `storedToken()` only; the layout is pinned in
 * `SessionCsrfTokenSourceTest`.
 */
final class FormSessionCsrfTest extends TestCase
{
    private const string ENGLISH_MESSAGE = 'The form could not be submitted because of a technical problem'
        . ' (invalid CSRF token). Please try again.';

    private ArraySessionStorage $storage;
    private SessionCsrfTokenSource $tokenSource;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new ArraySessionStorage();
        $this->tokenSource = new SessionCsrfTokenSource(session: new Session(storage: $this->storage));
    }

    private function seedToken(string $token): void
    {
        $this->storage->set(key: 'yuf', value: ['csrf' => ['token' => $token]]);
    }

    private function storedToken(): ?string
    {
        $yuf = $this->storage->get(key: 'yuf');
        $csrf = is_array(value: $yuf) ? ($yuf['csrf'] ?? null) : null;
        $token = is_array(value: $csrf) ? ($csrf['token'] ?? null) : null;

        return is_string(value: $token) ? $token : null;
    }

    private function createForm(bool $methodPost = true, bool $withSession = true): Form
    {
        return new Form(
            context: FormContextFactory::create(csrfTokenSource: $withSession ? $this->tokenSource : null),
            name: 'sessionForm',
            methodPost: $methodPost,
        );
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

    /**
     * Fail closed: checking a posted token does not create a token (it is created when a form is rendered).
     */
    public function testNoTokenInTheSessionRejectsEveryPostedTokenAndCreatesNone(): void
    {
        $form = $this->createForm();

        $this->assertFalse($this->send(form: $form, post: ['csrftoken' => 'anything']));

        $this->assertSame(FormSessionCsrfTest::ENGLISH_MESSAGE, $form->errorCollection->getFirstError()->render());
        $this->assertNull($this->storedToken());
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

        $this->assertSame([], $this->storage->all());
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

        $this->assertSame([], $this->storage->all());
    }

    public function testFormWithGetMethodNeverTouchesTheSession(): void
    {
        $form = $this->createForm(methodPost: false);
        $query = [$form->sentIndicator => ''];

        $this->assertTrue($form->validate(input: FormInput::fromArray(data: $query, query: $query)));
        $form->render();

        $this->assertSame([], $this->storage->all());
    }

    public function testRemovedCsrfProtectionNeverTouchesTheSession(): void
    {
        $form = $this->createForm();
        $form->removeCsrfProtection();

        $this->assertTrue($this->send(form: $form, post: []));

        $this->assertSame([], $this->storage->all());
    }

    /**
     * Decision of v4.30.0: without session (no token source in the `FormContext`) the form has no CSRF field and
     * checks no token. Before, it rendered a token that was never accepted.
     */
    public function testWithoutTokenSourceTheFormHasNoCsrfFieldAndChecksNoToken(): void
    {
        $form = $this->createForm(withSession: false);

        $this->assertFalse($form->hasField(name: 'csrftoken'));
        $this->assertTrue($this->send(form: $form, post: []));
        $this->assertFalse($form->hasErrors(withChildElements: true));
    }

    public function testWithoutTokenSourceTheFormRendersNoTokenField(): void
    {
        $html = $this->createForm(withSession: false)->render();

        $this->assertStringNotContainsString('csrftoken', $html);
    }

    public function testWithoutTokenSourceAPostedTokenIsIgnored(): void
    {
        $form = $this->createForm(withSession: false);

        $this->assertTrue($this->send(form: $form, post: ['csrftoken' => 'anything']));
    }

    public function testWithoutTokenSourceRemovingTheCsrfProtectionIsHarmless(): void
    {
        $form = $this->createForm(withSession: false);

        $form->removeCsrfProtection();

        $this->assertTrue($this->send(form: $form, post: []));
    }
}
