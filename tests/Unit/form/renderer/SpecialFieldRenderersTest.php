<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\component\field\IbanNumberField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\ZipCodeField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\security\InMemoryCsrfTokenSource;
use PHPUnit\Framework\TestCase;

/**
 * The HTML of the phone number, zip code, IBAN, hidden, password and CSRF fields. The expected strings were rendered
 * by yuf v3.3.1 for the equivalent state, except for the documented changes (UPGRADE.md): posted zip codes and IBANs
 * are trimmed, and the invalid phone number is HTML-encoded (v3.3.1 rendered it unencoded into the attribute).
 * The request is passed to `Form::validate()` as `FormInput`.
 */
final class SpecialFieldRenderersTest extends TestCase
{
    private static int $formCounter = 0;

    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    private function text(string $text): HtmlText
    {
        return HtmlText::encoded(textContent: $text);
    }

    private function phone(?string $value = null, bool $internalFormat = false): PhoneNumberField
    {
        return new PhoneNumberField(
            name: 'phone',
            label: $this->text('Phone'),
            value: $value,
            invalidErrorMessage: $this->text('Invalid'),
            requiredErrorMessage: $this->text('Req'),
            renderInternalFormat: $internalFormat
        );
    }

    private function zip(?string $value = null): ZipCodeField
    {
        return new ZipCodeField(
            name: 'zip',
            label: $this->text('Zip'),
            value: $value,
            requiredError: $this->text('Req'),
            maxLength: 10
        );
    }

    private function iban(?string $value = null): IbanNumberField
    {
        return new IbanNumberField(
            name: 'iban',
            label: $this->text('Iban'),
            value: $value,
            invalidError: $this->text('Invalid'),
            requiredError: $this->text('Req')
        );
    }

    private function createForm(FormMessages $messages = new FormMessages()): Form
    {
        return new Form(
            name: 'specialFieldsForm' . SpecialFieldRenderersTest::$formCounter++,
            messages: $messages,
            csrfTokenSource: new InMemoryCsrfTokenSource(token: 'tok+en/1=')
        );
    }

    public function testEmptyPhoneNumberField(): void
    {
        $this->assertSame('<input type="tel" name="phone" id="phone" value="">', $this->phone()->render());
    }

    public function testPhoneNumberFieldWithValueIsRenderedInInternationalFormat(): void
    {
        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="+41 44 668 18 00">',
            $this->phone(value: '044 668 18 00')->render()
        );
    }

    public function testPhoneNumberFieldWithValueIsRenderedInInternalFormat(): void
    {
        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="+41.446681800">',
            $this->phone(value: '044 668 18 00', internalFormat: true)->render()
        );
    }

    public function testPhoneNumberFieldWithInvalidValueIsEncoded(): void
    {
        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="a&quot;b&lt;c">',
            $this->phone(value: 'a"b<c')->render()
        );
    }

    public function testPostedPhoneNumberIsRenderedInInternationalFormat(): void
    {
        $field = $this->phone();
        $field->validate(input: FormInput::fromArray(data: ['phone' => ' 044 668 18 00 ']));

        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="+41 44 668 18 00">',
            $field->render()
        );
    }

    public function testPostedPhoneNumberIsRenderedInInternalFormat(): void
    {
        $field = $this->phone(internalFormat: true);
        $field->validate(input: FormInput::fromArray(data: ['phone' => '044 668 18 00']));

        $this->assertSame('<input type="tel" name="phone" id="phone" value="+41.446681800">', $field->render());
    }

    public function testPostedPhoneNumberWithPostedCountryCode(): void
    {
        $field = $this->phone();
        $field->validate(input: FormInput::fromArray(data: ['phone' => '030 123456', 'countryCode' => 'DE']));

        $this->assertSame('<input type="tel" name="phone" id="phone" value="+49 30 123456">', $field->render());
    }

    public function testInvalidPostedPhoneNumberIsRenderedAsPosted(): void
    {
        $field = $this->phone();
        $field->validate(input: FormInput::fromArray(data: ['phone' => 'abc']));

        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="abc" aria-invalid="true" aria-describedby="phone-error">',
            $field->render()
        );
    }

    public function testInvalidPostedPhoneNumberCannotBreakOutOfTheAttribute(): void
    {
        $field = $this->phone();
        $field->validate(input: FormInput::fromArray(data: ['phone' => '"><b>x']));

        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="&quot;&gt;&lt;b&gt;x" aria-invalid="true"'
            . ' aria-describedby="phone-error">',
            $field->render()
        );
    }

    public function testRequiredPhoneNumberFieldWithoutValue(): void
    {
        $field = $this->phone();
        $field->validate(input: FormInput::fromArray(data: ['phone' => '']));

        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="" aria-invalid="true" aria-describedby="phone-error">',
            $field->render()
        );
    }

    public function testPhoneNumberFieldWithPlaceholderAndAutoComplete(): void
    {
        $field = new PhoneNumberField(
            name: 'phone',
            label: $this->text('Phone'),
            value: null,
            invalidErrorMessage: $this->text('Invalid'),
            placeholder: 'p',
            autoComplete: AutoCompleteValue::TEL
        );

        $this->assertSame(
            '<input type="tel" name="phone" id="phone" value="" placeholder="p" autocomplete="tel">',
            $field->render()
        );
    }

    public function testEmptyZipCodeField(): void
    {
        $this->assertSame(
            '<input type="text" name="zip" id="zip" value="" maxlength="10">',
            $this->zip()->render()
        );
    }

    public function testZipCodeFieldWithValue(): void
    {
        $this->assertSame(
            '<input type="text" name="zip" id="zip" value="8000" maxlength="10">',
            $this->zip(value: '8000')->render()
        );
    }

    public function testPostedZipCodeIsRenderedTrimmed(): void
    {
        $field = $this->zip();
        $field->validate(input: FormInput::fromArray(data: ['zip' => ' 8000 ']));

        $this->assertSame('<input type="text" name="zip" id="zip" value="8000" maxlength="10">', $field->render());
    }

    public function testInvalidZipCodeIsRenderedEncodedWithErrorAttributes(): void
    {
        $field = $this->zip();
        $field->validate(input: FormInput::fromArray(data: ['zip' => 'x"y']));

        $this->assertSame(
            '<input type="text" name="zip" id="zip" value="x&quot;y" maxlength="10" aria-invalid="true"'
            . ' aria-describedby="zip-error">',
            $field->render()
        );
    }

    public function testRequiredZipCodeFieldWithoutValue(): void
    {
        $field = $this->zip();
        $field->validate(input: FormInput::fromArray(data: []));

        $this->assertSame(
            '<input type="text" name="zip" id="zip" value="" maxlength="10" aria-invalid="true"'
            . ' aria-describedby="zip-error">',
            $field->render()
        );
    }

    public function testEmptyIbanField(): void
    {
        $this->assertSame('<input type="text" name="iban" id="iban" value="">', $this->iban()->render());
    }

    public function testIbanFieldWithValueKeepsSpaces(): void
    {
        $this->assertSame(
            '<input type="text" name="iban" id="iban" value="CH93 0076 2011 6238 5295 7">',
            $this->iban(value: 'CH93 0076 2011 6238 5295 7')->render()
        );
    }

    public function testPostedIbanIsRenderedTrimmedWithItsCase(): void
    {
        $field = $this->iban();
        $field->validate(input: FormInput::fromArray(data: ['iban' => ' ch9300762011623852957 ']));

        $this->assertSame(
            '<input type="text" name="iban" id="iban" value="ch9300762011623852957">',
            $field->render()
        );
    }

    public function testInvalidIbanIsRenderedEncodedWithErrorAttributes(): void
    {
        $field = $this->iban();
        $field->validate(input: FormInput::fromArray(data: ['iban' => 'xx<']));

        $this->assertSame(
            '<input type="text" name="iban" id="iban" value="xx&lt;" aria-invalid="true"'
            . ' aria-describedby="iban-error">',
            $field->render()
        );
    }

    public function testHiddenFieldIsRenderedEncoded(): void
    {
        $this->assertSame(
            '<input type="hidden" name="h" value="a&quot;b">',
            new HiddenField(name: 'h', value: 'a"b')->render()
        );
    }

    public function testPostedPasswordIsNeverRenderedBack(): void
    {
        $field = new PasswordField(
            name: 'pw',
            label: $this->text('Pw'),
            requiredError: $this->text('Req'),
            purpose: PasswordPurposeEnum::CURRENT
        );
        $field->validate(input: FormInput::fromArray(data: ['pw' => 'secret"x']));

        $this->assertSame(
            '<input type="password" name="pw" id="pw" value="" autocomplete="current-password">',
            $field->render()
        );
    }

    public function testPasswordFieldWithPlaceholderAndMaxLength(): void
    {
        $field = new PasswordField(
            name: 'pw',
            label: $this->text('Pw'),
            requiredError: $this->text('Req'),
            purpose: PasswordPurposeEnum::NEW,
            placeholder: 'ph',
            maxLength: 50
        );

        $this->assertSame(
            '<input type="password" name="pw" id="pw" value="" placeholder="ph" autocomplete="new-password"'
            . ' maxlength="50">',
            $field->render()
        );
    }

    public function testFormWithTheSpecialFields(): void
    {
        $form = $this->createForm();
        $formName = $form->name;
        $form->addField(formField: $this->phone());
        $form->addField(formField: $this->zip());
        $form->addField(formField: $this->iban());

        $this->assertSame(
            '<form method="post" action="?' . $formName . '"><input type="hidden" name="csrftoken" value="tok+en/1=">'
            . '<dl><dt><label for="phone">Phone<span class="required">*</span></label></dt><dd>'
            . '<input type="tel" name="phone" id="phone" value=""></dd></dl>'
            . '<dl><dt><label for="zip">Zip<span class="required">*</span></label></dt><dd>'
            . '<input type="text" name="zip" id="zip" value="" maxlength="10"></dd></dl>'
            . '<dl><dt><label for="iban">Iban<span class="required">*</span></label></dt><dd>'
            . '<input type="text" name="iban" id="iban" value=""></dd></dl></form>',
            $form->render()
        );
    }

    public function testFormWithInvalidSpecialFieldsShowsTheErrors(): void
    {
        $form = $this->createForm(messages: FormMessages::german());
        $formName = $form->name;
        $form->addField(formField: $this->phone());
        $form->addField(formField: $this->zip());
        $form->addField(formField: $this->iban());

        $isValid = $form->validate(
            input: FormInput::fromArray(
                data: ['phone' => 'abc', 'zip' => 'x', 'iban' => 'x', 'csrftoken' => 'tok+en/1='],
                query: [$formName => '']
            )
        );

        $this->assertFalse($isValid);
        $this->assertSame(
            '<form method="post" action="?' . $formName . '"><input type="hidden" name="csrftoken" value="tok+en/1=">'
            . '<dl><dt><label for="phone">Phone<span class="required">*</span></label></dt><dd class="has-error">'
            . '<input type="tel" name="phone" id="phone" value="abc" aria-invalid="true"'
            . ' aria-describedby="phone-error"><div class="form-input-error" id="phone-error" role="alert"'
            . ' aria-live="assertive">Invalid</div></dd></dl>'
            . '<dl><dt><label for="zip">Zip<span class="required">*</span></label></dt><dd class="has-error">'
            . '<input type="text" name="zip" id="zip" value="x" maxlength="10" aria-invalid="true"'
            . ' aria-describedby="zip-error"><div class="form-input-error" id="zip-error" role="alert"'
            . ' aria-live="assertive">Die eingegebene PLZ ist ungültig.</div></dd></dl>'
            . '<dl><dt><label for="iban">Iban<span class="required">*</span></label></dt><dd class="has-error">'
            . '<input type="text" name="iban" id="iban" value="x" aria-invalid="true"'
            . ' aria-describedby="iban-error"><div class="form-input-error" id="iban-error" role="alert"'
            . ' aria-live="assertive">Invalid</div></dd></dl></form>',
            $form->render()
        );
    }

    public function testFormWithAnInvalidCsrfTokenShowsTheFormError(): void
    {
        $form = $this->createForm(messages: FormMessages::german());
        $formName = $form->name;
        $form->addField(formField: $this->phone());

        $isValid = $form->validate(
            input: FormInput::fromArray(
                data: ['phone' => '044 668 18 00', 'csrftoken' => 'wrong'],
                query: [$formName => '']
            )
        );

        $this->assertFalse($isValid);
        $this->assertSame(
            '<form method="post" action="?' . $formName . '"><p class="form-error" role="alert"'
            . ' aria-live="assertive"><strong>Das Formular konnte wegen eines technischen Problems (ungültiges CSRF)'
            . ' nicht übermittelt werden. Bitte versuchen Sie es erneut.</strong></p>'
            . '<input type="hidden" name="csrftoken" value="tok+en/1=">'
            . '<dl><dt><label for="phone">Phone<span class="required">*</span></label></dt><dd>'
            . '<input type="tel" name="phone" id="phone" value="+41 44 668 18 00"></dd></dl></form>',
            $form->render()
        );
    }

    public function testFormWithTheTokenInTheQueryStringHasNoError(): void
    {
        $form = $this->createForm();
        $formName = $form->name;
        $form->addField(formField: $this->phone());

        $isValid = $form->validate(
            input: FormInput::fromArray(
                data: ['phone' => '044 668 18 00'],
                query: [$formName => '', 'csrftoken' => 'tok+en/1=']
            )
        );

        $this->assertTrue($isValid);
        $this->assertSame(
            '<form method="post" action="?' . $formName . '"><input type="hidden" name="csrftoken" value="tok+en/1=">'
            . '<dl><dt><label for="phone">Phone<span class="required">*</span></label></dt><dd>'
            . '<input type="tel" name="phone" id="phone" value="+41 44 668 18 00"></dd></dl></form>',
            $form->render()
        );
    }
}