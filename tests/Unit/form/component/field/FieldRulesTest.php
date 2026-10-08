<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\field\DecimalField;
use actra\yuf\form\component\field\FloatField;
use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\NumericField;
use actra\yuf\form\component\field\RadioOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\form\rule\DecimalMinRule;
use actra\yuf\form\rule\FloatMaxRule;
use actra\yuf\form\rule\IntegerMaxRule;
use actra\yuf\form\rule\IntegerMinRule;
use actra\yuf\form\rule\MaxCountRule;
use actra\yuf\form\rule\MinCountRule;
use actra\yuf\form\rule\MinLengthRule;
use actra\yuf\form\rule\RegexRule;
use actra\yuf\form\rule\ValidValueRule;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\NoSpacesRule;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The typed rules of the field families: rules run for a non-empty value only, rejected input skips them.
 */
final class FieldRulesTest extends TestCase
{
    private function text(string $text): HtmlText
    {
        return HtmlText::fromHtml(html: $text);
    }

    private function options(): FormOptions
    {
        $options = new FormOptions();
        $options->addItem(key: 'a', htmlText: $this->text('A'));
        $options->addItem(key: 'b', htmlText: $this->text('B'));
        $options->addItem(key: 'c', htmlText: $this->text('C'));

        return $options;
    }

    private function textField(): TextField
    {
        return new TextField(name: 'field', label: $this->text('Field'));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function post(FormField $field, array $data): bool
    {
        return $field->validate(input: FormInput::fromArray(data: $data));
    }

    /**
     * @return list<string>
     */
    private function errorsOf(FormField $field): array
    {
        $errors = [];
        foreach ($field->errorCollection->listErrors() as $error) {
            $errors[] = $error->render();
        }

        return $errors;
    }

    public function testTextRuleAddsItsErrorForAFailingText(): void
    {
        $field = $this->textField();
        $field->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));

        $isValid = $this->post(field: $field, data: ['field' => 'ab']);

        $this->assertFalse($isValid);
        $this->assertSame(['Too short'], $this->errorsOf(field: $field));
    }

    public function testTextRuleAcceptsAValidText(): void
    {
        $field = $this->textField();
        $field->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));

        $this->assertTrue($this->post(field: $field, data: ['field' => 'abc']));
    }

    public function testTextRulesDoNotRunForAnEmptyValue(): void
    {
        $field = $this->textField();
        $field->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));

        $this->assertTrue($this->post(field: $field, data: ['field' => '  ']));
    }

    public function testEveryFailingTextRuleAddsItsError(): void
    {
        $field = $this->textField();
        $field->addRule(formRule: new MinLengthRule(minLength: 5, errorMessage: $this->text('Too short')));
        $field->addRule(formRule: new RegexRule(pattern: '/^\d+$/', errorMessage: $this->text('Digits only')));

        $this->post(field: $field, data: ['field' => 'ab']);

        $this->assertSame(['Too short', 'Digits only'], $this->errorsOf(field: $field));
    }

    public function testCustomRuleOfATypedBaseIsApplied(): void
    {
        $invalid = $this->textField();
        $invalid->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $this->text('No spaces')));
        $valid = $this->textField();
        $valid->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $this->text('No spaces')));

        $this->assertFalse($this->post(field: $invalid, data: ['field' => 'a b']));
        $this->assertTrue($this->post(field: $valid, data: ['field' => 'ab']));
    }

    public function testRulesDoNotRunForRejectedInput(): void
    {
        $field = $this->textField();
        $field->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));

        $this->post(field: $field, data: ['field' => ['x']]);

        $this->assertSame(['The invalid input was ignored.'], $this->errorsOf(field: $field));
    }

    public function testRulesRunInValidateCurrentValueWithoutReadingInput(): void
    {
        $field = new TextField(name: 'field', label: $this->text('Field'), value: 'ab');
        $field->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));

        $this->assertFalse($field->validateCurrentValue());
        $this->assertSame('ab', $field->getValueAsString());
    }

    public function testRequiredErrorIsAddedForAnEmptyValueOnly(): void
    {
        $field = $this->textField();
        $field->addRequiredRule(errorMessage: $this->text('Required'));
        $field->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Too short')));

        $this->post(field: $field, data: ['field' => '']);

        $this->assertSame(['Required'], $this->errorsOf(field: $field));
    }

    public function testRequiredIsKnownAfterAddRequiredRule(): void
    {
        $field = $this->textField();
        $this->assertFalse($field->isRequired());

        $field->addRequiredRule(errorMessage: $this->text('Required'));

        $this->assertTrue($field->isRequired());
    }

    public function testRequiredErrorOfTheConstructorMakesTheFieldRequired(): void
    {
        $field = new TextField(
            name: 'field',
            label: $this->text('Field'),
            requiredError: $this->text('Required'),
        );

        $this->assertTrue($field->isRequired());
    }

    public function testASecondRequiredRuleReplacesTheMessage(): void
    {
        $field = $this->textField();
        $field->addRequiredRule(errorMessage: $this->text('First'));
        $field->addRequiredRule(errorMessage: $this->text('Second'));

        $this->post(field: $field, data: []);

        $this->assertSame(['Second'], $this->errorsOf(field: $field));
    }

    public function testRadioFieldIsAlwaysRequired(): void
    {
        $field = new RadioOptionsField(
            name: 'radio',
            label: $this->text('Radio'),
            formOptions: $this->options(),
            initialValue: null,
        );

        $this->assertTrue($field->isRequired());
        $this->assertFalse($this->post(field: $field, data: []));
        $this->assertSame(['Please select one of the options.'], $this->errorsOf(field: $field));
    }

    public function testTextAreaLineRuleChecksEveryLine(): void
    {
        $field = new TextAreaField(name: 'lines', label: $this->text('Lines'));
        $field->addEachRule(
            formRule: new RegexRule(pattern: '/^[a-z0-9.-]+$/i', errorMessage: $this->text('Bad line')),
        );

        $this->assertTrue(
            $this->post(field: $field, data: ['lines' => "ns1.example.com\r\n ns2.example.com \n\n"]),
        );
        $this->assertFalse($this->post(field: $field, data: ['lines' => "ns1.example.com\nns 2"]));
        $this->assertSame(['Bad line'], $this->errorsOf(field: $field));
    }

    public function testTextAreaLineRuleAddsItsErrorOncePerRule(): void
    {
        $field = new TextAreaField(name: 'lines', label: $this->text('Lines'));
        $field->addEachRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Short')));

        $this->post(field: $field, data: ['lines' => "a\nb\nc"]);

        $this->assertSame(['Short'], $this->errorsOf(field: $field));
    }

    public function testTextAreaLineRuleDoesNotRunForAnEmptyText(): void
    {
        $field = new TextAreaField(name: 'lines', label: $this->text('Lines'));
        $field->addEachRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Short')));

        $this->assertTrue($this->post(field: $field, data: ['lines' => " \n "]));
    }

    public function testTextAreaTextRuleChecksTheWholeText(): void
    {
        $short = new TextAreaField(name: 'text', label: $this->text('Text'));
        $short->addRule(formRule: new MinLengthRule(minLength: 5, errorMessage: $this->text('Short')));
        $long = new TextAreaField(name: 'text', label: $this->text('Text'));
        $long->addRule(formRule: new MinLengthRule(minLength: 5, errorMessage: $this->text('Short')));

        $this->assertFalse($this->post(field: $short, data: ['text' => "a\nb"]));
        $this->assertTrue($this->post(field: $long, data: ['text' => "a\nbcd"]));
    }

    public function testIntegerValueRulesCheckTheParsedValue(): void
    {
        $field = new IntegerField(name: 'qty', label: $this->text('Qty'));
        $field->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $this->text('Too small')));
        $field->addValueRule(formRule: new IntegerMaxRule(max: 100, errorMessage: $this->text('Too big')));

        $this->assertFalse($this->post(field: $field, data: ['qty' => '0']));
        $this->assertSame(['Too small'], $this->errorsOf(field: $field));
    }

    public function testIntegerValueRulesAcceptAValueInRange(): void
    {
        $field = new IntegerField(name: 'qty', label: $this->text('Qty'));
        $field->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $this->text('Too small')));
        $field->addValueRule(formRule: new IntegerMaxRule(max: 100, errorMessage: $this->text('Too big')));

        $this->assertTrue($this->post(field: $field, data: ['qty' => ' 100 ']));
    }

    public function testIntegerValueRulesDoNotRunForAnEmptyValue(): void
    {
        $field = new IntegerField(name: 'qty', label: $this->text('Qty'));
        $field->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $this->text('Too small')));

        $this->assertTrue($this->post(field: $field, data: ['qty' => '']));
    }

    public function testIntegerValueRulesDoNotRunForTextThatIsNoInteger(): void
    {
        $field = new IntegerField(
            name: 'qty',
            label: $this->text('Qty'),
            individualInvalidError: $this->text('Not a number'),
        );
        $field->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $this->text('Too small')));

        $this->post(field: $field, data: ['qty' => 'abc']);

        $this->assertSame(['Not a number'], $this->errorsOf(field: $field));
    }

    public function testIntegerFieldAlsoChecksTheTextWithTextRules(): void
    {
        $tooShort = new IntegerField(name: 'qty', label: $this->text('Qty'));
        $tooShort->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Three digits')));
        $longEnough = new IntegerField(name: 'qty', label: $this->text('Qty'));
        $longEnough->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $this->text('Three digits')));

        $this->assertFalse($this->post(field: $tooShort, data: ['qty' => '12']));
        $this->assertTrue($this->post(field: $longEnough, data: ['qty' => '123']));
    }

    public function testNumericFieldTakesIntegerRules(): void
    {
        $field = new NumericField(name: 'zip', label: $this->text('Zip'));
        $field->addValueRule(formRule: new IntegerMinRule(min: 1000, errorMessage: $this->text('Too small')));

        $this->assertFalse($this->post(field: $field, data: ['zip' => '999']));
    }

    public function testFloatValueRulesCheckTheParsedValue(): void
    {
        $field = new FloatField(name: 'weight', label: $this->text('Weight'));
        $field->addValueRule(formRule: new FloatMaxRule(max: 1.5, errorMessage: $this->text('Too heavy')));

        $this->assertTrue($this->post(field: $field, data: ['weight' => '1.5']));
        $this->assertFalse($this->post(field: $field, data: ['weight' => '1.6']));
        $this->assertSame(['Too heavy'], $this->errorsOf(field: $field));
    }

    public function testDecimalValueRulesCheckTheCanonicalValue(): void
    {
        $field = new DecimalField(name: 'price', label: $this->text('Price'), scale: 2);
        $field->addValueRule(formRule: new DecimalMinRule(min: '0.05', errorMessage: $this->text('Too cheap')));

        $this->assertTrue($this->post(field: $field, data: ['price' => '0.05']));
        $this->assertFalse($this->post(field: $field, data: ['price' => '0.04']));
        $this->assertSame(['Too cheap'], $this->errorsOf(field: $field));
    }

    public function testSingleOptionsFieldTakesTextRulesForTheSelectedKey(): void
    {
        $field = new SelectOptionsField(
            name: 'select',
            label: $this->text('Select'),
            formOptions: $this->options(),
            initialValue: null,
        );
        $field->addRule(
            formRule: new ValidValueRule(validValues: ['a', 'b'], errorMessage: $this->text('Not allowed')),
        );

        $this->assertTrue($this->post(field: $field, data: ['select' => 'a']));
        $this->assertTrue($this->post(field: $field, data: ['select' => '']));
        $this->assertFalse($this->post(field: $field, data: ['select' => 'c']));
        $this->assertSame(['Not allowed'], $this->errorsOf(field: $field));
    }

    public function testMultiOptionsFieldTakesListRules(): void
    {
        $field = new MultiSelectOptionsField(
            name: 'select',
            label: $this->text('Select'),
            formOptions: $this->options(),
            initialValues: [],
        );
        $field->addRule(formRule: new MinCountRule(minCount: 2, errorMessage: $this->text('Pick two')));
        $field->addRule(formRule: new MaxCountRule(maxCount: 2, errorMessage: $this->text('Pick at most two')));

        $this->assertFalse($this->post(field: $field, data: ['select' => ['a']]));
        $this->assertSame(['Pick two'], $this->errorsOf(field: $field));
    }

    public function testMultiOptionsFieldAcceptsAListWithinTheLimits(): void
    {
        $field = new CheckboxOptionsField(
            name: 'checkbox',
            label: $this->text('Checkbox'),
            formOptions: $this->options(),
            initialValues: [],
        );
        $field->addRule(formRule: new MaxCountRule(maxCount: 2, errorMessage: $this->text('Pick at most two')));

        $this->assertTrue($this->post(field: $field, data: ['checkbox' => ['a', 'b']]));
        $this->assertFalse($this->post(field: $field, data: ['checkbox' => ['a', 'b', 'c']]));
    }

    public function testMultiOptionsListRulesDoNotRunForAnEmptySelection(): void
    {
        $field = new CheckboxOptionsField(
            name: 'checkbox',
            label: $this->text('Checkbox'),
            formOptions: $this->options(),
            initialValues: [],
        );
        $field->addRule(formRule: new MinCountRule(minCount: 2, errorMessage: $this->text('Pick two')));

        $this->assertTrue($this->post(field: $field, data: []));
    }

    public function testMultiOptionsEachRuleChecksEveryKey(): void
    {
        $field = new CheckboxOptionsField(
            name: 'checkbox',
            label: $this->text('Checkbox'),
            formOptions: $this->options(),
            initialValues: [],
        );
        $field->addEachRule(
            formRule: new ValidValueRule(validValues: ['a', 'b'], errorMessage: $this->text('Not allowed')),
        );

        $this->assertTrue($this->post(field: $field, data: ['checkbox' => ['a', 'b']]));
        $this->assertFalse($this->post(field: $field, data: ['checkbox' => ['a', 'c']]));
        $this->assertSame(['Not allowed'], $this->errorsOf(field: $field));
    }

    public function testValidateIsFinal(): void
    {
        $validate = new ReflectionClass(objectOrClass: FormField::class)->getMethod(name: 'validate');

        $this->assertTrue($validate->isFinal());
    }

    public function testFormFieldHasNoAddRule(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: FormField::class)->hasMethod(name: 'addRule'));
    }
}
