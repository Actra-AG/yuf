<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\collection;

use actra\yuf\clock\FixedClock;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\HiddenIntegerField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\spam\SpamCheck;
use actra\yuf\form\spam\SpamProtection;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `Form::addSpamProtection()`: the fields at the position of the call, the check in `validate()` and the messages.
 */
final class FormSpamProtectionTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';
    private const int RENDERED_AT = 1_800_000_000;

    private function createForm(
        int $now,
        FormMessages $messages = new FormMessages(),
        bool $withCsrf = false,
        ?HtmlText $globalErrorMessage = null,
    ): Form {
        $form = new Form(
            context: FormContextFactory::create(
                csrfTokenSource: $withCsrf ? new InMemoryCsrfTokenSource(token: 'expected-token') : null,
            ),
            name: 'contact',
            globalErrorMessage: $globalErrorMessage,
            messages: $messages,
            csrfProtection: $withCsrf,
        );
        $form->addField(
            formField: $emailField = new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: 'E-Mail'),
                value: null,
                invalidError: HtmlText::fromText(text: 'invalid'),
                mailDomainResolver: null,
                requiredError: HtmlText::fromText(text: 'required'),
            ),
        );
        $form->addSpamProtection(
            spamProtection: new SpamProtection(
                clock: FormSpamProtectionTest::clockAt(timestamp: $now),
                secret: FormSpamProtectionTest::SECRET,
                emailField: $emailField,
                rejectedEmailAddresses: ['info@example.com'],
            ),
        );
        $form->addComponent(formComponent: new FormControl(name: 'submit', submitLabel: HtmlText::fromText(text: 'Send')));

        return $form;
    }

    private static function clockAt(int $timestamp): FixedClock
    {
        return new FixedClock(now: new DateTimeImmutable(datetime: '@' . $timestamp));
    }

    private static function signedRenderTime(?string $sessionBinding = null): string
    {
        return new SpamCheck(
            clock: FormSpamProtectionTest::clockAt(timestamp: FormSpamProtectionTest::RENDERED_AT),
            secret: FormSpamProtectionTest::SECRET,
            rejectedEmailAddresses: [],
        )->signRenderTime(formName: 'contact', sessionBinding: $sessionBinding);
    }

    /**
     * @param array<string, string> $post
     */
    private function send(Form $form, array $post): bool
    {
        return $form->validate(input: FormInput::fromArray(data: $post, query: [$form->sentIndicator => '']));
    }

    /**
     * @return array<string, string>
     */
    private static function validPost(?string $sessionBinding = null): array
    {
        return [
            'email' => 'customer@example.com',
            'internal' => '',
            'timestamp' => FormSpamProtectionTest::signedRenderTime(sessionBinding: $sessionBinding),
            'csrftoken' => 'expected-token',
        ];
    }

    public function testValidSubmission(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10);

        $this->assertTrue($this->send(form: $form, post: FormSpamProtectionTest::validPost()));
    }

    public function testValidSubmissionWithCsrfToken(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10, withCsrf: true);

        $this->assertTrue(
            $this->send(form: $form, post: FormSpamProtectionTest::validPost(sessionBinding: 'expected-token')),
        );
    }

    public function testRenderTimeOfAnotherSessionIsRejected(): void
    {
        $form = $this->createForm(
            now: FormSpamProtectionTest::RENDERED_AT + 10,
            messages: FormMessages::german(),
            withCsrf: true,
        );

        $isValid = $this->send(form: $form, post: FormSpamProtectionTest::validPost(sessionBinding: 'other-token'));

        $this->assertFalse($isValid);
        $this->assertSame('Bitte versuchen Sie es erneut.', $form->errorCollection->getFirstError()->render());
    }

    public function testRenderedTimeIsBoundToTheSessionOfTheForm(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT, withCsrf: true);

        $html = $form->render();

        $signed = FormSpamProtectionTest::signedRenderTime(sessionBinding: 'expected-token');
        $this->assertStringContainsString('<input type="hidden" name="timestamp" value="' . $signed . '">', $html);
    }

    public function testFilledHoneypotIsAnErrorOfTheForm(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10, messages: FormMessages::german());

        $this->assertFalse($this->send(form: $form, post: ['internal' => 'spam'] + FormSpamProtectionTest::validPost()));
        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame(
            'Bitte lassen Sie das Feld mit der Bezeichnung &quot;Nur für interne Zwecke&quot; leer.',
            $form->errorCollection->getFirstError()->render(),
        );
    }

    public function testTooFastSubmission(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 2, messages: FormMessages::german());

        $this->assertFalse($this->send(form: $form, post: FormSpamProtectionTest::validPost()));
        $this->assertSame('Bitte versuchen Sie es erneut.', $form->errorCollection->getFirstError()->render());
    }

    public function testExpiredSubmission(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 3601);

        $this->assertFalse($this->send(form: $form, post: FormSpamProtectionTest::validPost()));
        $this->assertSame('Please try again.', $form->errorCollection->getFirstError()->render());
    }

    public function testMissingRenderTime(): void
    {
        $post = FormSpamProtectionTest::validPost();
        unset($post['timestamp']);
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10);

        $this->assertFalse($this->send(form: $form, post: $post));
        $this->assertSame('Please try again.', $form->errorCollection->getFirstError()->render());
    }

    public function testManipulatedRenderTimeAsListIsAVisibleErrorOfTheForm(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10);

        $isValid = $form->validate(
            input: FormInput::fromArray(
                data: ['timestamp' => ['1']] + FormSpamProtectionTest::validPost(),
                query: [$form->sentIndicator => ''],
            ),
        );

        $this->assertFalse($isValid);
        $this->assertSame('Please try again.', $form->errorCollection->getFirstError()->render());
    }

    public function testRejectedEmailAddress(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10, messages: FormMessages::german());

        $this->assertFalse($this->send(form: $form, post: ['email' => 'INFO@example.com'] + FormSpamProtectionTest::validPost()));
        $this->assertSame(
            'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            $form->errorCollection->getFirstError()->render(),
        );
    }

    public function testUnsentFormIsNotChecked(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT);

        $this->assertFalse($form->validate(input: FormInput::fromArray(data: [])));
        $this->assertFalse($form->hasErrors(withChildElements: true));
    }

    public function testSpamFieldsDoNotCountAsChanges(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10);
        $this->send(form: $form, post: ['email' => ''] + FormSpamProtectionTest::validPost());

        $this->assertFalse($form->hasChanges());
    }

    /**
     * @return array<string, array{array<string, string>, int}>
     */
    public static function provideSpamWithInvalidFields(): array
    {
        return [
            'filled honeypot' => [['internal' => 'spam'], FormSpamProtectionTest::RENDERED_AT + 10],
            'expired render time' => [[], FormSpamProtectionTest::RENDERED_AT + 3601],
            'too fast' => [[], FormSpamProtectionTest::RENDERED_AT + 1],
            'forged render time' => [['timestamp' => FormSpamProtectionTest::RENDERED_AT . '.' . str_repeat(string: '0', times: 64)], FormSpamProtectionTest::RENDERED_AT + 10],
        ];
    }

    /**
     * @param array<string, string> $post
     */
    #[DataProvider('provideSpamWithInvalidFields')]
    public function testInvalidFieldsShowOnlyTheFieldErrorsAndTheGlobalErrorMessage(array $post, int $now): void
    {
        $form = $this->createForm(
            now: $now,
            messages: FormMessages::german(),
            globalErrorMessage: HtmlText::fromText(text: 'Bitte prüfen Sie Ihre Eingaben.'),
        );

        $this->assertFalse($this->send(form: $form, post: $post + ['email' => 'invalid'] + FormSpamProtectionTest::validPost()));
        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame('Bitte prüfen Sie Ihre Eingaben.', $form->errorCollection->getFirstError()->render());
        $this->assertSame('invalid', $form->getField(name: 'email')->errorCollection->getFirstError()->render());
    }

    public function testForgedRenderTime(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 10);

        $this->assertFalse($this->send(form: $form, post: ['timestamp' => FormSpamProtectionTest::RENDERED_AT . '.' . str_repeat(string: '0', times: 64)] + FormSpamProtectionTest::validPost()));
        $this->assertSame('Please try again.', $form->errorCollection->getFirstError()->render());
    }

    public function testRenderingAfterFieldErrorsKeepsAValidPostedRenderTime(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT + 3600);
        $this->send(form: $form, post: ['email' => 'invalid'] + FormSpamProtectionTest::validPost());

        $this->assertStringContainsString('value="' . FormSpamProtectionTest::signedRenderTime() . '"', $form->render());
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function provideReplacedRenderTimes(): array
    {
        return [
            'expired' => [['timestamp' => FormSpamProtectionTest::signedRenderTime()]],
            'forged' => [['timestamp' => FormSpamProtectionTest::RENDERED_AT . '.' . str_repeat(string: '0', times: 64)]],
            'missing' => [['timestamp' => '']],
            'unsigned' => [['timestamp' => 'posted']],
        ];
    }

    /**
     * @param array<string, string> $post
     */
    #[DataProvider('provideReplacedRenderTimes')]
    public function testRenderingAfterFieldErrorsReplacesAnInvalidRenderTimeWithTheCurrentTime(array $post): void
    {
        $now = FormSpamProtectionTest::RENDERED_AT + 3601;
        $form = $this->createForm(now: $now);
        $this->send(form: $form, post: $post + ['email' => 'invalid'] + FormSpamProtectionTest::validPost());

        $html = $form->render();

        $currentTime = new SpamCheck(
            clock: FormSpamProtectionTest::clockAt(timestamp: $now),
            secret: FormSpamProtectionTest::SECRET,
            rejectedEmailAddresses: [],
        )->signRenderTime(formName: 'contact', sessionBinding: null);
        $this->assertStringContainsString('value="' . $currentTime . '"', $html);
        $this->assertStringNotContainsString('posted', $html);
    }

    public function testMarkupIsTheMarkupOfTheFieldsProjectsAddThemselves(): void
    {
        $protected = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT, messages: FormMessages::german());
        $own = new Form(
            context: FormContextFactory::create(),
            name: 'contact',
            messages: FormMessages::german(),
            csrfProtection: false,
        );
        $own->addField(
            formField: new EmailField(
                name: 'email',
                label: HtmlText::fromText(text: 'E-Mail'),
                value: null,
                invalidError: HtmlText::fromText(text: 'invalid'),
                mailDomainResolver: null,
                requiredError: HtmlText::fromText(text: 'required'),
            ),
        );
        $own->addField(
            formField: new TextField(
                name: 'internal',
                label: HtmlText::fromHtml(html: 'Nur für interne Zwecke'),
                autoComplete: AutoCompleteEnum::OFF,
            ),
        );
        $own->addField(formField: new HiddenIntegerField(name: 'timestamp', value: FormSpamProtectionTest::RENDERED_AT));
        $own->addComponent(formComponent: new FormControl(name: 'submit', submitLabel: HtmlText::fromText(text: 'Send')));

        $this->assertSame(
            str_replace(
                search: 'value="' . FormSpamProtectionTest::RENDERED_AT . '"',
                replace: 'value="' . FormSpamProtectionTest::signedRenderTime() . '"',
                subject: $own->render(),
            ),
            $protected->render(),
        );
    }

    public function testSecondSpamProtectionThrows(): void
    {
        $form = $this->createForm(now: FormSpamProtectionTest::RENDERED_AT);

        $this->expectException(LogicException::class);
        $form->addSpamProtection(
            spamProtection: new SpamProtection(
                clock: FormSpamProtectionTest::clockAt(timestamp: FormSpamProtectionTest::RENDERED_AT),
                secret: FormSpamProtectionTest::SECRET,
                emailField: null,
                rejectedEmailAddresses: [],
            ),
        );
    }

    public function testRejectedEmailAddressesNeedAnEmailField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpamProtection(
            clock: FormSpamProtectionTest::clockAt(timestamp: FormSpamProtectionTest::RENDERED_AT),
            secret: FormSpamProtectionTest::SECRET,
            emailField: null,
            rejectedEmailAddresses: ['info@example.com'],
        );
    }
}
