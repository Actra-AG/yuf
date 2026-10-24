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
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\spam\captcha\CaptchaFailModeEnum;
use actra\yuf\form\spam\captcha\CaptchaResult;
use actra\yuf\form\spam\FormCaptcha;
use actra\yuf\form\spam\SpamCheck;
use actra\yuf\form\spam\SpamProtection;
use actra\yuf\html\HtmlText;
use actra\yuf\security\CspNonce;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingLogger;
use actra\yuf\tests\Double\form\FormContextFactory;
use actra\yuf\tests\Double\form\RecordingCaptchaProvider;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * `Form::addSpamProtection()` with a `FormCaptcha`: the widget, the verification after all other spam rules and the
 * fail mode.
 */
final class FormCaptchaTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';
    private const int RENDERED_AT = 1_800_000_000;

    private RecordingCaptchaProvider $provider;
    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->provider = new RecordingCaptchaProvider(result: CaptchaResult::valid());
        $this->logger = new RecordingLogger();
    }

    private function createForm(
        CaptchaFailModeEnum $failMode = CaptchaFailModeEnum::CLOSED,
        FormMessages $messages = new FormMessages(),
        bool $withCaptcha = true,
        string $remoteAddress = '192.0.2.1',
    ): Form {
        $form = new Form(
            context: FormContextFactory::create(
                httpRequest: HttpRequestFactory::create(remoteAddress: $remoteAddress),
            ),
            name: 'contact',
            messages: $messages,
            csrfProtection: false,
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
                clock: FormCaptchaTest::clockAt(timestamp: FormCaptchaTest::RENDERED_AT + 10),
                secret: FormCaptchaTest::SECRET,
                rateLimit: null,
                captcha: $withCaptcha ? new FormCaptcha(
                    provider: $this->provider,
                    failMode: $failMode,
                    logger: $this->logger,
                    cspNonce: new CspNonce(value: 'nonce-1'),
                ) : null,
                emailField: $emailField,
                rejectedEmailAddresses: [],
            ),
        );

        return $form;
    }

    private static function clockAt(int $timestamp): FixedClock
    {
        return new FixedClock(now: new DateTimeImmutable(datetime: '@' . $timestamp));
    }

    /**
     * @param array<string, string> $post
     */
    private static function send(Form $form, array $post): bool
    {
        return $form->validate(input: FormInput::fromArray(data: $post, query: [$form->sentIndicator => '']));
    }

    /**
     * @return array<string, string>
     */
    private static function validPost(): array
    {
        return [
            'email' => 'customer@example.com',
            'internal' => '',
            'timestamp' => new SpamCheck(
                clock: FormCaptchaTest::clockAt(timestamp: FormCaptchaTest::RENDERED_AT),
                secret: FormCaptchaTest::SECRET,
                rejectedEmailAddresses: [],
            )->signRenderTime(formName: 'contact', sessionBinding: null),
            'captcha' => 'token-1',
        ];
    }

    public function testValidResponseIsVerifiedWithTheIpAddressOfTheVisitor(): void
    {
        $this->assertTrue(FormCaptchaTest::send(form: $this->createForm(), post: FormCaptchaTest::validPost()));
        $this->assertSame([['response' => 'token-1', 'remoteIp' => '192.0.2.1']], $this->provider->verifications);
    }

    public function testUnknownIpAddressIsPassedAsNull(): void
    {
        FormCaptchaTest::send(form: $this->createForm(remoteAddress: ''), post: FormCaptchaTest::validPost());

        $this->assertSame([['response' => 'token-1', 'remoteIp' => null]], $this->provider->verifications);
    }

    public function testInvalidResponseIsAnErrorOfTheForm(): void
    {
        $this->provider->result = CaptchaResult::invalid(reason: 'invalid-input-response');
        $form = $this->createForm(messages: FormMessages::german());

        $this->assertFalse(FormCaptchaTest::send(form: $form, post: FormCaptchaTest::validPost()));
        $this->assertSame(
            'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte bestätigen Sie erneut, dass Sie ein Mensch sind.',
            $form->errorCollection->getFirstError()->render(),
        );
        $this->assertSame([], $this->logger->loggedMessages);
    }

    public function testMissingResponseFailsWithoutRequestToTheProvider(): void
    {
        $post = FormCaptchaTest::validPost();
        unset($post['captcha']);
        $form = $this->createForm();

        $this->assertFalse(FormCaptchaTest::send(form: $form, post: $post));
        $this->assertSame(
            'The security check failed. Please confirm again that you are human.',
            $form->errorCollection->getFirstError()->render(),
        );
        $this->assertSame([], $this->provider->verifications);
    }

    public function testFilledHoneypotCostsNoRequestToTheProvider(): void
    {
        $form = $this->createForm();

        $this->assertFalse(
            FormCaptchaTest::send(form: $form, post: ['internal' => 'spam'] + FormCaptchaTest::validPost()),
        );
        $this->assertSame(1, $form->errorCollection->count());
        $this->assertSame([], $this->provider->verifications);
    }

    public function testTooFastSubmissionCostsNoRequestToTheProvider(): void
    {
        $post = ['timestamp' => new SpamCheck(
            clock: FormCaptchaTest::clockAt(timestamp: FormCaptchaTest::RENDERED_AT + 9),
            secret: FormCaptchaTest::SECRET,
            rejectedEmailAddresses: [],
        )->signRenderTime(formName: 'contact', sessionBinding: null)] + FormCaptchaTest::validPost();

        $this->assertFalse(FormCaptchaTest::send(form: $this->createForm(), post: $post));
        $this->assertSame([], $this->provider->verifications);
    }

    public function testFieldErrorsCostNoRequestToTheProvider(): void
    {
        $form = $this->createForm();

        $this->assertFalse(FormCaptchaTest::send(form: $form, post: ['email' => ''] + FormCaptchaTest::validPost()));
        $this->assertSame([], $this->provider->verifications);
    }

    public function testUnavailableProviderBlocksWithFailModeClosed(): void
    {
        $this->provider->result = CaptchaResult::unavailable(reason: 'timeout');
        $form = $this->createForm(failMode: CaptchaFailModeEnum::CLOSED);

        $this->assertFalse(FormCaptchaTest::send(form: $form, post: FormCaptchaTest::validPost()));
        $this->assertSame(
            'The security check is not available at the moment. Please try again later.',
            $form->errorCollection->getFirstError()->render(),
        );
        $this->assertSame(['The CAPTCHA could not be verified: timeout'], $this->logger->loggedMessages);
    }

    public function testUnavailableProviderAllowsWithFailModeOpen(): void
    {
        $this->provider->result = CaptchaResult::unavailable(reason: 'timeout');
        $form = $this->createForm(failMode: CaptchaFailModeEnum::OPEN);

        $this->assertTrue(FormCaptchaTest::send(form: $form, post: FormCaptchaTest::validPost()));
        $this->assertSame(['The CAPTCHA could not be verified: timeout'], $this->logger->loggedMessages);
    }

    public function testWidgetIsRenderedAfterTheOtherSpamFields(): void
    {
        $html = $this->createForm()->render();

        $this->assertMatchesRegularExpression(
            '~name="timestamp"[^>]*>\s*<div class="form-captcha"><div data-field="captcha" data-nonce="nonce-1">'
                . '</div></div>~',
            $html,
        );
        $this->assertStringNotContainsString('name="captcha"', $html);
    }

    public function testResponseIsNotRenderedAgain(): void
    {
        $this->provider->result = CaptchaResult::invalid(reason: 'invalid-input-response');
        $form = $this->createForm();
        FormCaptchaTest::send(form: $form, post: FormCaptchaTest::validPost());

        $this->assertStringNotContainsString('token-1', $form->render());
    }

    public function testFormWithoutCaptchaRendersNoWidgetAndVerifiesNothing(): void
    {
        $form = $this->createForm(withCaptcha: false);

        $this->assertTrue(FormCaptchaTest::send(form: $form, post: FormCaptchaTest::validPost()));
        $this->assertStringNotContainsString('form-captcha', $form->render());
        $this->assertSame([], $this->provider->verifications);
    }

    public function testFieldsOfTheSpamProtectionNeedDifferentNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpamProtection(
            clock: FormCaptchaTest::clockAt(timestamp: FormCaptchaTest::RENDERED_AT),
            secret: FormCaptchaTest::SECRET,
            rateLimit: null,
            captcha: new FormCaptcha(
                provider: $this->provider,
                failMode: CaptchaFailModeEnum::CLOSED,
                logger: $this->logger,
                cspNonce: null,
                fieldName: 'internal',
            ),
            emailField: null,
            rejectedEmailAddresses: [],
        );
    }
}
