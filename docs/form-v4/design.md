# Design: Form fields with typed values (v4)

Status: approved (2026-10-04), refined in review. Task 1 of [plan.md](plan.md). Base: code of v3.3.0
(`src/form/`), the notes of [docs/form-typed-values/plan.md](../form-typed-values/plan.md) and
[value-types.md](../form-typed-values/value-types.md).

## 1. Goals and constraints

- Every field stores and returns its value with a precise native type. No `mixed` in `src/form/`, PHPStan level 10 with
  no baseline entries left for `src/form/` (140 entries today, 119 blocks).
- No feature is lost. Breaking changes are allowed, each one is listed in `UPGRADE.md` (`[v4.0.0]`) with before/after.
- Zero runtime dependencies. `ext-bcmath` is already required (`composer.json`, used by `IbanNumberField`).
- The v3.3.0 getters are the API to keep, with the same names and semantics: `getValueAsString()` (textual and single
  option fields only, not on numbers and dates), `getValueAsInt()`, `getValueAsFloat()`, `getValues()`,
  `getValueAsDateTimeImmutable()`, `isChecked()`, `HiddenField` `valueIsInt` (now `HiddenIntegerField`), `AmountParser`.
  `null`/missing input gives `''`/`null`/`[]`; invalid values throw an `UnexpectedValueException` naming the field.
  Typed setters replace `setValue(mixed)`.
- Code rules of `docs/code-quality.md` apply: `final` by default (except the extension points, 3.12), one purpose
  per class, composition over inheritance, enums `*Enum`, no new abstraction without a reason, pure logic separated
  from I/O.

## 2. Current state

Problems the design solves (all verified in the code):

- **`mixed` storage.** `FormField` has `private mixed $value`, untyped `getRawValue()`, `getOriginalValue()`,
  `setValue()`, `setOriginalValue()` (`FormField.php`). Every rule and four renderers read `getRawValue()` and re-check
  the type (`rule/*`, `SelectOptionsRenderer:26`, `DefaultOptionsRenderer:80`, `TextAreaRenderer:91`,
  `CheckboxItemRenderer:114`). 18 rules, 4 renderers and 6 fields read it; 140 baseline entries.
- **Flag arguments.** `AmountField(valueIsFloat)`, `SelectOptionsField(acceptMultipleSelections)`,
  `ToggleField(multiple)`, `HiddenField(valueIsInt)`, `FormField::validate(..., bool $overwriteValue)`,
  `FormComponent::addError(..., bool $isEncodedForRendering)`.
- **One class, several value types.** `SelectOptionsField`/`ToggleField` are single or multiple by flag; a "multiple"
  field can still hold a string. `BooleanField` is a `CheckboxOptionsField` holding `['checked']`. `TextAreaField` holds
  `null|string|array`. `AmountField`/`NumericField` hold a numeric string.
- **Validation without types.** Rules re-implement "is the value empty" and "what type is it" each. `MinValueRule`,
  `MaxValueRule` and `ValueBetweenRule` throw for strings, i.e. for every posted value (no test covers them). Rules
  change the value (`ValidEmailAddressRule`, `ValidDateRule`, `ValidTimeRule`, `PhoneNumberRule` call `setValue()`).
- **Input boundary.** `FormField::validate(array $inputData)` takes raw `mixed` data (`Form::validate()` builds it from
  `$_POST|$_GET + $_FILES`). Array input is rejected inside `setValue()` and the old value stays (oddity list of the v4
  plan). `FileField`, `PhoneNumberField` and `ZipCodeField` do their own ad-hoc narrowing.
- **Global state.** `FileField` uses `$_SESSION`, `$_SERVER['SERVER_NAME']`, the temp directory, `move_uploaded_file()`,
  `time()`; `CsrfTokenField::getHtmlTag()` and `ValidCsrfTokenValue` use the session token and `$_GET`; `Form` has
  `static $formNameList`, `$_POST/$_GET/$_FILES` in `validate()` and `$_GET` in `isSent()`.
- **Hard-coded German texts** in `FormField` ("Die ungültige Eingabe wurde ignoriert."), `AmountField`, `HiddenField`,
  `ZipCodeField`, `RadioOptionsField`, `FileField` (4 constants + 2 templates), `FileFieldRenderer` ("löschen"),
  `FormControl` ("Abbrechen"), `ValidCsrfTokenValue`; two English ones (`OptionsField` "Selected invalid value in
  field", `SelectOptionsField` "-- Please select --").
- **Other oddities.** `PasswordField` is rendered back with the posted password (`InputFieldRenderer` uses
  `renderValue()`, which is the stored value). `InputFieldRenderer` accepts `InputField|OptionsField`, but reads
  `inputType`, which `OptionsField` does not have. Child fields of a `ToggleField` never get `topFormComponent`
  (listeners on them would fail). `ToggleField` creates renderers from the class string `$defaultChildFieldRenderer`.
  `isValueEmpty()` treats `['0']` and `false` as empty; `ValidateAgainstOptions` therefore accepts `['0']` for any
  options.
- **Templates do not depend on the field value API.** `src/template/customtags/*` (`FormComponentTag`, `CheckboxTag`,
  `RadioTag`, `OptionsTag`, ...) only call `getChildComponent()`/`render()` and read data from template variables. No
  template change is needed; projects pass `getValues()`/`getValueAsString()` into the template data.

## 3. Decisions

### 3.1 Value model per field type

Format: class(es): value (empty is), getters, setters. Each public setter changes only the current value; its protected
twin `setInitialValue()` sets the initial value (rules below).

- `TextField`, `EmailField`, `PhoneNumberField`, `HiddenField` (base `SettableStringInputField`, 3.2) and
  `ZipCodeField`, `IbanNumberField` (via `TextField`): `string` (empty: `trim() === ''`); `getValueAsString(): string`;
  `setValue(string)`; protected `setInitialValue(string)`.
- `PasswordField` (base `StringInputField`, 3.2): `string`; `getValueAsString(): string`; **no public setter**, no
  initial value (a password cannot be pre-filled). Required constructor argument `PasswordPurposeEnum $purpose`
  instead of a free `autoComplete` argument (decision 18).
- `CsrfTokenField`: the posted token as internal `string`; **no getter and no setter** (the expected token comes from
  the `CsrfTokenSource`, 3.11; the token cannot be overwritten).
- `TextAreaField`: `string`, the posted text (empty: `trim() === ''`); `getValueAsString()`, `getValues():
  list<string>`; `setValue(string)`; protected `setInitialValue(string)`.
- `IntegerField`, `NumericField`, `HiddenIntegerField`: `?int` (empty: `null`); `getValueAsInt(): ?int`;
  `setValue(?int)`; protected `setInitialValue(?int)`.
- `FloatField`: `?float` (empty: `null`); `getValueAsFloat(): ?float`; `setValue(?float)`; protected
  `setInitialValue(?float)`.
- `DecimalField`: `?string`, canonical decimal like `'12.50'` (empty: `null`); `getValueAsDecimal(): ?string`;
  `setValue(?string)` (validated decimal string); protected `setInitialValue(?string)`.
- `DateField`: `?DateTimeImmutable` (empty: `null`); `getValueAsDateTimeImmutable(): ?DateTimeImmutable`;
  `setValue(?DateTimeImmutable)`; protected `setInitialValue(?DateTimeImmutable)`.
- `TimeField`: `?TimeOfDay` (3.13, empty: `null`); `getValueAsTimeOfDay(): ?TimeOfDay`; `setValue(?TimeOfDay)`;
  protected `setInitialValue(?TimeOfDay)`.
- `RadioOptionsField`, `SelectOptionsField`, `ToggleField` (single): `string`, the option key, `''` = none (empty:
  `''`); `getValueAsString()`; `setValue(?string)` (`null` = none); protected `setInitialValue(?string)`.
- `CheckboxOptionsField`, `MultiSelectOptionsField`, `MultiToggleField` (multi): `list<string>` (empty: `[]`);
  `getValues(): list<string>`; `setValues(list<string>)`; protected `setInitialValues(list<string>)`.
- `BooleanField`: `bool` (empty: `false`, so a required rule means "must be checked"); `isChecked(): bool`;
  `setChecked(bool)`; protected `setInitiallyChecked(bool)`.
- `FileField`: `array<string, UploadedFile>`, key = sha1 of the stored path (empty: `[]`); `getFiles()`; no setter (the
  files live in the `FileUploadStorage`).

Rules behind the list:

- The empty value is a real value of the type (`''`, `[]`, `false`, `null` for numbers/dates), never `mixed`.
  `isValueEmpty()` (public, kept) is derived from it: no `array_filter()`, so `'0'` is a normal value everywhere.
- **Getters.** The typed getter is the API of a field. `getValueAsString()` exists only on textual fields and single
  option fields (the value is a text there). `IntegerField`, `FloatField`, `DecimalField`, `DateField`, `TimeField` (and
  `HiddenIntegerField`, `NumericField`) have **no** `getValueAsString()`: the text for rendering is internal
  (`renderValue()`), a project formats the typed value itself. A getter that cannot fit the type does not exist
  (`IntegerField` has no `getValueAsFloat()`, `FloatField` no `getValueAsInt()`, `getValues()` only on lists).
- The typed getters of numbers and dates return `null` for an empty field and throw an `UnexpectedValueException` naming
  the field if the field holds rejected input (the typed value does not exist then). All other getters never throw.
- **Constructor values and setters** (public and protected) have the same type as the value (`TextField` `?string`,
  `IntegerField` `?int`, `DateField` `?DateTimeImmutable`, `TimeField` `?TimeOfDay`, `MultiSelectOptionsField`
  `list<string>`). A wrong type is a `TypeError` (programming error), not a validation error. A right type with an
  illegal content (`DecimalField`: no decimal string or more decimals than `scale`) is an `InvalidArgumentException`.
  Option fields do not check the key against the options (as in v3; the check is part of the input, 3.3), so a database
  value that is no longer an option does not throw.
- **Setter semantics: initial value and current value are separate.** The *initial value* is what the field starts
  with and what `valueHasChanged()`, `getAddedValues()` and `getRemovedValues()` compare the current value with. The
  constructor value is the initial value (the same operation as `setInitialValue()`). Two kinds of setter:
  - **Public** `setValue()` / `setValues()` / `setChecked()` change **only the current value**: they clear a kept
    rejected input text and add no error, but never touch the initial value. So `valueHasChanged()` is "current value
    differs from the initial value", also after `validate()` and after a programmatic change (a project can use it
    after `setValue()` to learn whether the value differs from the loaded one).
  - **Protected** `setInitialValue()` (`setInitialValues(list<string>)` on multi option fields,
    `setInitiallyChecked(bool)` on `BooleanField`) sets the current **and** the initial value. It is for subclasses
    that fill the field right after `parent::__construct(...)`, typically with data from the database (a project can
    also pass the value to the constructor). It is strict: it throws a `LogicException` once the field has received
    input, i.e. as soon as `validate(FormInput)` or `validateCurrentValue()` has been called on it at least once
    (`FormField` keeps a private flag, set at the start of both). Before that, it may be called repeatedly (the last
    call wins).
  - No public `setOriginalValue()`/`getOriginalValue()`; the typed initial value is internal. All setters apply
    `normalize()` (3.6), so current and initial value stay comparable. `PasswordField` and `CsrfTokenField` have no
    public setter (3.2).
- `TimeField` holds a `TimeOfDay` (3.13). Phone numbers stay a string (internal format `+41.446681800`, as in v3).

### 3.2 Class hierarchy: abstract bases per value family, no generics

Options: (a) `@template T` on `FormField`, (b) one abstract base per value family.

**Recommendation: (b).** PHP has no runtime generics: with `FormField<T>` the value property is natively untyped (a
PHPDoc `@var T`), every consumer needs `FormField<string>` annotations, `Form::getField()` returns `FormField<mixed>`
and rules cannot get native parameter types. With bases, each class has native typed properties, native typed getters
and PHPStan needs no annotations. The cost is a few lines of repeated plumbing per family, which is acceptable.

- `FormField` (abstract, `extends FormComponent`) holds no value. It owns everything value-independent (label, info,
  rules registry, listeners, errors, `validate()` template, `isValueEmpty()`/`valueHasChanged()` as abstract methods).
  It has no setter and no getter of a value (an `LSP` conflict, the setters differ per family).
- `TextualField` (abstract): the field's request value is one text. It owns the pipeline `normalize()` then `accept()`
  (3.3), a protected `getText()` and `addRule(StringRule)`, but **no public value getter or setter**, because its
  subclasses have different value types. `InputField` (abstract, `<input>`: `inputType`, `placeholder`, `autoComplete`,
  `maxLength`) and `TextAreaField` extend it. Typed fields (`IntegerField`, `FloatField`, `DecimalField`, `DateField`,
  `TimeField`, `HiddenIntegerField`) extend `InputField` and override `accept()` to parse the text into their own
  native property; this avoids a second "parsed input" hierarchy.
- `StringInputField` (abstract, extends `InputField`): the string value with `getValueAsString()` and the protected
  `setInitialValue(string)`, but **no public setter**. `PasswordField` extends it directly: a password cannot be
  pre-filled and is only read after a post. The missing setter is a type-level fact, so PHPStan reports
  `$password->setValue(...)` (no runtime exception needed).
- `SettableStringInputField` (abstract, extends `StringInputField`): adds the public `setValue(string)`. Base of
  `TextField`, `EmailField`, `PhoneNumberField`, `HiddenField`. It is the only extra level and exists for exactly one
  reason: to separate "has a public setter" from "has none". `TextField` stays non-final (3.12).
- `CsrfTokenField` extends `InputField` directly (no extra level): it keeps the posted token in a private property and
  compares it with the `CsrfTokenSource` in `accept()` (3.11). It has **neither getter nor setter**: the posted token
  is of no use to a project and the expected token comes from the source, so the token cannot be overwritten.
- `TextAreaField` declares `getValueAsString()`, `setValue(string)` and `setInitialValue(string)` itself. (A typed field
  must not inherit them, so they cannot sit on `TextualField`.)
- `OptionsField` (abstract) with `SingleOptionsField` (value `string`) and `MultiOptionsField` (value `list<string>`).
- `BooleanField` and `FileField` extend `FormField` directly.

Composition is used where inheritance would not be a real "is a": file storage (`FileUploadStorage`), CSRF token source
(`CsrfTokenSource`), messages (`FormMessages`), toggle children (`ToggleChildren`, 3.10), renderers.

### 3.3 Input boundary

Raw request data becomes typed in exactly two places: `FormInput` (narrows `mixed` once) and each field's `readInput()`.

```php
enum InputShapeEnum { case MISSING; case TEXT; case LIST; case INVALID; }

final readonly class FormInput
{
    /** @param array<array-key, mixed> $data @param array<array-key, mixed> $files @param array<array-key, mixed> $query
     *  the only place with `mixed` */
    public static function fromArray(array $data, array $files = [], array $query = []): FormInput;
    public static function fromGlobals(bool $methodPost): FormInput;  // the only access to $_POST/$_GET/$_FILES
    public function getShape(string $name): InputShapeEnum;
    public function getText(string $name): ?string;   // non-null only for TEXT
    /** @return ?list<string> */
    public function getList(string $name): ?array;    // non-null only for LIST
    /** @return list<UploadInput> */
    public function getUploads(string $name): array;  // from $_FILES, both `name` and `name[]` structures (task 5)
    public function hasQueryKey(string $key): bool;   // `Form::isSent()` (the sent indicator is in the query string)
    public function getQueryText(string $key): ?string;  // CSRF token fallback
}
```

- `string` is `TEXT`; an array whose values are all strings is `LIST` (keys dropped); anything else (nested arrays,
  ints) is `INVALID`. `$_POST`-over-`$_FILES` precedence of `Form::validate()` is kept. The query part is always `$_GET`
  (the form action carries the sent indicator, also for POST forms).
- **Keys of posted arrays are kept (added in review).** A posted array (`qty[123]=2`) keeps its int or string keys and
  its order: `FormInput` stores an array whose entries are all strings as it is. `getList(name): ?list<string>` returns
  the values in order without the keys (option fields use it), the new `getMap(name): ?array<int|string, string>`
  returns the array with its keys, for project fields that use them. Both belong to the shape `LIST` (no new shape: the
  shape only says "an array of strings"). Nested arrays and non-string entries stay `INVALID`.
- `FormInput::fromGlobals()` is the only code in `src/form/` that reads `$_POST/$_GET/$_FILES`. `Form::validate()` and
  `Form::isSent()` take `?FormInput $input = null` (default: from globals), so forms are testable without superglobals
  (3.11).
- `FormField::validate(FormInput $input): bool` is a `final` template: read input, run listeners, check required, run
  typed rules, run listeners. The flag `$overwriteValue` becomes a second method `validateCurrentValue(): bool` (same
  steps without reading input). Subclasses cannot bypass the boundary; they customize through `normalize()`, `accept()`,
  the constructor, setters and rules. Extra input (`countryCode`, `_UID`, `_removeAttachment`) is read in the protected
  hook `readAdditionalInput(FormInput)`.
- Per family, how `readInput()` treats the shapes:

  - Text and typed input fields: `TEXT` gives `normalize()`, then `accept()`; `MISSING` gives the empty value;
    `LIST`/`INVALID` are invalid input.
  - `SingleOptionsField`: `TEXT` must be an existing option key, else "invalid option"; `MISSING` gives `''`;
    `LIST`/`INVALID` are invalid input.
  - `MultiOptionsField`: `LIST`: every key must exist, else "invalid option"; `MISSING` gives `[]`; **`TEXT` (`tags=a`
    instead of `tags[]=a`) is invalid input, it is not wrapped into a list**; `INVALID` is invalid input.
  - `BooleanField`: `TEXT` `'checked'` is checked, other text is invalid; `MISSING` gives `false`; `LIST` `['checked']`
    is checked (it is rendered as `name[]`), other lists are invalid.
  - `FileField`: `TEXT`, `LIST` and `INVALID` are ignored (as in v3); `MISSING` means no new files.

- **Invalid input is a validation error, never an exception**: the value is **reset to the empty value** (v3 kept the
  previous value), exactly one error is added (`FormMessages::invalidInput`, or `invalidOption`, or the field's own
  invalid-value message) and the typed rules are **not run** (no second "required" error). `validate()` returns `false`.
- A text that cannot be parsed by a typed field (`'abc'` in an `IntegerField`, `'31.02.2020'` in a `DateField`,
  `'25:00'` in a `TimeField`): typed value `null`, the text is kept for re-rendering, error = the field's
  `individualInvalidError` or the default message.
- Constructor values, setters and `normalize()` use the same path, so `valueHasChanged()` is a plain comparison of the
  typed value with the typed original (fixes the zero-width-space oddity, removes
  `PhoneNumberField::valueHasChanged()`).
- Empty strings in a multi list are dropped at input (`['', 'a']` becomes `['a']`), so `getValues()` needs no filtering
  and never throws.

### 3.4 Public API

- Kept as is: `getValueAsInt()`, `getValueAsFloat()`, `getValues()`, `getValueAsDateTimeImmutable()`, `isChecked()`,
  `isValueEmpty()`, `valueHasChanged()`, `renderValue(): string`, `getAddedValues()`/`getRemovedValues()` (now
  `list<string>`, only on `MultiOptionsField`; `FileField` keeps its own). `getValueAsString()` is kept on textual
  fields and single option fields only (3.1). New: `getValueAsDecimal(): ?string`, `getValueAsTimeOfDay():
  ?TimeOfDay`. **Removed in v4: `getValueAsString()` on the former `AmountField` (v3.3.0), on `DateField` and on
  `TimeField`**; the replacement is in section 5.
- **No generic `getValue()`.** The native type differs per family, so it would be `mixed` on the base, and a typed
  `getValue()` per family would duplicate the named getters. The named getters are the API.
- **Typed setters instead of `setValue(mixed)`** (3.1): public `setValue(string)` on string fields (not on
  `PasswordField`/`CsrfTokenField`) and `TextAreaField`, `setValue(?string)` on single option fields,
  `setValues(list<string>)` on multi option fields, `setValue(?int)`, `setValue(?float)`, `setValue(?string)`
  (`DecimalField`), `setValue(?DateTimeImmutable)`, `setValue(?TimeOfDay)`, `setChecked(bool)`. They change only the
  current value. For pre-filling in a subclass, each family has a protected twin: `setInitialValue(<same type>)`,
  `setInitialValues(list<string>)`, `setInitiallyChecked(bool)` (current and initial value, `LogicException` after
  validation). They are declared per leaf family, never on `FormField` (LSP). Semantics: 3.1.
- **`getRawValue()` is removed** (replaced by the getter that fits; `getRawValue(true)` by the getter plus
  `isValueEmpty()`). **`getOriginalValue()` and `setOriginalValue()` are removed** from the public API: the original
  value is the initial value (constructor or `setInitialValue()`), and `valueHasChanged()`, `getAddedValues()` and
  `getRemovedValues()` cover the use cases.
- `addRule(FormRule)` on the base becomes typed `addRule()` per family (3.8). `addRequiredRule(HtmlText)` and
  `isRequired()` stay; `RequiredRule` is removed.
- `validate(array $inputData, bool)` becomes `validate(FormInput)` and `validateCurrentValue()` (3.3).

### 3.5 Text areas with one entry per line

Options: (a) string-only `TextAreaField` with `getValues()` (v3.3.0), (b) extra class with `list<string>`.

**Recommendation: (a)**, plus `addEachRule(StringRule $rule)`: the rule is applied to every line of `getValues()` (one
error message per rule). `TextAreaField` stays the posted text; `getValues()` (split at CRLF/LF/CR, trim, drop empty
lines) stays exactly as in v3.3.0. A second class would duplicate `getValues()`, and the v3 array value only existed
because projects had no line API. The array constructor value is replaced by `implode(PHP_EOL, $lines)`. `TextAreaField`
stays non-final (extension point, 3.12) and has `setValue(string)`; a project subclass keeps working if it only sets up
rules and sets its values through the constructor, `setInitialValue()` or `setValue()`. The nameserver example is in
section 5.

### 3.6 Input normalization

One hook in `TextualField`: `protected function normalize(string $input): string`, default = remove U+200B, then
`trim()`. Classes override it (no flags). The normalized text is stored, so rules, getters and re-rendering see the
same. Setters and constructor values pass through it too.

- `TextField`, `ZipCodeField`, `IbanNumberField`, `DateField`, numbers: U+200B removed, trimmed.
- `EmailField`, `PhoneNumberField`, `TimeField`: as default, then the canonical form if valid (lower-case e-mail,
  internal phone format, `TimeOfDay`); invalid input is kept trimmed (v3 behaviour, now done by the field, not by a
  rule).
- `TextAreaField`: U+200B removed, **not trimmed** (leading spaces, indentation and line breaks stay).
- `HiddenField`, `CsrfTokenField`: U+200B removed, not trimmed (exact round trip). `HiddenIntegerField` parses a trimmed
  copy.
- `PasswordField`: **no normalization at all**, not trimmed and U+200B kept (v3 removed U+200B). Never rendered back:
  `PasswordField::renderValue()` returns `''` (v3 echoed the posted password into the HTML after an error; fixed in
  v3.3.1, the v4 class keeps it by design).
- `PasswordField` autofill: generic or random field names to block autofill are not used (no longer best practice:
  browsers ignore `autocomplete="off"` on login fields, and password managers lead to stronger passwords, see NIST SP
  800-63B and OWASP). Instead the field is labelled precisely: `enum PasswordPurposeEnum` (`actra\yuf\form\settings`,
  next to the autocomplete enum) with `case CURRENT` (login, confirming the current password) and `case NEW`
  (registration, password change, reset); method `autoComplete(): AutoCompleteEnum` via `match` (`CURRENT_PASSWORD` /
  `NEW_PASSWORD`). The renderer always outputs this `autocomplete` value, so a login or registration form cannot be
  built without it.
- Option fields: none (keys are compared exactly).

### 3.7 Numeric field classes

The flag `valueIsFloat` is replaced by classes. All extend `InputField` (input type `text`, as `AmountField` today) and
parse with the existing `AmountParser` (integer/decimal formats, no exponent, whitespace trimmed, range-safe).

- `IntegerField` (`?int`): replaces `AmountField(valueIsFloat: false)`. Non-final (extension point and parent of
  `NumericField`).
- `NumericField` (final, extends `IntegerField`): kept, same renderer (`inputmode=numeric`, `pattern=\d{min,max}`),
  `minLength`/`maxLength` as today. It is an integer field: leading zeros are not kept (`'007'` becomes `7`). Codes with
  leading zeros use `TextField` plus `RegexRule('/^\d{4,6}$/')`.
- `HiddenIntegerField` (final): replaces `HiddenField(valueIsInt: true)`; `HiddenField` stays string-only. Manipulated
  input is a validation error, so `getValueAsInt()` never fails after a successful validation (same promise as v3.3.0).
- `FloatField` (`?float`): replaces `AmountField(valueIsFloat: true)`. For measurements, not money.
- `DecimalField` (`?string`, bcmath): for money. Required constructor argument `int $scale` (`2` for CHF). Input with
  more significant decimals than `$scale` is **rejected** (validation error, never silently rounded; trailing zeros
  are accepted, `'12.500'` gives `'12.50'`, refined in review); valid input is stored
  canonical (`'12'` becomes `'12.50'`). Dot as separator only (as `AmountParser`, comma input stays invalid). Value is a
  `string` so no float error can occur; arithmetic is up to the project (`bcadd()` etc.), the field only guarantees the
  format.
- None of these has `getValueAsString()` (3.1); the rendered text is the canonical text of the value, internal.
- `AmountField` is removed. Its three-way split is documented in `UPGRADE.md`. `ValidAmountRule`, `FloatValueRule`,
  `NumericValueRule` are removed (the field parses; there is nothing left for a rule to check).
- Range limits: typed rules `IntegerMinRule`/`IntegerMaxRule`, `FloatMinRule`/`FloatMaxRule`,
  `DecimalMinRule`/`DecimalMaxRule` (3.8) via `addValueRule()`. `ValueBetweenRule` is dropped: min and max with the same
  message do the same (at most one of them can fail). `MinValueRule`/`MaxValueRule` are replaced one to one; the
  migration is in section 5.

### 3.8 Rules

Rules get native typed values. Generics are not an option for the same reason as in 3.2.

```php
abstract class FormRule { /* error message storage, as today */ }
abstract class StringRule extends FormRule { abstract public function validate(string $value): bool; }
abstract class StringListRule extends FormRule { /** @param list<string> $values */
                                                  abstract public function validate(array $values): bool; }
abstract class IntegerRule extends FormRule { abstract public function validate(int $value): bool; }
abstract class FloatRule extends FormRule { abstract public function validate(float $value): bool; }
abstract class DecimalRule extends FormRule { abstract public function validate(string $value): bool; }
```

- Fields: `TextualField::addRule(StringRule)` (checks the text of every text-based field, e.g. `MaxLengthRule` also
  works on a number's text), `IntegerField::addValueRule(IntegerRule)`, `FloatField::addValueRule(FloatRule)`,
  `DecimalField::addValueRule(DecimalRule)`, `MultiOptionsField::addRule(StringListRule)` and
  `::addEachRule(StringRule)` (also on `TextAreaField`). A rule of the wrong type is a PHPStan error. The base
  `FormField` has no `addRule()` (a typed override would violate LSP).
- Rules are **pure predicates**: no `setValue()`, no empty check. The field runs typed rules only for a non-empty value
  (today every rule starts with `isValueEmpty()`). Normalization is the field's job (3.6).
- Kept (retyped): `MinLengthRule`, `MaxLengthRule`, `RegexRule`, `ValidValueRule` (`StringRule`);
  `ValidEmailAddressRule` (`StringRule`, syntax and DNS only, used by `EmailField`). New: `MinCountRule`/`MaxCountRule`
  (`StringListRule`, what `MinLengthRule`/`MaxLengthRule` did for arrays), the six numeric rules from 3.7.
- Removed, because the check moves into the field's `accept()` (needs the field state or the value type): `ZipCodeRule`
  and `PhoneNumberRule` (country code of the field), `ValidDateRule`, `ValidTimeRule`, `ValidAmountRule`,
  `FloatValueRule`, `NumericValueRule`, `NoArrayRule`, `ValidateAgainstOptions`, `ValidCsrfTokenValue`, `RequiredRule`,
  `MinValueRule`, `MaxValueRule`, `ValueBetweenRule`. Logic longer than a few lines moves into small pure classes
  (`ZipCodeValidator` with the regex table, `IbanValidator` with the country table) with unit tests.
- **Rules are extension points (non-final); custom rules must extend a typed base.** A project rule extends the typed
  base that fits (`StringRule`, `StringListRule`, `IntegerRule`, `FloatRule`, `DecimalRule`) or an existing rule
  (`MinLengthRule`, ...). A rule that extends `FormRule` directly can no longer be added: every `addRule()` /
  `addValueRule()` / `addEachRule()` takes a typed base (PHPStan error, `TypeError` at runtime). `FormRule`, the typed
  bases and all concrete rules stay non-final; `FormRule::validate(FormField)` with `getRawValue()` is gone. The
  migration is in section 5.
- Listeners (`FormFieldListener`) keep their six hooks and signature (`Form`, `FormField`); they use `isValueEmpty()`.

### 3.9 Error messages

Options: (a) keep hard-coded German, (b) `FormMessages` value object, (c) translation keys through `LocaleHandler`.

**Decision: (b) with English defaults.** `final readonly class FormMessages` has one named constructor argument per
text, **English defaults**, and a factory `FormMessages::german()` with the v3 texts, so a project keeps its texts with
one line: `new Form(name: 'contact', messages: FormMessages::german())`. `Form` takes `messages: FormMessages` (default
`new FormMessages()`, English) and hands it to its fields in `addField()`; a field without a form uses the English
defaults. Fields resolve the default text when they validate or render, so nothing is fixed at construction. Individual
per-field messages (`requiredError`, `individualInvalidError`, ...) stay as constructor arguments and win over the
defaults. A project builds its own texts with `new FormMessages(invalidInput: ..., ...)` (named arguments; texts it
does not pass stay English). (c) is rejected: `LocaleHandler` is global state and would force every project to add
keys. No translation library; i18n beyond this is out of scope. `FileField::ERRMSG_*` constants are removed.

All hard-coded messages of v3.3.0 and their new defaults (`german()` reproduces the v3 text exactly, including the two
texts that were already English):

| Argument | Used by | v3.3.0 text (`german()`) | New English default |
|:--|:--|:--|:--|
| `invalidInput` | `FormField` | Die ungültige Eingabe wurde ignoriert. | The invalid input was ignored. |
| `invalidValue` | `AmountField`, `HiddenField` (now `IntegerField`, `HiddenIntegerField`) | Der angegebene Wert ist ungültig. | The given value is invalid. |
| `invalidZipCode` | `ZipCodeField` | Die eingegebene PLZ ist ungültig. | The entered zip code is invalid. |
| `selectOneOption` | `RadioOptionsField` | Bitte wählen Sie eine der Optionen aus. | Please select one of the options. |
| `selectEmptyOption` | `SelectOptionsField` | -- Please select -- | -- Please select -- |
| `invalidOption` (`[field]`) | `OptionsField` | Selected invalid value in field [name] | Selected invalid value in field [field] |
| `invalidCsrfToken` | `ValidCsrfTokenValue` (now `Form`) | Das Formular konnte wegen eines technischen Problems (ungültiges CSRF) nicht übermittelt werden. Bitte versuchen Sie es erneut. | The form could not be submitted because of a technical problem (invalid CSRF token). Please try again. |
| `cancel` | `FormControl` | Abbrechen | Cancel |
| `removeFile` | `FileFieldRenderer` | löschen | remove |
| `fileEmpty` | `FileField` | Die Datei war leer: | The file was empty: |
| `fileIncomplete` | `FileField` | Die Datei wurde unvollständig hochgeladen: | The file was uploaded incompletely: |
| `fileTooBig` | `FileField` | Die Datei war zu gross: | The file was too big: |
| `fileTechnicalError` | `FileField` | Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten: | A technical error occurred while uploading the file: |
| `tooManyFiles` (`[max]`) | `FileField` | Nur [max] Datei(en) möglich. | Only [max] file(s) allowed. |
| `duplicateFile` (`[fileName]`) | `FileField` | Es wurde bereits eine Datei mit dem Dateinamen "[fileName]" hochgeladen. | A file named "[fileName]" has already been uploaded. |

Texts are plain text and are encoded when used (as `HtmlText::encoded()` today). The default of `selectEmptyOption`
is the same in both languages (v3 behaviour). Migration for projects: add `messages: FormMessages::german()` to every
`new Form(...)`, nothing else (see section 5). Without it, the form shows the English defaults.

### 3.10 Options fields: single and multiple are separate classes

**Recommendation:** split by value type, not by flag.

- `SelectOptionsField` (single, `string`) and new `MultiSelectOptionsField` (`list<string>`); `ToggleField` (single) and
  new `MultiToggleField`; `RadioOptionsField` (single) and `CheckboxOptionsField` (multi) as today. The flags
  `acceptMultipleSelections` and `multiple` are removed. `BooleanField` no longer extends `CheckboxOptionsField`.
- `OptionsField::isSelected(string $optionKey): bool` (abstract; single: equals, multi: `in_array(..., strict: true)`).
  All options renderers (`DefaultOptionsRenderer`, `SelectOptionsRenderer`, `CheckboxItemRenderer`, toggle) use it
  instead of reading the raw value; `SelectOptionsRenderer` takes `SelectOptionsField|MultiSelectOptionsField` via a
  small interface or the shared `OptionsField` plus a `multiple` property of the field (`isMultiple(): bool`, a fixed
  property of the class, not an argument).
- The option check happens in `readInput()`: unknown keys are an "invalid option" error with the empty value (replaces
  `ValidateAgainstOptions`; removes its `int` `TypeError` and the `['0']` oddity). `FormOptions` becomes `final`, gets
  `/** @var array<string, HtmlText> */`.
- `ToggleField` is the largest class (child fields per option, own `getHtmlTag()` ignoring the renderer). It moves its
  child handling into `ToggleChildren` (child registry, validation of the children of the selected options) and its
  markup into a `ToggleFieldRenderer`, so single and multi variants share them by composition and the renderer is again
  the extension point. Children get `topFormComponent` when added. `defaultChildFieldRenderer` (class string) becomes a
  `Closure(FormField): FormRenderer`.
- All option fields stay **non-final** (3.12).

### 3.11 `FileField`, CSRF and global state

- `FileField` holds `array<string, UploadedFile>`. New `final readonly class UploadedFile(name, type, size, path)`
  (stored file, replaces the mutable `FileDataModel` and its misnamed `tmp_name`); new `final readonly class
  UploadInput` (name, tmpName, type, error, size: raw upload from `FormInput::getUploads()`, replaces
  `convertMultiFileArray()` and the `VALUE_*` constants).
- Storage abstraction (reason: the field is untestable today):

```php
interface FileUploadStorage
{
    /** @return array<string, UploadedFile>  files that vanished from disk are dropped */
    public function load(string $pointer): array;
    /** @param array<string, UploadedFile> $files */
    public function save(string $pointer, array $files): void;
    public function store(string $pointer, UploadInput $upload): UploadedFile;  // move_uploaded_file()
    public function delete(UploadedFile $file): void;
    public function clear(string $pointer): void;                               // clearData()
    public function removeExpired(): void;                                      // older than 2 days
}
```

  `SessionFileUploadStorage` (session + temp directory, `forCurrentRequest()` reads `SERVER_NAME`) is the only class
  touching `$_SESSION`, the file system and the clock. `FileField` takes `?FileUploadStorage $storage = null` (default:
  session storage); tests use an `InMemoryFileUploadStorage` in `tests/Double/`. Upload logic (limits, duplicates, empty
  files, error codes) stays in `FileField` and becomes unit testable.
- `CsrfTokenField`: new `interface CsrfTokenSource { getToken(): string; isValid(string): bool }` with
  `SessionCsrfTokenSource` wrapping the static `CsrfToken` (that class belongs to another area and stays). `Form`
  creates the field with the default source (`?CsrfTokenSource` argument on `Form`); the token is read lazily (no
  session write in the constructor, as today). The posted token comes from `FormInput` (text, then query string like
  `ValidCsrfTokenValue`).
- **`Form` global state is cleaned up in v4, one piece is kept on purpose.** `Form::validate(?FormInput $input =
  null)` and `Form::isSent(?FormInput $input = null)` work on a `FormInput` (default `FormInput::fromGlobals()`);
  `isSent()` asks `FormInput::hasQueryKey($sentIndicator)` instead of reading `$_GET`. After this,
  `FormInput::fromGlobals()` is the only superglobal access in `src/form/` besides `SessionFileUploadStorage` and
  `SessionCsrfTokenSource`.
- **`FormNameRegistry`: the duplicate form name check stays, the static state leaves `Form`.** `final class
  FormNameRegistry` (`actra\yuf\form`, marked `@internal`: public only because `Form` lives in another namespace, not
  part of the API) has exactly one purpose: remember the form names of the current request.

```php
/** @internal */
final class FormNameRegistry
{
    private static array $names = [];                  // list<string>
    public static function register(string $name): void;   // LogicException if already registered (same message as v3)
    public static function reset(): void;                  // for tests (setUp/tearDown), clears the names
}
```

  `Form::__construct()` calls `FormNameRegistry::register($name)` exactly where it checks today, so the behaviour is
  unchanged (`LogicException` 'A Form with the name "x" has already been defined.'); `$formNameList` is removed from
  `Form`. This is the **one deliberately kept piece of global state in `src/form/`** (an exception to the "no new static
  state" rule of `docs/code-quality.md`, because it is existing behaviour that is moved, not new state). Why it stays
  global: the check needs a memory that lives for the whole request and spans independent `Form` instances; passing a
  registry to every `Form` would be boilerplate in every project for a safety check; checking via `FormInput` would
  need static memoization too (a `FormInput` is built per `validate()` call). Containing it in a small named class
  makes it visible, documented and resettable (tests that build the same form name twice call `reset()`).
  Planned in task 6, because it belongs to the `validate(FormInput)` switch of the same method and needs the query part
  of `FormInput` from task 2; task 7 is already the catch-all.

### 3.12 `final`, extension points, enum renames

Projects extend library classes, so `final` is the exception, not the default.

- **Non-final (extension points):** the abstract bases (`FormField`, `TextualField`, `InputField`, `StringInputField`,
  `SettableStringInputField`, `OptionsField`, `SingleOptionsField`, `MultiOptionsField`), `TextField` (also parent of
  `ZipCodeField` and
  `IbanNumberField`), `TextAreaField`, `IntegerField` (parent of `NumericField`), all option fields
  (`RadioOptionsField`, `SelectOptionsField`, `CheckboxOptionsField`, `ToggleField`, `MultiSelectOptionsField`,
  `MultiToggleField`), `BooleanField` (refined in review), `FormRenderer` and all renderers (projects extend them and
  set them with `setRenderer()`/`getDefaultRenderer()`), `FormFieldListener`, `Form`, `FormCollection`, `FormComponent` and the other
  `Form*` collections, and the rules: `FormRule`, the typed rule bases and all concrete rules (3.8).
- **Final:** all other classes: `EmailField`, `PasswordField`, `PhoneNumberField`, `TimeField`, `DateField`,
  `FloatField`, `DecimalField`, `HiddenField`, `HiddenIntegerField`, `CsrfTokenField`, `NumericField`, `ZipCodeField`,
  `IbanNumberField`, `FileField`, `FormOptions`, `FormInput`, `FormMessages`, `UploadedFile`,
  `UploadInput`, `TimeOfDay`, `ErrorCollection`, the storage/source implementations and the validators. Customization
  of a final field goes through its constructor, setters, rules, listeners and renderer.
- **Enums:** `InputTypeValue` becomes `InputTypeEnum`, `AutoCompleteValue` `AutoCompleteEnum`, `RadioOptionsLayout`
  `RadioOptionsLayoutEnum`, `CheckboxOptionsLayout` `CheckboxOptionsLayoutEnum`; new `InputShapeEnum` (3.3). Case names
  stay.
- Out of scope here (task 7): `FormComponent::addError(string, bool)` flag (propose `addError(HtmlText)` only), public
  mutable properties of `FormField` (`id`, `fieldInfo`, `autoFocus`, ...), `FormRenderer`'s `prepare()`/`getHtmlTag()`
  two-phase API.

### 3.13 `TimeOfDay` value object for `TimeField`

PHP has no time-of-day type, and a `DateTimeImmutable` would need a fake date. `final readonly class TimeOfDay`:

```php
final readonly class TimeOfDay
{
    public function __construct(public int $hour, public int $minute, public int $second = 0);  // ValueError
    public static function fromString(string $time): ?TimeOfDay;  // 'H:i' or 'H:i:s', 00:00 to 23:59:59, else null
    public function toString(): string;       // 'H:i:s'
    public function toShortString(): string;  // 'H:i'
    public function equals(TimeOfDay $other): bool;
}
```

- **Namespace `actra\yuf\common\TimeOfDay`** (next to `ValidatedEmailAddress`, `CountryCodeEnum`): it is a general value
  object without any form dependency (projects use it for opening hours etc.), and `common` already holds such types.
  Putting it in `actra\yuf\form\` would make a general type depend on the form namespace.
- `fromString()` uses the pattern of today's `ValidTimeRule` (`/^(([0-1]\d)|(2[0-3])):[0-5]\d(:[0-5]\d)?$/`) and returns
  `null` for anything else; the value is not a date, so no `DateTimeImmutable` is involved.
- `TimeField`: constructor value, `setValue()` and getter `getValueAsTimeOfDay(): ?TimeOfDay`. Input is parsed by
  `accept()`; unparsable text gives `null`, keeps the text and adds the field's `invalidError`. Rendering: `H:i` of
  the value (`toShortString()`, as v3), or the kept text after an error. `DateTimeFieldCore` is removed (`DateField` and
  `TimeField` each parse their own text).

## 4. Proposed class overview

```
Legend: each public setter has a protected twin `setInitialValue(<same type>)` (current and initial value; multi option
fields `setInitialValues()`, `BooleanField` `setInitiallyChecked()`); not repeated per line.

FormComponent
└─ FormField (abstract)            validate(FormInput), validateCurrentValue(), isValueEmpty(), valueHasChanged(),
   │                               addRequiredRule(), isRequired(), addListener(); no value getter/setter
   ├─ TextualField (abstract)      text pipeline normalize(), accept(); addRule(StringRule); no public getter/setter
   │  ├─ InputField (abstract)     inputType, placeholder, autoComplete, maxLength; InputFieldRenderer
   │  │  ├─ StringInputField (abstract)  string; getValueAsString(), protected setInitialValue(string); no setter
   │  │  │  ├─ SettableStringInputField (abstract)  adds public setValue(string)
   │  │  │  │  ├─ TextField           (open)
   │  │  │  │  │  ├─ ZipCodeField    countryCode, ZipCodeValidator
   │  │  │  │  │  └─ IbanNumberField IbanValidator
   │  │  │  │  └─ EmailField, PhoneNumberField, HiddenField
   │  │  │  └─ PasswordField         no setter, never rendered back, PasswordPurposeEnum
   │  │  ├─ CsrfTokenField           posted token internal; no getter, no setter; CsrfTokenSource
   │  │  ├─ IntegerField           ?int   getValueAsInt(), setValue(?int)          (open) ← NumericField
   │  │  ├─ HiddenIntegerField     ?int   getValueAsInt(), setValue(?int)
   │  │  ├─ FloatField             ?float getValueAsFloat(), setValue(?float)
   │  │  ├─ DecimalField           ?string getValueAsDecimal(), setValue(?string)   scale, bcmath
   │  │  ├─ DateField              ?DateTimeImmutable getValueAsDateTimeImmutable(), setValue(?...)
   │  │  └─ TimeField              ?TimeOfDay getValueAsTimeOfDay(), setValue(?TimeOfDay)
   │  └─ TextAreaField             string, getValueAsString(), getValues(): list<string>, setValue(string),
   │                               setInitialValue(string), addEachRule()   (open)
   ├─ OptionsField (abstract)      formOptions, isSelected(string): bool, getListTagClasses()   (option fields open)
   │  ├─ SingleOptionsField (abstract)   string, getValueAsString(), setValue(?string)
   │  │  └─ RadioOptionsField, SelectOptionsField, ToggleField (ToggleChildren)
   │  └─ MultiOptionsField (abstract)    list<string>, getValues(), setValues(list<string>), getAddedValues(),
   │                                     getRemovedValues(), addRule(StringListRule)
   │     └─ CheckboxOptionsField, MultiSelectOptionsField, MultiToggleField
   ├─ BooleanField                 bool, isChecked(), setChecked(bool)
   └─ FileField                    array<string, UploadedFile>, getFiles(), FileUploadStorage
FormComponent: FormControl, FormInfo, FormSubHeadline, NullField, FormCollection ─ Form (FormInput, FormMessages)

Support: FormInput, InputShapeEnum, UploadInput, UploadedFile, FormMessages (+ german()), FileUploadStorage
         (+ SessionFileUploadStorage), CsrfTokenSource (+ SessionCsrfTokenSource), FormOptions (final),
         AmountParser (unchanged), ZipCodeValidator, IbanValidator, actra\yuf\common\TimeOfDay,
         FormNameRegistry (@internal, the only static state in src/form/: duplicate form names, reset() for tests)
Rules:   FormRule ─ StringRule (MinLength, MaxLength, Regex, ValidValue, ValidEmailAddress),
                    StringListRule (MinCount, MaxCount),
                    IntegerRule, FloatRule, DecimalRule (Min/Max each)    (all rules open; custom rules extend one
                    of the typed bases)
```

## 5. Migration examples

**Text field** (unchanged since v3.3.0):

```php
// v3.2: $name = ScalarCast::toString($field->getRawValue());
$name = $field->getValueAsString();   // v3.3.0 and v4
```

**Setting a value** (typed setters replace `setValue(mixed)`; the original value is split from the current value):

```php
// v3: $field->setValue($row->name);   $field->setOriginalValue($row->name);   // mixed, both calls
// v4, pre-fill from the database: constructor value ...
$field = new TextField(name: 'name', label: $label, value: $row->name);        // initial value
// ... or in a project subclass right after the constructor (protected, LogicException after validate())
final class CustomerField extends TextField
{
    public function __construct(string $name, HtmlText $label, Customer $customer)
    {
        parent::__construct(name: $name, label: $label);
        $this->setInitialValue($customer->name);   // current and initial value; also setInitialValues(list<string>)
    }                                              // on multi option fields, setInitiallyChecked(bool) on BooleanField
}
// v4, change the value later (also after validate()): public setter, the initial value stays
$field->setValue($row->name);          // setValue(string): current value only, the initial value stays
$amount->setValue($row->quantity);     // IntegerField: setValue(?int)
$tags->setValues($row->tags);          // MultiSelectOptionsField: setValues(list<string>)
$agree->setChecked($row->agreed);      // BooleanField
// PasswordField and CsrfTokenField have no setter: $password->setValue('x') is a PHPStan error
```

**Integer amount field** (and the removed `getValueAsString()`):

```php
// v3.3.0
$field = new AmountField(name: 'qty', label: $label, valueIsFloat: false, initialValue: 5, requiredError: $required);
$field->addRule(new MinValueRule(minValue: 1, errorMessage: $tooSmall));   // threw for posted strings
$qty = $field->getValueAsInt();                                              // ?int
$text = $field->getValueAsString();                                          // '' or the number as text

// v4
$field = new IntegerField(name: 'qty', label: $label, initialValue: 5, requiredError: $required);
$field->addValueRule(new IntegerMinRule(min: 1, errorMessage: $tooSmall));
$qty = $field->getValueAsInt();                                              // ?int, unchanged
$text = (string)$field->getValueAsInt();                                     // getValueAsString() is gone on numbers
// float: FloatField / getValueAsFloat()
// money: new DecimalField(..., scale: 2, initialValue: '12.50'), getValueAsDecimal(): ?string
// hidden id: HiddenField(name: 'id', value: $id, valueIsInt: true) -> HiddenIntegerField(name: 'id', value: $id)
```

**`MinValueRule`/`MaxValueRule`/`ValueBetweenRule`** (they only worked with values the project set as `int`/`float`, or
with earlier library versions, never with posted strings in v3.x):

```php
// v3: AmountField (or a custom field) with the value rules
$field->addRule(new MinValueRule(minValue: 1, errorMessage: $tooSmall));
$field->addRule(new MaxValueRule(maxValue: 100, errorMessage: $tooBig));
$field->addRule(new ValueBetweenRule(minValue: 1, maxValue: 100, errorMessage: $outOfRange));

// v4: IntegerField (FloatField, DecimalField) and the typed rules; "between" is a min and a max rule
$field->addValueRule(new IntegerMinRule(min: 1, errorMessage: $outOfRange));
$field->addValueRule(new IntegerMaxRule(max: 100, errorMessage: $outOfRange));
// money limits are decimal strings: new DecimalMinRule(min: '0.05', errorMessage: $tooSmall)
```

**Date and time fields** (`getValueAsString()` is gone, the typed getter stays or is new):

```php
// v3.3.0
$date = $dateField->getValueAsDateTimeImmutable();   // ?DateTimeImmutable, unchanged in v4
$text = $dateField->getValueAsString();              // 'Y-m-d' or ''
$time = $timeField->getValueAsString();              // 'H:i:s' or ''; TimeField(value: '08:30')

// v4
$text = $dateField->getValueAsDateTimeImmutable()?->format('Y-m-d') ?? '';
$time = $timeField->getValueAsTimeOfDay();           // ?TimeOfDay; TimeField(value: new TimeOfDay(8, 30))
$text = $time?->toString() ?? '';                    // 'H:i:s' or ''
$timeField->setValue(TimeOfDay::fromString('08:30'));
```

**Multiple select**:

```php
// v3.3.0
$field = new SelectOptionsField(name: 'tags', label: $label, formOptions: $options, initialValue: ['a', 'b'],
    acceptMultipleSelections: true);
$tags = $field->getValues();          // list<string>, also if a string `tags=a` was posted (wrapped)

// v4
$field = new MultiSelectOptionsField(name: 'tags', label: $label, formOptions: $options, initialValues: ['a', 'b']);
$tags = $field->getValues();          // list<string>, unchanged
// BEHAVIOUR CHANGE: a scalar posted to a multi field (`tags=a` instead of `tags[]=a`) is invalid input (value reset
// to [], one error "invalid input"), it is not wrapped as in v3 and in the v3.3.0 getValues().
```

**Error messages** (English defaults, German texts with one line):

```php
// v3: the German texts were hard-coded
$form = new Form(name: 'contact');

// v4: the default is English; keep the v3 German texts with
$form = new Form(name: 'contact', messages: FormMessages::german());
// own texts: new FormMessages(invalidInput: 'Ungültige Eingabe.', cancel: 'Zurück', ...)  (named arguments)
```

**Project subclass of `TextAreaField` (nameserver field)**:

```php
// v3: array value = one entry per line; the subclass parses and validates in validate()
final class NameserverField extends TextAreaField
{
    public function __construct(string $name, HtmlText $label, array $nameservers, private HtmlText $invalid)
    {
        parent::__construct(name: $name, label: $label, value: $nameservers);   // array enables array input
    }

    public function validate(array $inputData, bool $overwriteValue = true): bool
    {
        if (is_string($inputData[$this->name] ?? null)) {
            $lines = preg_split('/\R/', $inputData[$this->name]);
            $inputData[$this->name] = array_values(array_filter(array_map('trim', $lines)));
        }
        $isValid = parent::validate($inputData, $overwriteValue);
        foreach ((array)$this->getRawValue() as $line) {
            if (preg_match('/^[a-z0-9.-]+$/i', (string)$line) !== 1) {
                $this->addErrorAsHtmlTextObject($this->invalid);
                return false;
            }
        }
        return $isValid;
    }
}
$nameservers = (array)$field->getRawValue();

// v4: string value, lines through getValues(), per-line rule instead of an overridden validate()
class NameserverField extends TextAreaField   // TextAreaField stays open, the subclass may stay
{
    public function __construct(string $name, HtmlText $label, array $nameservers, HtmlText $invalid)
    {
        parent::__construct(name: $name, label: $label, value: implode(PHP_EOL, $nameservers));
        $this->addEachRule(formRule: new RegexRule(pattern: '/^[a-z0-9.-]+$/i', errorMessage: $invalid));
    }
}
$nameservers = $field->getValues();   // list<string>: trimmed, no empty lines, CRLF-safe
$field->setValue(implode(PHP_EOL, $nameserversFromDatabase));   // change the current value later, typed setter
// pre-fill in the subclass: $this->setInitialValue(implode(PHP_EOL, $nameservers)) after parent::__construct()
```

The subclass is not needed any more (the same `TextAreaField` with `addEachRule()` works), but it still works if the
project keeps it. Overriding `validate()` is no longer possible (`final`), the migration is "move the checks into
rules".

**Custom rule** (a rule extends a typed base, not `FormRule`):

```php
// v3: validate(FormField) read getRawValue() and re-checked the type itself
class NoSpacesRule extends FormRule
{
    public function validate(FormField $formField): bool
    {
        $value = $formField->getRawValue();
        return !is_string($value) || !str_contains($value, ' ');
    }
}

// v4: typed base, native parameter, pure predicate (called for non-empty values only); non-final as before
class NoSpacesRule extends StringRule
{
    public function validate(string $value): bool
    {
        return !str_contains($value, ' ');
    }
}
$field->addRule(new NoSpacesRule(defaultErrorMessage: $message));   // addRule() takes a StringRule, a FormRule is rejected
// int value: extends IntegerRule + addValueRule(); list of strings: extends StringListRule on a multi option field
```

## 6. Impact on tasks 2 to 7

Problem: `FormField` is the base of every field, so removing its `mixed` storage is atomic, while each task must end
with a green `composer check`. **Approved approach:** a temporary bridge from task 2 to 5, removed in task 6.

- The new bases (`TextualField`, `SingleOptionsField`, ...) extend the existing `FormField`, keep their typed value
  themselves and override the legacy methods (`getRawValue()`, `setValue()`, `isValueEmpty()`, `validate(array, bool)`)
  to delegate to it. Untouched rules, renderers and not yet migrated fields keep working. The overrides are ~10 lines
  per base, marked `@internal`, and are deleted in task 6 together with `getRawValue()`. The typed public setters are
  added next to the bridge `setValue(mixed)` in the task of the family, and become the only `setValue()` in task 6.

- **Task 2:** `FormInput` (`fromArray()` incl. query part, text/list shapes), `FormMessages` (+ `german()`,
  `Form(messages:)`, `addField()` handover), `TextualField`, `InputField`, `StringInputField` (no public setter),
  `SettableStringInputField`, string fields (`TextField`, `EmailField`, `HiddenField` as string; `PasswordField`
  extends `StringInputField` directly), `TextAreaField` (`getValues()` stays; `addEachRule()` follows in task 6),
  `normalize()` rules, public setters (current value only) and protected `setInitialValue()` with the
  `LogicException` after validation (flag in `FormField`) for these fields, `InputFieldRenderer`/
  `TextAreaRenderer` without `getRawValue()`. `InputTypeValue`/`AutoCompleteValue` stay until 7.
- **Task 3:** `OptionsField`, `SingleOptionsField`, `MultiOptionsField`, `isSelected()`, the `Multi*` split, typed
  setters and protected `setInitialValue()`/`setInitialValues()`/`setInitiallyChecked()`, `BooleanField` as `bool` with
  `setChecked()`, `ToggleChildren` + `ToggleFieldRenderer`, the options renderers
  without `getRawValue()` (part of this task, not task 7), `FormOptions` final, invalid-option handling, scalar to a
  multi field is invalid input. All option fields stay non-final.
- **Task 4a** (numbers, date/time): `IntegerField`, `NumericField`, `HiddenIntegerField`, `FloatField`, `DecimalField`
  (bcmath), `DateField`, `TimeField` with `TimeOfDay` (`actra\yuf\common`), public setters and protected
  `setInitialValue()`, no `getValueAsString()`, removal of `AmountField` and `DateTimeFieldCore`.
- **Task 4b** (special fields): `PhoneNumberField` (on `SettableStringInputField`), `ZipCodeField` (+
  `ZipCodeValidator`), `IbanNumberField` (+ `IbanValidator`), `HiddenField`, `CsrfTokenField` (extends `InputField`
  directly, no getter, no setter) with `CsrfTokenSource`, `PasswordField` final check (on `StringInputField`, no setter,
  no normalization, never rendered back).
- **Task 5:** `FileField` as designed: `UploadedFile`, `UploadInput`, `FormInput::getUploads()`, `FileUploadStorage`
  with session implementation and in-memory double, messages from `FormMessages`, `FileFieldRenderer` "remove" text.
- **Task 6:** typed rule bases and rules (3.8, all non-final), removal of the obsolete rules,
  `FormField::validate(FormInput)` as `final` template, `validateCurrentValue()`, `Form::validate(?FormInput)` and
  `Form::isSent(?FormInput)` with `FormInput::fromGlobals()`, the static form name list moves from `Form` into
  `FormNameRegistry` (decision 13, with `reset()` for tests; `Form` tests reset it),
  removal of the bridge and of `getRawValue()`/`getOriginalValue()`/`setOriginalValue()` and the bridge
  `setValue(mixed)`; `grep getRawValue src/` is empty. Reason for placing the `Form` clean-up here: `validate()`
  changes in the same task and `isSent()` needs the query part of `FormInput`; task 7 is the catch-all and stays
  smaller.
- **Task 7:** remaining baseline entries (`FormComponent`, collections, `FormInfo`, layout renderers), enum renames
  (`*Enum`), `addError(HtmlText)`, `FormControl` "cancel" text from `FormMessages`, complete `UPGRADE.md` and
  `README.md`.

Further proposals for the task texts: every task owns the matching `UPGRADE.md` entries (per class, as the plan says);
the v3.3.0 characterization tests that pin oddities change on purpose (list in the plan, "Input from v3.3.0"); a
`tests/Unit/form/FormInputTest` and a test per `readInput()` shape table row belong to the task introducing each
family; `TimeOfDay` gets its own unit test in task 4a.

## 7. Decisions (approved by the user, 2026-10-04)

1. **Setters (refined in review):** typed setters stay instead of `setValue(mixed)` (3.1, 3.4). Public
   `setValue()`/`setValues()`/`setChecked()` change only the current value, the initial value (= constructor value)
   stays; protected `setInitialValue()` (`setInitialValues()`, `setInitiallyChecked()`) sets both for subclasses and
   throws a `LogicException` once the field was validated. `getOriginalValue()`/`setOriginalValue()` are removed from
   the public API.
2. **Getters:** only the typed getter on `IntegerField`, `FloatField`, `DecimalField`, `DateField`, `TimeField`;
   `getValueAsString()` stays on textual and single option fields; the v3.3.0 `getValueAsString()` of numbers and dates
   is removed (section 5).
3. **Invalid input:** the value is reset to empty, exactly one error is added and the other rules are skipped.
4. **`NumericField`** becomes a final subclass of `IntegerField` (`'007'` becomes `7`).
5. **`DecimalField`:** more significant decimals than `scale` are rejected (no rounding; trailing zeros accepted,
   refined in review), `scale` is required, dot only.
6. **`TimeField`** gets the value object `TimeOfDay` (`actra\yuf\common`, 3.13), getter `getValueAsTimeOfDay()`;
   `DateField` uses `?DateTimeImmutable`.
7. **`PasswordField`:** no normalization at all, never rendered back.
8. **`FormMessages`** with English defaults, configured per `Form`, no `LocaleHandler`; `FormMessages::german()`
   reproduces the v3 texts (3.9).
9. **Extension points are non-final:** `TextField`, `TextAreaField`, all option fields, `BooleanField` (refined in
   review, it has a protected `setInitiallyChecked()`), `IntegerField`, the abstract
   bases, renderers, `Form*` collections and all rules (`FormRule`, typed bases, concrete rules); everything else final
   (3.12).
10. **Split into `Multi*` classes;** `BooleanField` is no longer a `CheckboxOptionsField`.
11. **Migration strategy:** temporary `@internal` bridge in tasks 2 to 5, every task green, removal in task 6.
12. **`FileField`:** `FileDataModel` becomes `UploadedFile`, constants removed, optional `FileUploadStorage` with
    session default.
13. **`Form` global state (refined in review)** is cleaned up in v4 (task 6): `isSent()` and `validate()` work with
    `FormInput`, `FormInput::fromGlobals()` is the only superglobal access. The duplicate form name check stays, its
    static state moves into the `@internal` class `FormNameRegistry` (`register()`, `reset()`), the one deliberately
    kept global state in `src/form/` (3.11).
14. **`MinValueRule`/`MaxValueRule`/`ValueBetweenRule`** are replaced by the typed numeric Min/Max rules,
    `ValueBetweenRule` is dropped; migration example in section 5.
15. **A scalar posted to a multi field** (`tags=a`) is invalid input (reset plus one error), not wrapped; differs from
    v3 and the v3.3.0 `getValues()`, goes into the `UPGRADE.md` notes.

16. **Custom rules (confirmed in review)** must extend a typed rule base (`StringRule`, `IntegerRule`, ...), never
    `FormRule` directly (3.8, section 5).
17. **`PasswordField` and `CsrfTokenField` without public setter (refined in review):** `StringInputField` has no
    setter, `SettableStringInputField` adds `setValue(string)` for `TextField`, `EmailField`, `PhoneNumberField`,
    `HiddenField`; `PasswordField` extends the former; `CsrfTokenField` extends `InputField` directly and has neither
    getter nor setter (3.2).
18. **`PasswordField` purpose (added in review):** required `PasswordPurposeEnum $purpose` (`CURRENT` → autocomplete
    `current-password`, `NEW` → `new-password`) replaces the free `autoComplete` argument; no generic/random field
    names to block autofill (3.6).
19. **Keys of posted arrays (added in review):** `FormInput` keeps the keys of a posted array of strings
    (`getMap()`), `getList()` returns the values without keys; one shape `LIST` for both (3.3).

New open questions: none.