# Forms

A `Form` holds fields. Every field stores and returns its value with a precise type, so no casting is needed. The texts
a form creates itself (e.g. "The invalid input was ignored.") are English; `FormMessages::german()` has the German
texts, own texts are named arguments of `FormMessages`.

```php
$form = new Form(context: $this->context->formContext, name: 'order', messages: FormMessages::german());
$name = new TextField(name: 'customer', label: HtmlText::fromHtml(html: 'Name'), requiredError: $requiredError);
$quantity = new IntegerField(name: 'quantity', label: HtmlText::fromHtml(html: 'Quantity'));
$form->addField(formField: $name);
$form->addField(formField: $quantity);

if ($form->validate()) { // reads the request of the context, only if the form was sent
    $customer = $name->getValueAsString();
    $amount = $quantity->getValueAsInt(); // ?int, null if empty (optional field)
}
echo $form->render();
```

## Rules for forms

- Every form gets the `FormMessages` of the route language; without `messages:` the texts are English.
- Initial values go into the constructor (`value:`, `initialValue:`, `initialValues:`, `isCheckedByDefault:`) or, in a
  field subclass, into the protected `setInitialValue()` (`setInitialValues()`, `setInitiallyChecked()`). The public
  setters change the current value only; `valueHasChanged()` compares with the initial value.
- `PasswordField` gets the purpose of the input: `PasswordPurposeEnum::CURRENT` for a login and for confirming the
  current password, `NEW` for setting a password (sets `autocomplete`).
- Checks of a field are [rules](#rules), never overrides of `checkRules()` or `validateCurrentValue()`.
- An error message with user input is `HtmlText::fromText()` (escaped); `HtmlText::fromHtml()` is for trusted HTML only.
- Form names must be unique per page (see [session-and-login.md](session-and-login.md)).

```php
// $messages->userExists: 'The user "[name]" already exists.'
$userExists = strtr(string: $messages->userExists, from: ['[name]' => $userName]);
$name->addError(errorMessage: HtmlText::fromText(text: $userExists));
```

## Typed values

Each field class has the getter and setter of its value type; a getter that does not fit does not exist, so a wrong
call is a PHPStan error. Call the getters after a successful validation: a value that cannot be converted (text in a
number field, a manipulated array) throws an `UnexpectedValueException` naming the field.

| Field                                                                                                            | Value                         | Getter                              |
|:-----------------------------------------------------------------------------------------------------------------|:------------------------------|:------------------------------------|
| `TextField`, `EmailField`, `PhoneNumberField`, `HiddenField`, `ZipCodeField`, `IbanNumberField`, `PasswordField` | `string`                      | `getValueAsString()`                |
| `TextAreaField`                                                                                                  | `string`                      | `getValueAsString()`, `getValues()` |
| `IntegerField`, `NumericField`, `HiddenIntegerField`                                                             | `?int`                        | `getValueAsInt()`                   |
| `FloatField`                                                                                                     | `?float`                      | `getValueAsFloat()`                 |
| `DecimalField` (money, bcmath, `scale` required)                                                                 | `?string`, e.g. `'12.50'`     | `getValueAsDecimal()`               |
| `DateField`                                                                                                      | `?DateTimeImmutable`          | `getValueAsDateTimeImmutable()`     |
| `TimeField`                                                                                                      | `?TimeOfDay`                  | `getValueAsTimeOfDay()`             |
| `RadioOptionsField`, `SelectOptionsField`, `ToggleField`                                                         | `string` (`''` = none)        | `getValueAsString()`                |
| `CheckboxOptionsField`, `MultiSelectOptionsField`, `MultiToggleField`                                            | `list<string>`                | `getValues()`                       |
| `BooleanField`                                                                                                   | `bool`                        | `isChecked()`                       |
| `FileField`                                                                                                      | `array<string, UploadedFile>` | `getFiles()`                        |

For a field with a required rule (e.g. `requiredError`), use the `getRequiredValueAs…()` getter after a successful
`validate()`: it returns the type without `null` (`getRequiredValueAsInt()`, `…AsFloat()`, `…AsDecimal()`,
`…AsDateTimeImmutable()`, `…AsTimeOfDay()`). An empty field throws a `FormFieldValueMissingException` (a
`LogicException`); there is never a default value.

```php
$date = new DateField(name: 'start', label: $label, value: null, invalidError: $invalid, requiredError: $required);
if ($form->validate()) {
    $start = $date->getRequiredValueAsDateTimeImmutable(); // DateTimeImmutable
}
```

- The setters have the type of the value: `setValue(string)`, `setValue(?int)`, `setValue(?DateTimeImmutable)`,
  `setValues(list<string>)`, `setChecked(bool)`. `PasswordField`, `CsrfTokenField` and `FileField` have none.
- `getValues()` of a `TextAreaField` returns one entry per line (trimmed, without empty lines).
- Invalid input (a manipulated array, an option that does not exist) resets the value, adds one error and skips the
  other rules. `PasswordField` is never rendered back.

```php
$price = new DecimalField(name: 'price', label: HtmlText::fromHtml(html: 'Price'), scale: 2, initialValue: '12.50');
$agree = new BooleanField(name: 'agree', label: HtmlText::fromHtml(html: 'I agree'), isCheckedByDefault: false);
$tags = new MultiSelectOptionsField(name: 'tags', label: $label, formOptions: $options, initialValues: ['a']);
```

## Options with integer keys

PHP turns numeric string keys of an array into integers. `FormOptions` keeps them internally; `getKeys()` /
`getItems()` return the keys as the strings that are rendered and posted (the fields, renderers and `SearchState` use
them). Add database ids with `addIntItem()` and read them back as integers:

```php
$groups = new FormOptions();
foreach ($groupRows as $row) {
    $name = HtmlText::fromText(text: $row->getString(column: 'name'));
    $groups->addIntItem(key: $row->getInt(column: 'id'), htmlText: $name);
}
$group = new SelectOptionsField(name: 'group', label: $label, formOptions: $groups, initialValue: null);
$tags = new MultiSelectOptionsField(name: 'tags', label: $label, formOptions: $groups, initialValues: ['1']);

if ($form->validate()) {
    $groupId = $group->getValueAsInt(); // ?int, null if nothing is selected
    $tagIds = $tags->getIntValues(); // list<int>; also getAddedIntValues(), getRemovedIntValues()
}
```

The input is already checked against the options, so the integer getters only throw an `UnexpectedValueException` if
an option key is not a strict integer (optional minus, digits, nothing else, within the integer range): use the
string getters for options with text keys. `FormOptions::toIntKey(string): ?int` does the same check.

Search forms: `SearchState::checkOptionsFilter()`, `checkMultiOptionsFilter()` (keys as strings), and
`checkIntOptionsFilter()` (`?int`), `checkIntMultiOptionsFilter()` (`list<int>`) take the `FormOptions` of the field.
They remember keys as strings and drop remembered keys that are not an option any more. A posted `''` (the empty
option of the field, e.g. `individualEmptyValueLabel`) resets `checkOptionsFilter()` to `''` and
`checkIntOptionsFilter()` to `null` (no filter).

## Changes and password rules

`Form::hasChanges()` is `true` if any field (also the child fields of toggle fields) differs from its initial value.
The CSRF field never counts; a typed password and an uploaded file do.

`PasswordField::setMinLength(minLength: 12)` requires a minimum length in characters (a rule for fields that set a
password, `PasswordPurposeEnum::NEW`); the message is `FormMessages::$passwordTooShort` (`[min]` is replaced) or the
`errorMessage:` argument. `EqualsFieldRule` compares a text with another field, e.g. a password confirmation; add the
compared field to the form first:

```php
$purpose = PasswordPurposeEnum::NEW;
$password = new PasswordField(name: 'password', label: $label, requiredError: $required, purpose: $purpose);
$password->setMinLength(minLength: 12);
$confirm = new PasswordField(name: 'confirm', label: $label, requiredError: $required, purpose: $purpose);
$confirm->addRule(formRule: new EqualsFieldRule(otherField: $password, errorMessage: $differentError));
```

## Compact fields

The default frame of a field is a definition list (`<dl><dt>label</dt><dd>control</dd></dl>`).
`Form::useCompactFieldRenderer()` renders the fields without renderer of their own with `CompactFieldRenderer`:
a plain `<div>` with the label and the control (plus errors and field info, only if there are some; then the div has
`class="has-error"`).
For one field, call `$field->setRenderer(renderer: new CompactFieldRenderer(formField: $field))`. Meant for search and
filter forms.

## Rules

Rules are small pure predicates with a typed parameter; the field calls them for a non-empty value only. Add them with
`addRule()` (text), `addValueRule()` (`IntegerField`, `FloatField`, `DecimalField`) or `addEachRule()` (every line of a
`TextAreaField`, every key of a multi field). A custom rule extends the base that fits the value (`StringRule`,
`StringListRule`, `IntegerRule`, `FloatRule`, `DecimalRule`):

```php
final class NoSpacesRule extends StringRule
{
    public function validate(string $value): bool
    {
        return !str_contains($value, ' ');
    }
}

$name->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $tooShort));
$name->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $noSpaces));
$quantity->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $atLeastOne));
$recipients->addEachRule(formRule: new ValidEmailAddressRule(errorMessage: $invalidAddress));
```

## Request data

A form gets the request and the CSRF token source from its `FormContext` (`$this->context->formContext`).
`validate()` and `isSent()` read the posted data (for a GET form the query string); the `input:` argument is optional.
To validate other data, e.g. in a test, pass a `FormInput`. The sent indicator (`?order`) is part of the query:

```php
$isValid = $form->validate(input: FormInput::fromHttpRequest(httpRequest: $httpRequest, methodPost: true));
$isValid = $form->validate(input: FormInput::fromArray(data: ['customer' => 'Ann'], query: ['order' => '']));
```

## File uploads

`FileField` needs a `FileUploadStorage` (in production `SessionFileUploadStorage::forHttpRequest(session:,
httpRequest:)`) and the allow-list `allowedFileTypes:` (`UploadFileType::pdf()`, `jpeg()`, `png()`, `gif()`, `webp()`,
`plainText()`, `csv()`, `docx()`, `xlsx()`, `pptx()`, `zip()`, or `new UploadFileType(mimeTypes:, extensions:)`; no SVG,
it can carry scripts).

Every upload is checked before it is stored: `maxFileSize:` (bytes, 10 MB by default) and the type, which is detected
from the file content (`finfo`, never taken from the client) and has to fit the extension of the file name.
`UploadedFile::$type` is the detected type. `FileFieldRenderer` renders an `accept` attribute with the allowed
extensions.

## Extending the form classes

Documented extension points: `Form` (one subclass per form), `TextField`, `TextAreaField`, `SelectOptionsField`,
`CheckboxOptionsField`, `RadioOptionsField`, `BooleanField`, `IntegerField` and `FormControl` for fields with a fixed
meaning, and the abstract bases `FormComponent`, `FormField`, `FormRenderer`, `FormFieldListener` and the rule bases.
Every other form class is `final`: customize it through its constructor, setters, rules, listeners and
`setRenderer()`.

Renderers build their tags with `HtmlTagAttribute::fromText()` (plain text, escaped), `fromHtml()` (trusted HTML, as it
is) and `fromName()` (no value, e.g. `required`):

```php
new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: [
    HtmlTagAttribute::fromText(name: 'placeholder', text: 'Name & "Vorname"'),
    HtmlTagAttribute::fromName(name: 'required'),
]);
```

## Phone numbers

`PhoneNumberField` accepts valid numbers only; see [phone-numbers.md](phone-numbers.md).
