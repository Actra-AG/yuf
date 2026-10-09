# Plan: Typed form field values (v3.3.0)

Status: done (2026-10-04). Follow-up for v4: [docs/plans/done/form-v4/plan.md](../form-v4/plan.md).

## Goal

Form fields get typed value getters, so projects using PHPStan level 10 no longer need casting wrappers like
`ScalarCast::toString($field->getRawValue())`. Two input-handling bugs get fixed.

This is a **minor release without breaking changes**. The new getters are designed as the final API: in v4 they stay,
while `getRawValue()` and the `mixed` value storage go away.

## Rules for every task

- Read and follow `AGENTS.md` and `actra/coding-standard`.
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

- `docs/plans/done/form-typed-values/value-types.md`: one table row per field class.
- Characterization tests in `tests/Unit/form/component/field/` that pin the current value behaviour (including the
  `PhoneNumberField` `TypeError` for array input, marked as known bug).
- No production code changes. If a field cannot be tested without global state (`Core`, session), note it.

Verify: table covers every field class; `ddev composer check` green.

Handover notes:

Done (2026-10-04): `docs/plans/done/form-typed-values/value-types.md` (table per class, normalizing rules, 15 notes with
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

Done (2026-10-04): tests first (`PhoneNumberFieldValueTest`, `ZipCodeFieldValueTest`, `AmountFieldValueTest` updated,
data provider with 19 integer and 13 float cases), then `PhoneNumberField`, `ZipCodeField` and `ValidAmountRule` fixed.
No signature changes. `ddev composer check` green (218 tests).

Changes visible to projects (for UPGRADE.md in Task 6):

- `PhoneNumberField`: array input for the phone value no longer throws a `TypeError`; it is rejected with the normal
  array error (`validate()` returns `false`). Only strings are trimmed. The previous value stays and the rules still run
  on it (it may be normalized by `PhoneNumberRule`, see `value-types.md` note 1).
- `PhoneNumberField` and `ZipCodeField`: a non-string `countryCode` input (array) no longer throws a `TypeError`. It is
  **ignored**, the current country code stays (default `'CH'` or the last valid string). Reason: the input cannot be
  meaningful, the country code is not a user-visible field and an error message would have no field to show it on;
  falling back to the configured country is the safest behaviour. Unknown country code strings are still not validated.
- `ValidAmountRule` (`AmountField`, `NumericField`; `valueIsFloat: false` = integer field):
  - Integer field: accepts an `int` or a string with optional sign and digits only (`'12'`, `'-5'`, `'+5'`, `'007'`).
    **Now rejected** (were accepted): `'1.5'`, `'1.0'`, `'1.'`, `'.5'`, `'1e3'`. A `float` value is rejected.
  - Float field: accepts `int`, `float` and a string `[+-]digits[.digits]`, `'1.'` and `'.5'` (like before).
    **Now rejected** (was accepted): exponent notation (`'1e3'`, `'1.5E-3'`). A `float` typed value is accepted.
  - Both: surrounding whitespace is still accepted (`' 12 '`), whitespace inside and between sign and digits is rejected
    (`'- 5'`; `is_numeric()` rejected it as well). Hex, octal and thousands separators stay rejected. Empty/whitespace-only
    values stay valid (`isValueEmpty()`; required handling is a separate rule).
  - Other value types (e.g. `bool`, array) are rejected.

Decisions:

- Exponent notation is rejected for both field kinds: users do not type it, it is not a plain amount and `'1e3'` would
  make the integer check ambiguous (it is numerically an integer but not "digits only", as the plan requires).
- Review change (before release): `AmountField::validate()` now trims posted string input before storing it, so the
  stored value is clean (`' 12 '` → `'12'`); the getters still trim (values from constructor/`setValue()`). The note
  below describes the original Task 2 decision.
- Whitespace is accepted but the stored value is **not trimmed**. Reason: no amount rule changes the value today (only
  email/date/time/phone rules normalize), and trimming would be an additional visible change (`getRawValue()` result,
  re-rendered input). Accepted whitespace is the existing behaviour (`is_numeric()` allows surrounding whitespace), so the
  fix only removes what was never meant to be valid. Consequence for Task 4: `getValueAsInt()`/`getValueAsFloat()` must
  `trim()` the stored string before converting (`' 12 '` is a valid stored value). The trim characters are
  `" \t\n\r\v\f"` (same as `is_numeric()`, not the NUL byte of `trim()`'s default).
- Pattern check instead of `is_numeric()`: the numeric string stays a plain decimal, so Task 4 can share the same
  regexes/idea; `(int)`-conversion of a digits-only string can still overflow for huge values (`PHP_INT_MAX`), Task 4
  must handle that (throw instead of silently saturating).

Baseline: 3 entries removed (`trim` mixed in `PhoneNumberField`, `$countryCode` mixed in `PhoneNumberField` and
`ZipCodeField`), none added (`git diff phpstan-baseline.neon` only has deletions). Remaining entries in touched files,
all needing a signature change or the `mixed` value storage (v4): `PhoneNumberField::validate()` `$inputData` array
type, `Sanitizer::trimmedString()`, `PhoneNumber::createFromString()` and `HtmlEncoder::encode()` with `mixed` from
`getRawValue()`; `ZipCodeField::validate()` `$inputData` array type. `ValidAmountRule` has no entries left.

Relevant for the next tasks: the characterization tests for amounts now pin that the stored value is the posted
string (also with whitespace). `PhoneNumberField::renderValue()` still returns `mixed` from `getRawValue()` (baseline).

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

Done (2026-10-04): tests first, then the getters. No signature changes, no `final`, nothing renamed. `ddev composer check`
green (282 tests).

New public API (final API, survives into v4): `getValueAsString(): string` on `InputField` (all subclasses),
`TextAreaField`, `RadioOptionsField`, `SelectOptionsField` and `ToggleField`. Shared implementation:
`protected FormField::getValueAsStringOrFail(): string` (reads the private value, fully typed, no `mixed` in the
signature). The public methods are one-line delegations; `TextAreaField` handles arrays first and delegates the rest.

Exact semantics per stored value type:

- `null` -> `''`; `string` -> unchanged (no trimming, no encoding; zero-width spaces were already removed by `setValue()`).
- `int`/`float`/`bool` (only `HiddenField`/`InputField` by construction): `(string)` cast, the same as `renderValue()`
  does before encoding via `HtmlEncoder::encode()`: `5` -> `'5'`, `1.5` -> `'1.5'`, `2.0` -> `'2'`, `true` -> `'1'`,
  `false` -> `''`. Pinned in `HiddenFieldValueTest` (also compared with `renderValue()`).
- `TextAreaField`, array: entries joined with `PHP_EOL` (like `renderValue()`, but unencoded); `[]` -> `''`. An entry that
  is not a string (nested array, also after manipulated input into an array field) -> `UnexpectedValueException`
  (`'The value of field <name> cannot be read as string, it contains an entry of type <type>.'`).
- Array in any other field (multiple `SelectOptionsField`/`ToggleField`, single ones constructed with an array, multiple
  fields after any validation) and any other type (object): `UnexpectedValueException`
  (`'The value of field <name> cannot be read as string, it is of type <type>.'`, type via `get_debug_type()`).
- After `validate()`: key present -> the posted string; key missing -> `''` (stored `null`; a multiple field holds `[]` and
  throws); rejected array input -> the **previous** value, converted by the same rules (e.g. constructor value `'a'`
  gives `'a'`, `HiddenField` `5` gives `'5'`).
- `PhoneNumberField`, `DateField`, `TimeField`, `EmailField`: the stored value, **not** the rendered format (the phone
  field stores the normalized `+41.446681800` once valid; an invalid number is stored trimmed).
- `AmountField`/`NumericField`: the stored string, untrimmed (`' 1.5 '` stays; Task 4 must trim for numbers).
- `DateField::getValueAsDateTimeImmutable()` now uses `getValueAsString()`: `null` and `''` -> `null`. The known-bug test
  was changed to the fixed behaviour (`null` after construction and after validation with a missing key).

Decisions:

- Not on `FormField` itself: `CheckboxOptionsField`/`BooleanField` (always arrays) and `FileField` (array of
  `FileDataModel`) would get a method that can never succeed. Only the classes in the plan have it, so the API says where
  a single string exists. `CsrfTokenField` and `PasswordField` inherit it from `HiddenField`/`InputField`.
- Helper name `getValueAsStringOrFail()` is protected on `FormField` so Task 4/5 and projects can reuse it; it reads
  `$this->value` directly (not `getRawValue()`), so a subclass overriding `getRawValue()` is not consulted. `TextAreaField`
  uses `getRawValue()` for the array case, like its `renderValue()`.
- No `TextAreaField` array trimming/filtering: the getter reports what is stored; line parsing is Task 5 (`getValues()`).
- `ToggleField::getValueAsString()` throws for `multiple: true` always (the value is always an array).
- Review addition: `SelectOptionsField::getValueAsString()` also throws always for `acceptMultipleSelections: true`,
  even if the multiple field holds a single string (constructor or posted string). The result depends on the field
  configuration, not on the current value; multiple fields use `getValues()` (Task 5), which must wrap such a string.

For UPGRADE.md / README.md (Task 6):

- New getters per class as above; recommended migration: `ScalarCast::toString($field->getRawValue())` ->
  `$field->getValueAsString()`. Note: `null` and missing keys give `''`; `getRawValue()` keeps returning `null`.
- Potential conflict (unavoidable, acceptable): project subclasses that already declare a method `getValueAsString()` with a
  different signature (e.g. `: ?string` or other parameters) become fatal-error incompatible with the new parent method.
  Rename them or adjust the signature to `getValueAsString(): string`.
- `DateField::getValueAsDateTimeImmutable()` no longer throws a `TypeError` for an empty field that holds `null`.

For Tasks 4/5: reuse `FormField::getValueAsStringOrFail()` in `HiddenField::getValueAsInt()` and `AmountField` (parse the
string, `trim()` it first) and take the same exception style (field name + `get_debug_type()`). Extending to `getValues()`:
the stored-value rules for rejected array input (previous value) apply the same way.

Baseline: only shrank (1 entry removed: `DateTimeImmutable` constructor with `mixed` in `DateField`, `git diff` shows only
6 deleted lines). New code has no entries. Remaining entries in touched files need signature changes or the `mixed`
value storage (v4): `FormField` (`getRawValue`, `getOriginalValue`, `setValue`, `setOriginalValue`, `validate()` `$inputData`,
`getAddedValues`/`getRemovedValues` array types, `renderValue` `mixed`), `TextAreaField` (array PHPDoc types of the constructor
and `cssClassesForRenderer`, `renderValue` `mixed`), `SelectOptionsField` (array types), `ToggleField` (untyped
`$initialValue`/`setValue`, `mixed` handling in rendering). `InputField`, `RadioOptionsField` and `DateField` have none left.

### Task 4: Numeric getters

- `AmountField` (and so `NumericField`): `getValueAsInt(): ?int` and `getValueAsFloat(): ?float`. `null` when the value
  is empty. `getValueAsInt()` throws for a non-integer value (e.g. `"1.5"`), both throw for non-numeric values (e.g.
  called before successful validation).
- `HiddenField`: `getValueAsInt(): ?int` with the same rules.
- Share the conversion logic (no duplicated parsing).

Verify: unit tests incl. empty, integer, float, negative, whitespace, non-numeric and before-validation cases;
`ddev composer check` green.

Handover notes:

Done (2026-10-04): tests first, then code. No signature changes, no `final` on existing classes, nothing renamed.
`ddev composer check` green (416 tests).

New public API (final API, survives into v4):

- `AmountField` (so `NumericField`): `getValueAsInt(): ?int`, `getValueAsFloat(): ?float`.
- `HiddenField`: `getValueAsInt(): ?int`. (`CsrfTokenField` inherits it, which is meaningless but harmless.)
- New `final class actra\yuf\form\AmountParser` (pure, static): `isInteger(string)`, `isDecimal(string)`,
  `toInt(string): ?int`, `toFloat(string): ?float`. It is the single place that defines the accepted number formats;
  `ValidAmountRule` uses it too (behaviour unchanged, its tests are unchanged and green). Unit tests:
  `tests/Unit/form/AmountParserTest.php`.
- Shared implementation: `protected FormField::getValueAsIntOrFail(): ?int` and `getValueAsFloatOrFail(): ?float`
  (read the private value directly, like `getValueAsStringOrFail()`).

Exact semantics:

- Accepted formats = `ValidAmountRule`: integer = optional sign + digits; decimal additionally `1.5`, `1.`, `.5`; surrounding
  whitespace `" \t\n\r\v\f"` ignored; no exponent, hex, thousands separators, inner whitespace.
- Empty -> `null`: stored `null`, or a string for which `isValueEmpty()` is true (`''`, whitespace-only).
- `getValueAsInt()`: string -> `AmountParser::toInt()` (`'+5'` -> 5, `'007'` -> 7, `' 12 '` -> 12, `'-0'` -> 0); stored `int` -> as is.
  Throws `UnexpectedValueException` for decimal/exponent/text strings, for integer strings outside
  `PHP_INT_MIN..PHP_INT_MAX` (message says "out of the integer range", no saturation, no float), for a stored `float`
  (even `2.0`), `bool`, array and other types.
- `getValueAsFloat()`: string -> `AmountParser::toFloat()` (`'5'` -> 5.0, `'.5'` -> 0.5); stored `int` -> `(float)`; finite
  `float` as is. Throws for text, exponent, strings too large for a finite float (`INF`), non-finite floats, `bool`,
  arrays, other types. Integers above `PHP_INT_MAX` as string are fine for `getValueAsFloat()`.
- Messages: `The value of field <name> cannot be read as integer|float, ...` with the problem and (for strings) the value,
  shortened to 40 characters; for non-string types `it is of type <get_debug_type>`.
- After `validate()`: key present -> parsed posted string; key missing -> `null`; rejected array input -> the **previous**
  value is parsed (e.g. constructor `5` -> `5`); before validation -> the constructor value (`AmountField`: `''` -> `null`,
  `5` -> `5`, `1.5` stored as `'1.5'`). After a failed validation (e.g. `'abc'`, `'1e3'`) the getters throw because the
  invalid string is stored.

Decisions:

- `HiddenField::getValueAsInt()` uses the same shared parsing (sign, leading zeros, whitespace accepted): one rule for
  all numeric getters, and posted hidden values may come from forms of the project that format numbers like that.
- A `bool` value (`HiddenField` constructed with `true`/`false`) always throws, also `false` although `isValueEmpty()` is
  true for it: a bool is not a number and silently becoming `null` would hide a programming error.
- Review change: `ValidAmountRule` now rejects values out of range (`AmountParser::toInt()`/`toFloat()` return `null`):
  integer strings outside `PHP_INT_MIN..PHP_INT_MAX`, float strings too large for a finite `float` and non-finite
  `float` values. Otherwise a user could post `99999999999999999999`, pass validation and trigger an exception in
  `getValueAsInt()` (HTTP 500). Like the Task 2 fixes, this is a bug fix with a visible change (for UPGRADE.md): such
  input is now a validation error. The numeric getters never throw after a successful validation.
- Review addition: `HiddenField` got an optional constructor parameter `valueIsInt` (default `false`). With `true`, a
  `ValidAmountRule` (integer) is added, so manipulated hidden input (e.g. `id=abc`) becomes a validation error instead
  of an exception in `getValueAsInt()`. Recommended for hidden IDs (README.md/UPGRADE.md, Task 6).- Helpers are protected on `FormField` (not on `InputField`/`AmountField`) next to `getValueAsStringOrFail()` so Task 5 and
  project subclasses can reuse them; helper and parser are not tied to a field type.
- Parser lives in `actra\yuf\form` (not `datacheck`), next to the rule/field classes that use it; `datacheck` is for
  generic validators/sanitizers.
- `toInt()` detects overflow by comparing `(string)(int)$value` with the normalized digits (PHP saturates the cast).

For UPGRADE.md / README.md (Task 6):

- New getters `AmountField::getValueAsInt()/getValueAsFloat()`, `HiddenField::getValueAsInt()`; migration
  `(int)$field->getRawValue()` / `ScalarCast::toInt(...)` -> `$field->getValueAsInt()` (null for empty).
- Potential conflict: project subclasses of `AmountField`/`NumericField`/`HiddenField` that already declare
  `getValueAsInt()` or `getValueAsFloat()` with a different signature (e.g. `: int` instead of `: ?int`) become fatal-error
  incompatible; rename them or use `: ?int` / `: ?float`. Same for subclasses declaring `getValueAsIntOrFail()`,
  `getValueAsFloatOrFail()` (new protected methods on `FormField`, private helpers `createNumericTypeException()` and
  `describeValueForException()` are private and cannot conflict).
- Overflow/non-integer values throw instead of being cast silently; call the getters after successful validation.

Baseline: unchanged (`git diff phpstan-baseline.neon` empty; PHPStan reported no fixed entries, `AmountField`,
`HiddenField` and `ValidAmountRule` had none). New code has no entries.

For Task 5: nothing numeric. The exception style (field name + `get_debug_type()`) and the "previous value after rejected
array input" rule apply the same way.

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

Done (2026-10-04): tests first, then code. No signature changes, no `final`, nothing renamed. `ddev composer check`
green (533 tests).

New public API (final API, survives into v4): `getValues(): array` (PHPDoc `list<string>`) on `CheckboxOptionsField`
(so `BooleanField`), `SelectOptionsField`, `ToggleField` and `TextAreaField`. Shared implementation:
`protected FormField::getValuesAsStringListOrFail(): array` (reads the private value, like the other `*OrFail()` helpers);
the three option fields delegate in one line. `TextAreaField::getValues()` has its own line parsing on top of
`getValueAsString()`. `BooleanField::isChecked()` is unchanged.

Exact semantics, option fields (`CheckboxOptionsField`, `BooleanField`, `SelectOptionsField`, `ToggleField`; single and
multiple variants behave the same, the result depends only on the stored value):

- `null` and `''` -> `[]`. Any other string -> one-entry list (`'a'` -> `['a']`), also for multiple fields that hold a
  string (Task 1 oddity). Whitespace-only strings and `'0'` are kept (they can be option keys).
- Array -> entries in stored order, re-indexed (`list`), duplicates kept. Entry `string` kept unless `''`; `int` ->
  `(string)`; `null`, `false`, `0.0`, `[]` dropped; anything else (nested non-empty array, `true`, non-zero `float`,
  object) -> `UnexpectedValueException`
  (`The value of field <name> cannot be read as list of strings, it contains an entry of type <type>.`). Any other stored
  type (object) -> same exception with `it is of type <type>`.
- Values are NOT filtered against the options: the getter reports what is stored; unknown options after a failed
  validation are returned (`['a', 'x']`). Validation is responsible.
- `ToggleField` multiple: stored `[null]` (null constructor value) -> `[]`; stored `['']` (posted `''`) -> `[]`.
- After rejected array input of a single field: the previous value (e.g. constructor `'a'` -> `['a']`). Key missing -> `[]`.

`TextAreaField::getValues()`:

- Uses `getValueAsString()` (so `null` -> `''`, array entries joined with `PHP_EOL`, non-string array entry or other type ->
  `UnexpectedValueException`), then splits at CRLF, LF or CR, trims every line (`trim()` default characters), drops
  empty lines. `'0'` lines are kept. Array values go through the same code, so entries are trimmed/empty-filtered and an
  entry containing line breaks is split: `['a ', '', "b\nc"]` and `"a\n\nb\nc"` give the same `['a', 'b', 'c']`.
- Split pattern `/\r\n|\n|\r/` without `/u` (review: replaced `/(*BSR_ANYCRLF)\R/`, same behaviour, IDE-friendly).
  Plain `\R` without `/u` would also split at the byte `0x85` inside multibyte characters (e.g. `Å`), `/u` would fail
  on invalid UTF-8. Unicode line separators (U+2028 etc.) are not split; browsers post CRLF.

Decisions and reasons:

- Dropped entries: `''`/`null` mean "nothing selected" (the empty select option, `[null]` of `ToggleField`, `isValueEmpty()`),
  so callers get an empty list instead of `['']`. `false`, `0.0`, `[]` are dropped too only because
  `ValidateAgainstOptions` treats an array that consists only of falsy entries as empty and therefore **valid**
  (`isValueEmpty()` uses `array_filter()`): `[false]`, `[[]]` pass validation, so `getValues()` must not throw for them.
  `'0'` is NOT dropped even though `['0']` is "empty" for validation (valid whatever the options are): `'0'` is a
  legitimate option key and the getter reports the stored value. So `['0']` -> `['0']`.
- `int` entries are converted, not rejected: renderers compare keys loosely (`in_array($key, $rawValue)` in
  `DefaultOptionsRenderer`, `ToggleField`, `SelectOptionsRenderer`; `(string)` casts in the single variants) and PHP array
  keys of `FormOptions` can be ints (`'1'` becomes `1`), so `1` and `'1'` are the same selection. Ints only come from project
  code; `ValidateAgainstOptions` itself would raise a `TypeError` for them (`exists(string)` under `strict_types`, see Task 1),
  unchanged and not part of this task.
- After a successful validation `getValues()` never throws (data provider test per class): validation accepts only scalar
  entries (strings from posted data) or an array of falsy entries, all of which are handled. After a failed validation it
  throws for nested arrays (pinned). Not covered: an empty `ArrayObject` stored by project code (`isValueEmpty()` accepts it,
  `getValues()` throws), considered not worth a special case.
- Helper in `FormField`, not `OptionsField`: it must read the private `$value` without `getRawValue()` (which calls
  `isValueEmpty()` first and throws for objects), like the Task 3/4 helpers. Public `getValues()` is not on `OptionsField`
  because `RadioOptionsField` is a single-value field with `getValueAsString()` and is not part of the plan.
- Single fields hold one value but get the same list API on purpose (plan), so code can treat select/toggle fields uniformly.

For UPGRADE.md / README.md (Task 6):

- New getters per class as above; recommended migration for multi-value fields: `(array)$field->getRawValue()` /
  project helper casts -> `$field->getValues()`; for text areas: custom line splitting in `validate()` overrides ->
  `$field->getValues()` (CRLF-safe, trimmed, no empty lines). `getRawValue()` keeps its behaviour.
- Potential conflict (unavoidable, acceptable): project subclasses of `CheckboxOptionsField`, `BooleanField`,
  `SelectOptionsField`, `ToggleField` or `TextAreaField` that already declare `getValues()` with another signature (other
  parameters or return type) become fatal-error incompatible with the new method; rename them or use `getValues(): array`
  (a return type `array` is compatible, `list<string>` is only PHPDoc). Same for subclasses declaring the new protected
  method `getValuesAsStringListOrFail()` on `FormField`.
- Document in the README: values are not checked against the options (call after successful validation; the getter
  throws for manipulated nested arrays after a failed validation), `''`/`null` entries are dropped, ints become strings.

Baseline: unchanged (`ddev composer phpstan:baseline` regenerated an identical file, `git diff phpstan-baseline.neon` is
empty; no fixed entries, no new ones). Remaining entries of the touched files need signature changes or the `mixed` value
storage (v4), see Task 3.

### Task 6: Documentation and release preparation

- `README.md`: short section "Form field values" listing the typed getters per field type with an example.
- `UPGRADE.md`: new section `[v3.3.0]` (date: release day) with the new getters (recommended migration from
  `getRawValue()`), the two bug fixes (`ValidAmountRule` now rejects decimals in integer fields: this can turn
  previously accepted input into a validation error) and the outlook that `getRawValue()` is removed in v4.
- Run the "Before commit suggestions" checklist from `AGENTS.md` and propose the commit message(s) and tag `v3.3.0`.
- Update the v4 plan with anything learned (e.g. remaining baseline entries in `src/form/`).

Verify: README and UPGRADE match the implemented API; `ddev composer check` green.

Handover notes:

Done (2026-10-04): documentation only, no change in `src/` or `tests/`.

- `README.md`: new section "Form Field Values" (before "Documentation"): getters per field type, example with named
  arguments, `HiddenField(..., valueIsInt: true)` recommendation, hint to call after validation
  (`UnexpectedValueException`).
- `UPGRADE.md`: new topmost section `[v3.3.0] – unreleased` (the date is set on release): new getters with before/after
  (also `TextAreaField::getValues()` for subclasses that split lines), `valueIsInt`, `AmountParser`, fixes (⚠️ for the
  stricter amount rule: integer fields reject decimals/exponent, out-of-range values rejected; phone/zip `TypeError`;
  `DateField` null-safe), ⚠️ possible method name conflicts, outlook for v4.
- `docs/plans/done/form-v4/plan.md`: new section "Input from v3.3.0" (API to keep, remaining baseline entries per file, open
  oddities).
- Verified against the code (`git diff v3.2.2..HEAD -- src/`): all method names, signatures and the `HiddenField`
  parameter match the notes of Tasks 1 to 5. Baseline entry counts per touched file taken from `phpstan-baseline.neon`.
- Release: next tag `v3.3.0` (new features, no breaking API change; the stricter amount validation is documented as a
  fix with ⚠️). Set the date in `UPGRADE.md` on release.

## Final note (2026-10-09)

The plan moved to `docs/plans/done/` (coding standard v1.16.0).
