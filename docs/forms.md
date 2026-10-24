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

## Formatting amounts

The values of the number fields have no thousands separator. `AmountFormatter` (`actra\yuf\common`) formats them for
output: `swiss()` (`1'234.50`, ASCII apostrophe), `german()` (`1.234,50`), `english()` (`1,234.50`), or
`new AmountFormatter(thousandsSeparator: '’', decimalSeparator: '.')`. Decimals are formatted from the decimal string
with bcmath (never via float); the currency is up to the caller.

```php
$formatter = AmountFormatter::swiss();
$formatter->formatDecimal(amount: '27839.32'); // 27'839.32
$formatter->formatDecimal(amount: '-2500.5', scale: 2); // -2'500.50 (pads or rounds half away from zero)
$formatter->formatInt(amount: 1234567); // 1'234'567
'CHF ' . $formatter->formatDecimal(amount: $price->getRequiredValueAsDecimal());
```

Only a minus sign, digits and an optional dot with digits are accepted (`1,000.00`, `+1`, `.5` throw an
`InvalidArgumentException`).

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

## Maximum length

`maxLength:` of the input fields (`TextField`, `EmailField`, `IntegerField`, `PasswordField`, ...) is rendered as
`maxlength` attribute and validated on the server (number of characters of the text; message `FormMessages::$tooLong`,
`[max]` is replaced). A `MaxLengthRule` of the project with the same or a smaller limit replaces that check, so the user
gets one error. `TextAreaField` has no `maxLength:`; add a `MaxLengthRule`.

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
$recipients->addEachRule(formRule: new ValidEmailAddressRule(
    errorMessage: $invalidAddress,
    mailDomainResolver: $this->context->formContext->mailDomainResolver,
));
```

## Email addresses

`EmailField` and `ValidEmailAddressRule` always check the syntax, and the domain with the `mailDomainResolver:` of the
`FormContext`:

```php
$email = new EmailField(
    name: 'email',
    label: HtmlText::fromHtml(html: 'E-Mail'),
    value: null,
    invalidError: $invalidEmail,
    mailDomainResolver: $this->context->formContext->mailDomainResolver, // null: syntax only
);
```

- `SystemMailDomainResolver` (default): the domain needs an MX record, or without one an A record of a public address
  that accepts connections on port 25. A domain with a null MX (RFC 7505, e.g. `example.com`) is rejected. With
  `trueOnDnsError: true` (default) a failed DNS lookup accepts the address.
- In debug mode (`'debug' => true` in `.env.php`) `Core` sets `AcceptingMailDomainResolver`: no domain check, so test
  domains (`.test`, `.invalid`, `example.com`) work locally, where all mail goes to a mail catcher.
- A project can pass its own `MailDomainResolver`.

## Request data

A form gets the request and the CSRF token source from its `FormContext` (`$this->context->formContext`).
`validate()` and `isSent()` read the posted data (for a GET form the query string); the `input:` argument is optional.
To validate other data, e.g. in a test, pass a `FormInput`. The sent indicator (`?order`) is part of the query:

```php
$isValid = $form->validate(input: FormInput::fromHttpRequest(httpRequest: $httpRequest, methodPost: true));
$isValid = $form->validate(input: FormInput::fromArray(data: ['customer' => 'Ann'], query: ['order' => '']));
```

## CSRF protection

A POST form has a CSRF field, and `validate()` checks the token first: with a missing or invalid token the form gets the
error `FormMessages::$invalidCsrfToken` and nothing else of the request is read (no field is validated, no listener
runs, no upload is stored or removed). A GET form has no token. Without session (`individualSessionHandler: false`) the
`FormContext` has no token source and a POST form throws a `LogicException` when it is created, unless the project opts
out explicitly:

```php
new Form(context: $context, name: 'search', csrfProtection: false); // only for forms without session
```

## Spam protection

Public forms (contact, order, sign-up) add a honeypot and a signed render time where the fields should be rendered,
usually before the `FormControl`:

```php
$form->addSpamProtection(spamProtection: new SpamProtection(
    clock: $clock,
    secret: $environmentSettings->getString(key: 'spamProtectionSecret'), // bin2hex(random_bytes(32))
    rateLimit: new FormRateLimit(
        rateLimiter: $rateLimiter, // one RateLimiter per project, see rate-limiting.md
        failMode: RateLimitFailModeEnum::CLOSED, // or OPEN, see below
        logger: $logger,
    ), // null as explicit opt-out
    captcha: null, // or a FormCaptcha, see captcha.md
    emailField: $emailField, // or null
    rejectedEmailAddresses: ['info@example.com'], // e.g. the own address; [] as explicit opt-out
));
```

- It adds the text field `internal` (label `FormMessages::$spamHoneypotLabel`, `autocomplete="off"`) and the hidden
  field `timestamp`, with the markup of a `TextField` and a hidden field (other names: `honeypotName:`,
  `renderTimeName:`). Hide the honeypot with the CSS of the project (e.g. `dl:has(#internal)`).
- `validate()` fails with an error of the form if the e-mail address is rejected (case-insensitive), the honeypot is
  filled or the form is submitted less than 3 or more than 3600 seconds after rendering (`minSeconds:`,
  `maxSeconds:`). The render time is signed with the secret and the form name (HMAC-SHA256); a missing, unsigned or
  manipulated value counts as expired. The signature is bound to the session of the user (the CSRF token source of the
  `FormContext`), so a render time of another session is expired too. The texts are `spamRejectedEmailAddress`,
  `spamHoneypotFilled` and `spamRetry` of `FormMessages`.
- The rules are checked only if the other fields are valid: with field errors the form shows them
  and `globalErrorMessage`, not a spam text. A form rendered again keeps a validly signed, not expired posted render
  time (a visitor who corrects a field is not too fast); otherwise it renders the current time.
- `SpamCheck` has the rules without the form, e.g. for tests with a `FixedClock`; `sessionBinding:` is the session
  value (the CSRF token), `null` only without session.
- `captcha:` adds a CAPTCHA widget (Cloudflare Turnstile), verified after all other rules: [captcha.md](captcha.md).

### Rate limit

`rateLimit:` is required: every public form is limited, backend forms (without `addSpamProtection()`) pay nothing.
`FormRateLimit` counts every submit with a valid CSRF token, valid or not, per client IP address
(`RateLimiter::ipBucket()`, IPv6 per /64) and form name.

- Limits: `clientLimits:` (default 10 per 10 minutes and 50 per day) and `globalLimits:` per form for all clients
  (default none; for forms that cost money or call third parties), checked after the client limits, so a blocked client
  uses up nothing of the global budget. Keys: `form|<form name>|<ip bucket>` and `form|<form name>`.
- Blocked: `validate()` returns `false`, the form has the error `FormMessages::$rateLimited` (en/de) and
  `$form->retryAfterSeconds` (for `429` and `Retry-After`, if wanted). The fields only read their input, so the typed
  values are shown again: no rules (no DNS check of e-mail fields), no listeners, no new uploads.
- `failMode:` decides what happens when the submit cannot be counted (rate limit directory not writable, invalid
  client IP address); the error is logged with `logger:` either way. `CLOSED` blocks (the visitor sees the rate limit
  message, `retryAfterSeconds` 60): for forms that send mails, cost money or call a third party. `OPEN` allows: for
  forms where a lost submission does more harm (e.g. an order the project checks anyway).
- Requests with an invalid CSRF token are not counted: they read nothing, and a page of another site could otherwise
  use up the budget of its visitors' IP addresses with forged submits.

## File uploads

`FileField` needs a `FileUploadStorage` (in production
`new SessionFileUploadStorage(session:, rootDirectory:, documentRoot:)`) and the
allow-list `allowedFileTypes:` (`UploadFileType::pdf()`, `jpeg()`, `png()`, `gif()`, `webp()`,
`plainText()`, `csv()`, `docx()`, `xlsx()`, `pptx()`, `zip()`, or `new UploadFileType(mimeTypes:, extensions:)`; no SVG,
it can carry scripts).

A `FileField` needs a form with `acceptUpload: true` (else `addField()` throws a `LogicException`). A form without
`acceptUpload` rejects every request with an upload entry or a `multipart/form-data` body with
`FormMessages::$invalidInput` and reads nothing else (checked after the CSRF token and the rate limit).

Every upload is checked before it is stored: the count (`maxFileUploadCount:`, at least 1, else an
`InvalidArgumentException`), `maxFileSize:` (bytes, 10 MB by default; the stored file must have the size PHP
reported) and the type, which is detected from the file content (`finfo`, never taken from the client) and has to fit
the extension of the file name. `UploadedFile::$type` is the detected type. `FileFieldRenderer` renders an `accept`
attribute with the allowed extensions.

Images can be re-encoded (opt-in): `imageReencoder: new GdImageReencoder()` (needs `ext-gd`) writes stored JPEG, PNG,
GIF and WebP files again from their pixels, so metadata (EXIF, GPS position) and data appended to the image (polyglot
files) are dropped. Animated images keep their first frame, colour profiles are lost. An image with more than
`maxPixels:` (40 megapixels by default) is rejected before it is decoded; one that cannot be decoded is removed and
rejected (`fileTypeNotAllowed`). `quality:` (90) applies to JPEG and WebP.

The upload directory `rootDirectory:` is a required argument: choose a directory outside the document root that only
the user of the web server can use (e.g. below the `var/` directory of the project). With
`documentRoot: $core->documentRoot` (required, `null` only without document root) a root inside the document root is
refused with a `RuntimeException` (symbolic links resolved). It is created with mode 0700 (directories 0700, stored
files 0600, whatever the umask is); a root that is a symbolic link or belongs to another user is refused with a
`RuntimeException`. Stored files get a random name without extension (128 bits), never the client name. Call
`FileField::clearData()` after the form has been processed; files of forms that were never completed are removed after
two days. Set the PHP limits for uploads in php.ini ([setup.md](setup.md#request-and-upload-limits)). The pointer of the uploaded files is issued to the session when the field is
created; a posted pointer that was not issued to the session (or expired) is ignored and the field starts with an empty
list, so files of other users cannot be read, added to or removed.

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
