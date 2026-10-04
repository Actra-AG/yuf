# Value types of form fields (v3.2.2)

Analysis for [plan.md](plan.md), Task 1. Based on the code of `src/form/component/FormField.php` and every class in
`src/form/component/field/`. Behaviour is pinned by the characterization tests in
`tests/Unit/form/component/field/`.

## How values get into a field

`FormField` stores one `private mixed $value`. `getRawValue()` returns it unchanged (or `null` if it is empty and
`$returnNullIfEmpty` is set). There is no type conversion anywhere.

- **Construction:** `FormField::__construct()` calls `setValue()` and `setOriginalValue()` with the constructor value
  (`FormField.php:62-66`). An array constructor value enables array input for this field (`FormField.php:62`).
- **`setValue()`** (`FormField.php:75-98`): an array is rejected with an error (and the value is **left unchanged**)
  unless array values are allowed. Strings lose zero-width spaces (U+200B, `FormField.php:89`); nothing else is
  changed (no trimming).
- **`validate()`** (`FormField.php:223-232`): sets the value from `$inputData[$name]` if the key exists, otherwise to
  `null` (`[]` if array values are allowed). Then the rules run on the **current** value, even if `setValue()` rejected
  the input. Rules can change the value again via `setValue()` (see "Normalizing rules").
- Hence after `validate()` the value is a posted `string`, `null` (key missing), `[]` or an array (only for array
  fields), or the unchanged previous value (rejected array input).

## Table

Legend: "ctor" = value after construction. "string in" = after `validate()` with `$inputData[$name]` being a string
(validation result in brackets where it matters). "array in" = `$inputData[$name]` is an array. "missing" = key not in
`$inputData`. "kept" = array is rejected with an error and the previous value stays (the rules still run on it).
`getRawValue()` returns the stored value in every row (no transformation), except where noted.

| Class | ctor | string in | array in | missing |
|:--|:--|:--|:--|:--|
| `FormField` (abstract base) | any (`mixed`); array enables array input | `string` without U+200B | kept, error (array input only if enabled: then `array`) | `null` (`[]` if array input enabled) |
| `InputField` (abstract) | `int\|float\|string\|bool\|null`, unchanged | `string` | kept, error | `null` |
| `TextField` | `?string` | `string`, untrimmed | kept, error | `null` |
| `EmailField` | `?string` | valid: `string`, trimmed and lower-cased by `ValidEmailAddressRule`; invalid: the posted `string` | kept, error | `null` |
| `ZipCodeField` | `?string` | `string`, untrimmed (rule accepts surrounding whitespace) | kept, error; array for `countryCode` input: `TypeError` | `null` |
| `IbanNumberField` | `?string` | `string`, not normalized (case, spaces kept) | kept, error | `null` |
| `PasswordField` | `string` (always `''`) | `string`, untrimmed | kept (`''`), error | `null` (required rule fails) |
| `HiddenField` | `int\|float\|string\|bool\|null`, **type kept** | `string` | kept (typed ctor value stays), error | `null` |
| `CsrfTokenField` (final, extends `HiddenField`) | `null`; `getHtmlTag()` sets the session token (`string`) | `string` | kept, error | `null` |
| `AmountField` | `null\|int\|float` cast to `string`: `null` becomes `''`, `5` becomes `'5'` | `string` as posted (`' 1.5 '`, `'1e3'` accepted and kept) | kept (`string`), error | `null` |
| `NumericField` (extends `AmountField`) | as `AmountField` | as `AmountField`, integer rule broken (see notes) | kept (`string`), error | `null` |
| `DateTimeFieldCore` (abstract) | `?string` | see `DateField` / `TimeField` | kept, error | `null` |
| `DateField` | `?string` | valid: ISO `Y-m-d` `string` (`'3.2.2020'` becomes `'2020-02-03'`); invalid: the posted `string` | kept, error | `null` |
| `TimeField` | `?string` | valid: `H:i:s` `string` (`'08:05'` becomes `'08:05:00'`); invalid: the posted `string` | kept (normalized again by the rule if valid), error | `null` |
| `PhoneNumberField` | `?string`, not formatted | valid: trimmed input, then internal format `string` (`'+41.446681800'`); invalid: the **trimmed** posted `string` | **`TypeError`** from `trim()` (known bug, Task 2); same for array `countryCode` input | `null` |
| `TextAreaField` | `null\|string\|array`; array enables array input | `string` without U+200B, untrimmed | string field: kept, error. Array field (ctor array): `array`, entries untouched | `null` (string field), `[]` (array field) |
| `OptionsField` (abstract) | `mixed` | see subclasses | see subclasses | see subclasses |
| `RadioOptionsField` | `?string` | `string` (unknown option stored, invalid) | kept, error | `null` (required rule always fails) |
| `SelectOptionsField` (single) | `null\|string\|array`; **array enables array input** | `string` (unknown option stored, invalid) | kept, error; with array ctor value: `array` | `null` (`[]` with array ctor value) |
| `SelectOptionsField` (multiple) | `null\|string\|array`, **not wrapped** (a string stays a `string`) | `string` (**not wrapped**) | `array` as posted (unknown options / nested arrays stored, invalid) | `[]` |
| `CheckboxOptionsField` | `array` | `string` (**not wrapped**, accepted) | `array` as posted (unknown options / nested arrays stored, invalid) | `[]` |
| `BooleanField` (extends `CheckboxOptionsField`) | `['checked']` or `[]` | `string` (`isChecked()` is `false`) | `array` as posted | `[]` |
| `ToggleField` (single) | untyped; stored as given (`null`, `string`, `array`) | `string` | kept, error; with array ctor value: `array` | `null` (`[]` with array ctor value) |
| `ToggleField` (multiple) | always `array`: `null` becomes `[null]`, `'a'` becomes `['a']` | `array` with the string (`'a'` becomes `['a']`, `''` becomes `['']`) | `array` as posted | `[]` |
| `FileField` | `[]` (`array<string, FileDataModel>`, filled from `$_SESSION`) | `[]` unchanged (anything but an upload structure is ignored) | `[]` unless it has the `$_FILES` structure (`name`, `tmp_name`, `type`, `error`, `size`); then an `array<string, FileDataModel>` | `[]` |
| `NullField` | not a field (`extends FormComponent`), has no value | | | |

`validate()` with `$overwriteValue = false` never touches the value (rules still run).

### Normalizing rules (they call `setValue()` while validating)

| Rule | Field | Effect on the stored value |
|:--|:--|:--|
| `ValidEmailAddressRule` | `EmailField` | trimmed, lower-cased `string` (valid addresses only) |
| `ValidDateRule` | `DateField` | `Y-m-d` `string` (valid dates only) |
| `ValidTimeRule` | `TimeField` | `H:i:s` `string` (valid times only) |
| `PhoneNumberRule` | `PhoneNumberField` | internal format `string` (valid numbers only) |

`ZipCodeField`, `IbanNumberField` and `AmountField` do not change the value; they only validate.

## Notes

1. **Rejected array input keeps the old value.** `setValue()` returns early after adding the error
   (`FormField.php:75-85`), so the value is not reset to `null`. This is why the "array in" column says "kept". The
   rules still run on the kept value (e.g. a kept `'08:00'` in a `TimeField` becomes `'08:00:00'`).
2. **`TextAreaField` arrays are a feature.** An array constructor value enables arrays for the field
   (`FormField.php:62`). The entries are returned as is (no zero-width space removal, no type check); `renderValue()`
   joins them with `PHP_EOL` after encoding each entry (`TextAreaField.php:63-79`). Without an array constructor value,
   array input is rejected like in any other field, even if a subclass wants to store arrays later: it can only do so
   by calling `acceptArrayAsValue()` (protected) itself.
3. **Array values in "single" fields.** `SelectOptionsField` and `ToggleField` accept arrays whenever the
   constructor value is an array, even without `acceptMultipleSelections`/`multiple`
   (`FormField.php:62`; `SelectOptionsField.php:57-60` only adds the multiple case).
4. **Multiple fields do not always hold arrays.** `SelectOptionsField` (multiple) and `CheckboxOptionsField` keep a
   posted string as `string` and a string constructor value as `string`. Only `ToggleField` (multiple) wraps scalars
   (`ToggleField.php:59-65`, `:290-295`). `BooleanField::isChecked()` compares `=== ['checked']`
   (`BooleanField.php:41`), so a posted string `'checked'` is valid but not "checked".
5. **`ToggleField` (multiple) starts with `[null]`** for a `null` constructor value (`ToggleField.php:59-64`), not
   `[]`. `isValueEmpty()` treats it as empty. The `[null]` / `['']` entries break a future `list<string>` assumption.
6. **`isValueEmpty()` uses `array_filter()`** (`FormField.php:127`), so an array with only `''`, `'0'` or `null` entries
   is empty, and `false` (hidden field) is empty. `isValueEmpty()` throws an `UnexpectedValueException` for any other
   type (e.g. a nested object).
7. **Original value is not cleaned.** `setOriginalValue()` stores the constructor value untouched (`FormField.php:66`),
   while `setValue()` removes zero-width spaces, so `valueHasChanged()` is `true` right after construction with such a
   string. `AmountField` stores `(string)$initialValue` (`AmountField.php:31`) for both.
8. **Option validation has no type check.** `ValidateAgainstOptions` accepts scalars and arrays of scalars and rejects
   nested arrays (`ValidateAgainstOptions.php:37-50`); with `strict_types` an `int` entry would raise a `TypeError` in
   `FormOptions::exists(string $key)`. Posted data is always strings, so only values set by project code can hit this.
   The invalid values are still stored (see table), so `getRawValue()` can return unvalidated data after a failed
   validation (`['x']`, `[['a']]`, `'x'`).
9. **`ValidAmountRule` ignores the integer setting for posted strings** (`ValidAmountRule.php:37`, `is_float()` is never
   true for a string), so `NumericField` and integer `AmountField` accept `'1.5'`, `'1e3'` and `' 12 '`
   (`is_numeric()` semantics). Whitespace and exponent notation are kept in the stored string.
10. **`PhoneNumberField::validate()` trims before the array check** (`PhoneNumberField.php:63-67`): `TypeError` for
    array input. The country code input is assigned unchecked to `private(set) string $countryCode`
    (`PhoneNumberField.php:56-61`, `ZipCodeField.php:46-48`): array input raises a `TypeError` in both fields. The country
    code string is not validated against known codes either (unknown codes: `ZipCodeRule` accepts everything).
11. **`DateField::getValueAsDateTimeImmutable()`** (`DateField.php:42-46`) handles `''` but not `null`: a `DateField`
    that was never filled or validated with a missing key throws a `TypeError` (`new DateTimeImmutable(null)`).
12. **`PasswordField`** is constructed with `''` (not `null`); the posted value is stored untrimmed.
13. **`CsrfTokenField::getHtmlTag()`** overwrites the value with the session token on every render
    (`CsrfTokenField.php:21-26`), so the value after rendering is always a `string`.
14. **`FileField`** always stores an array of `FileDataModel` (keyed by sha1 of the temp name). The value is read from
    `$_SESSION[$uniqueSessFileStorePointer]` in every `setValue()` (`FileField.php:190-207`), so it is not derived
    from `$inputData` at all except for the `$_FILES` structure. The posted `string`/`null` is ignored. Typed
    getters are out of scope.
15. **Strings in arrays are not cleaned**: `setValue()` only removes zero-width spaces from top-level strings.

## Not covered by tests

- `FileField::validate()` with `overwriteValue = true` and any real upload (temp directory from
  `$_SERVER['SERVER_NAME']`, `unlink`, `mkdir`, `$_FILES` handling). Only construction, `setValue()` with
  non-upload data and `validate(overwriteValue: false)` are tested (with a temporary `$_SESSION`).
- `CsrfTokenField::getHtmlTag()` (session token, random).
- `EmailField` with DNS check (`dnsCheck: false` in tests).
- Rendering (`renderValue()` is only covered for `TextField` and `TextAreaField`).
- `NullField` (not a field, no value).