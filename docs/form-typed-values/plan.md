# Plan: Typed form field values (v3.3.0)

Status: planned (2026-10-04). Follow-up for v4: [docs/form-v4/plan.md](../form-v4/plan.md).

## Goal

Form fields get typed value getters, so projects using PHPStan level 10 no longer need casting wrappers like
`ScalarCast::toString($field->getRawValue())`. Two input-handling bugs get fixed.

This is a **minor release without breaking changes**. The new getters are designed as the final API: in v4 they stay,
while `getRawValue()` and the `mixed` value storage go away.

## Rules for every task

- Read and follow `AGENTS.md` and `docs/code-quality.md`.
- **No breaking changes in v3.3.0.** Do not change signatures (parameter types, return types, visibility) of existing
  public or protected methods or constructors. Do not add `final` to existing classes, do not remove or rename anything.
  Only add new methods and fix bugs.
- New methods: fully typed, named arguments, `=== null` instead of `is_null()`, no `mixed`.
- When a stored value cannot have the promised type (a programming error, e.g. an array in a single-value field), throw
  an `UnexpectedValueException` with a message naming the field and the actual type. Never cast silently.
- Baseline: fix the baseline entries of touched files where this is possible without a breaking change. Entries that
  need a signature change stay in the baseline and are listed in the handover notes (they are solved in v4).
- Write tests first where behaviour exists already (characterization), then change code.
- `ddev composer check` must be green at the end of every task. Do not commit.
- Append handover notes to the task below: what was done, decisions, open points.

## Background (verified in v3.2.2)

- `FormField` stores `private mixed $value`. `getRawValue(bool $returnNullIfEmpty = false)` has no return type.
- `FormField::validate()` sets the value from the input data, or `null` (`[]` if arrays are allowed) when the key is
  missing.
- `FormField::setValue()` rejects arrays unless `acceptArrayAsValue()` was called (`CheckboxOptionsField`,
  `SelectOptionsField` with multiple selections, multiple `ToggleField`, any field constructed with an array value).
- Constructor value types: `TextField`, `EmailField`, `ZipCodeField`, `IbanNumberField`, `DateField`, `TimeField`:
  `?string`. `HiddenField`, `InputField`: `int|float|string|bool|null`. `AmountField`, `NumericField`:
  `null|int|float` (stored as string). `TextAreaField`, `SelectOptionsField`, `ToggleField`: `null|string|array`.
  `RadioOptionsField`: `?string`. `CheckboxOptionsField`: `array`.
- `TextAreaField` accepts an array value on purpose: a list of entries, rendered one per line (`renderValue()` joins
  them with `PHP_EOL`). Projects use it, e.g. a subclass that splits the posted text into lines in `validate()` and
  stores them as array with `setValue()`.
- `DateField::getValueAsDateTimeImmutable()` shows the naming pattern for typed getters.
- `FileField` uses `$_SESSION`/`$_SERVER` and stores upload data; it is out of scope for typed getters.

## Tasks

### Task 1: Analyse value types and add characterization tests

Determine for every class in `src/form/component/field/` (and `FormField`) which types its value can have:

- after construction (per allowed constructor value type),
- after `validate()` with the key present (string input, array input),
- after `validate()` with the key missing,
- including value changes by overridden `validate()`/`setValue()` (e.g. `PhoneNumberField`, `IbanNumberField`,
  `ZipCodeField`, `ToggleField`, `FileField`).

Deliverables:

- `docs/form-typed-values/value-types.md`: one table row per field class.
- Characterization tests in `tests/Unit/form/component/field/` that pin the current value behaviour (including the
  `PhoneNumberField` `TypeError` for array input, marked as known bug).
- No production code changes. If a field cannot be tested without global state (`Core`, session), note it.

Verify: table covers every field class; `ddev composer check` green.

Handover notes:

Done (2026-10-04): `docs/form-typed-values/value-types.md` (table per class, normalizing rules, 15 notes with
file:line) and 17 characterization test classes in `tests/Unit/form/component/field/` (`*FieldValueTest.php`, 189 tests
in the suite). No change in `src/`. `ddev composer check` is green without baseline changes.

Findings relevant for the next tasks:

- **Rejected array input keeps the previous value** (no reset to `null`) and the rules still run on it. Typed getters
  must expect the previous value (e.g. the constructor value) after array input.
- **Value types after validation**: single-value fields hold `?string` (posted string or `null`). Exceptions by
  construction: `HiddenField`/`InputField` keep `int|float|bool` from the constructor until the first string input
  (and after rejected array input); `AmountField`/`NumericField` hold `''` instead of `null` after construction.
- **Multiple fields are not always arrays** (Task 5): `SelectOptionsField` (multiple) and `CheckboxOptionsField` keep a
  posted/constructed string as `string`; only `ToggleField` (multiple) wraps. `ToggleField` (multiple) starts with
  `[null]` (null constructor value), `['']` for posted `''`. Single `SelectOptionsField`/`ToggleField` constructed with
  an array value accept arrays. `getValues()` must handle `string`, `array` and `null` entries.
- `isValueEmpty()` uses `array_filter()`: `'0'` entries and `false` count as empty (pinned for `TextAreaField`,
  `HiddenField`).
- Invalid option values are stored (`['x']`, `[['a']]`): `getValues()` must decide what to do with nested arrays
  after a failed validation (`ValidateAgainstOptions` marks them invalid).
- `ZipCodeField` and `PhoneNumberField`: array `countryCode` input throws a `TypeError` (pinned; Task 2 covers the
  phone field, the zip field needs the same fix, which is not mentioned in Task 2 yet).
- `DateField::getValueAsDateTimeImmutable()` throws a `TypeError` for `null` (pinned, Task 3 fixes it).
- `AmountField`: integer fields accept `'1.5'`, `' 12 '` and `'1e3'`; float fields accept `' 1.5 '` and `'1e3'`
  (pinned, Task 2 changes these tests).
- `FormField::setOriginalValue()` does not strip zero-width spaces while `setValue()` does: `valueHasChanged()` is
  `true` right after construction (pinned in `TextFieldValueTest`, not fixed).
- `ValidateAgainstOptions` passes an `int` entry to `FormOptions::exists(string)` under `strict_types` (`TypeError`);
  only reachable via project code, not via posted data. Not tested.

Not testable in isolation: `FileField::validate()` with `overwriteValue = true` and uploads (temp directory from
`$_SERVER['SERVER_NAME']`, file system), `CsrfTokenField::getHtmlTag()` (session token). `FileField` is tested with a
temporary `$_SESSION` (saved and restored in `setUp()`/`tearDown()`), `CsrfTokenField` only before rendering.
`EmailField` is tested with `dnsCheck: false`.

Known-bug tests to update when fixing: `PhoneNumberFieldValueTest` (2x `TypeError`),
`AmountFieldValueTest::testIntegerFieldAcceptsDecimalStringBecauseOfKnownBug` and
`testNumericFieldBehavesLikeIntegerAmountField`, `DateTimeFieldValueTest::
testGetValueAsDateTimeImmutableThrowsTypeErrorForNullBecauseOfKnownBug`, `ZipCodeFieldValueTest::
testArrayAsCountryCodeInputThrowsTypeError`.

### Task 2: Fix input-handling bugs

1. `PhoneNumberField::validate()` calls `trim()` on the input before the array check, so posting `name[]=x` throws a
   `TypeError`. Only trim strings and leave arrays to the normal rejection in `setValue()`. Check the country code input
   (`countryCodeFieldName`) the same way; `ZipCodeField` has the same `TypeError` for an array country code (found in
   task 1), fix it there too.
2. `ValidAmountRule` checks integers with `is_float($value)`, which is always `false` for posted strings, so an integer
   `AmountField`/`NumericField` accepts `"1.5"`. Integer fields must only accept integer values (`int`, or a string
   with optional sign and digits only, after trimming). Decide and document how exponent notation (`"1e3"`) and
   surrounding whitespace are handled for float fields.

Update the characterization tests from task 1 to the fixed behaviour and add tests for the edge cases.

Verify: tests for both fixes; `ddev composer check` green.

Handover notes:

### Task 3: `getValueAsString()` for single-value fields

Add `getValueAsString(): string` to `InputField` (covers all its subclasses, including `HiddenField`, `AmountField`,
`DateField`, `TimeField`, `PhoneNumberField`, `PasswordField`), `TextAreaField`, `RadioOptionsField`,
`SelectOptionsField` and single `ToggleField`.

- `null` → `''`, `string` → as is.
- `int`/`float`/`bool` (possible in `HiddenField`/`InputField` by construction): return the same string `renderValue()`
  would render before encoding. Document this in the PHPDoc.
- `TextAreaField` with an array value: the entries joined with `PHP_EOL`, like `renderValue()` (without encoding).
- Array (multiple `SelectOptionsField`/`ToggleField`, array constructor value): `UnexpectedValueException`.
- Use one shared implementation (e.g. a protected helper in `FormField`) instead of duplicated code.
- Make `DateField::getValueAsDateTimeImmutable()` null-safe: treat `null` like `''`.

Verify: unit tests for every field class (value types from task 1); `ddev composer check` green.

Handover notes:

### Task 4: Numeric getters

- `AmountField` (and so `NumericField`): `getValueAsInt(): ?int` and `getValueAsFloat(): ?float`. `null` when the value
  is empty. `getValueAsInt()` throws for a non-integer value (e.g. `"1.5"`), both throw for non-numeric values (e.g.
  called before successful validation).
- `HiddenField`: `getValueAsInt(): ?int` with the same rules.
- Share the conversion logic (no duplicated parsing).

Verify: unit tests incl. empty, integer, float, negative, whitespace, non-numeric and before-validation cases;
`ddev composer check` green.

Handover notes:

### Task 5: `getValues()` for multi-value fields

Add `getValues(): array` with PHPDoc `list<string>` to `CheckboxOptionsField`, `SelectOptionsField`, `ToggleField`
and `TextAreaField`.

- Single-value variants (not multiple): `[]` for an empty value, otherwise a list with the one value, so callers can
  always use `getValues()` on these classes.
- Non-string entries in the stored array (e.g. nested arrays from manipulated input): `UnexpectedValueException`.
  Check first how `ValidateAgainstOptions` already handles them; do not reject what validation accepts as valid.
- `TextAreaField::getValues()`: one entry per line. An array value is returned as list; a string value is split at
  line breaks (`\R`), each line trimmed, empty lines removed. This makes custom line parsing in subclasses unnecessary.
- Keep `BooleanField::isChecked()` unchanged.

Verify: unit tests incl. empty input, single and multiple values, manipulated input; `ddev composer check` green.

Handover notes:

### Task 6: Documentation and release preparation

- `README.md`: short section "Form field values" listing the typed getters per field type with an example.
- `UPGRADE.md`: new section `[v3.3.0]` (date: release day) with the new getters (recommended migration from
  `getRawValue()`), the two bug fixes (`ValidAmountRule` now rejects decimals in integer fields: this can turn
  previously accepted input into a validation error) and the outlook that `getRawValue()` is removed in v4.
- Run the "Before commit suggestions" checklist from `AGENTS.md` and propose the commit message(s) and tag `v3.3.0`.
- Update the v4 plan with anything learned (e.g. remaining baseline entries in `src/form/`).

Verify: README and UPGRADE match the implemented API; `ddev composer check` green.

Handover notes: