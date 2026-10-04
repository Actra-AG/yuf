# Plan: Form fields with typed values (v4)

Status: planned (2026-10-04). Builds on [docs/form-typed-values/plan.md](../form-typed-values/plan.md) (v3.3.0),
which must be released first.

## Goal

Every form field stores and returns its value with a precise type. `mixed` value storage and `getRawValue()` are
removed, the form code in `src/form/` has no PHPStan baseline entries left, and no feature is lost. Breaking changes
are allowed and documented in `UPGRADE.md` with before/after examples.

## Rules for every task

- Read and follow `AGENTS.md` and `docs/code-quality.md` (full rules, including `final`, enums and fixing all
  baseline entries of touched files).
- The characterization and getter tests from v3.3.0 are the regression base: features they cover must keep working;
  tests only change where the API changes on purpose.
- Every breaking change goes into the `[v4.0.0]` section of `UPGRADE.md` within the same task.
- `ddev composer check` must be green at the end of every task. Check `example/` in the browser after changes to
  rendered HTML. Do not commit.
- Append handover notes to the task below.

## Input from v3.3.0

Findings of [docs/form-typed-values/plan.md](../form-typed-values/plan.md) that the tasks below must take into account.

**The getter semantics of v3.3.0 are the API to keep** (`getValueAsString()`, `getValueAsInt()`, `getValueAsFloat()`,
`getValues()`, `getValueAsDateTimeImmutable()`, `isChecked()`, `HiddenField` `valueIsInt`, `AmountParser`): `null` and
missing keys give `''`/`null`/`[]`, numbers are parsed like `ValidAmountRule` accepts them (whitespace trimmed, no
exponent, no overflow), `getValues()` drops `''`/`null` entries and converts `int` entries, and invalid values throw an
`UnexpectedValueException` naming the field. Their tests are the regression base.

**Remaining PHPStan baseline entries in `src/form/`** (140 in total, all need a signature change or the `mixed` value
storage; count of entries per touched file, verified in `phpstan-baseline.neon`):

| File | Entries | Cause |
|:--|:--|:--|
| `FormField` | 8 | untyped `getRawValue()`, `getOriginalValue()`, `setValue()`, `setOriginalValue()`; array types; `mixed` in `encode()` |
| `TextAreaField` | 4 | array types of the constructor and `cssClassesForRenderer`, `renderValue()` with `mixed` |
| `SelectOptionsField` | 6 | array types (constructor, `cssClasses`, data attributes) |
| `ToggleField` | 20 | untyped `$initialValue`/`setValue()`, `mixed` in `changeValueToArray()` and rendering |
| `PhoneNumberField` | 6 | `validate()` `$inputData`, `mixed` from `getRawValue()` into `Sanitizer`, `PhoneNumber`, `HtmlEncoder` |
| `ZipCodeField` | 1 | `validate()` `$inputData` array type |
| `CheckboxOptionsField` | 1 | array type of `$initialValues` |

`InputField`, `RadioOptionsField`, `DateField`, `AmountField`, `HiddenField` and `ValidAmountRule` have none left.
Untouched files with entries (renderers, rules, `FileField`, collections) belong to tasks 5 to 7.

**Open oddities pinned by the characterization tests** (decide in the design, tests change on purpose):

- A rejected array input keeps the previous value instead of resetting to `null`; the rules still run on it.
- `isValueEmpty()` uses `array_filter()`: `'0'` entries and `false` count as empty. `getValues()` keeps `'0'`, but
  `ValidateAgainstOptions` treats `['0']`, `[false]` and `[[]]` as empty and therefore valid.
- `setOriginalValue()` does not remove zero-width spaces while `setValue()` does, so `valueHasChanged()` is `true`
  right after construction with such a string.
- `ValidateAgainstOptions` raises a `TypeError` for `int` entries (`FormOptions::exists(string)` under `strict_types`),
  reachable only via project code. `getValues()` converts them to strings.
- "Multiple" fields can hold a single string (`SelectOptionsField`, `CheckboxOptionsField`); only `ToggleField` wraps.
  `SelectOptionsField::getValueAsString()` throws for multiple selection by configuration, not by value.
- `ToggleField` (multiple) starts with `[null]` and holds `['']` for posted `''`; `getValues()` maps both to `[]`.
- Single `SelectOptionsField`/`ToggleField` constructed with an array accept arrays.
- The unchanged v3.3.0 field-level decisions: the amount value is stored untrimmed, `HiddenField`/`InputField` keep
  `int|float|bool` constructor values until the first posted string, `PhoneNumberField`/`ZipCodeField` ignore a
  non-string country code.

## Tasks

### Task 1: Design decision

Write `docs/form-v4/design.md` and get it approved by the user before task 2 starts. Decide:

- **Value model per field type:** e.g. single text (`string`), integer (`?int`), decimal (`?float` or string-based
  decimal for amounts), options (`list<string>` / `?string`), boolean (`bool`), date/time (`?DateTimeImmutable`),
  files (list of typed upload objects).
- **Class hierarchy:** separate abstract base classes per value type vs. PHPStan generics (`@template T` on
  `FormField`). Prefer what keeps each class simple and gives PHPStan precise types without casts.
- **Input boundary:** where raw request input (`string|array|null`) is converted to the typed value, and how invalid
  input (wrong type, manipulated arrays) becomes a validation error instead of an exception.
- **Public API:** the v3.3.0 getters (`getValueAsString()`, `getValueAsInt()`, `getValueAsFloat()`, `getValues()`,
  `getValueAsDateTimeImmutable()`, `isChecked()`) stay as they are. Decide whether a generic `getValue()` is added and
  what replaces `getRawValue()`, `getOriginalValue()` and `setValue()`.
- **Text areas with one entry per line:** `TextAreaField` accepts an array (list of lines) today, used by projects
  (e.g. a nameserver field). Decide between a string-only `TextAreaField` with `getValues()` (from v3.3.0) for the
  lines, or a separate field class with a `list<string>` value. The feature must remain; `UPGRADE.md` shows the
  migration of such a subclass.
- **Input normalization:** which fields trim (or otherwise normalize) posted input before storing it. v3.3.0 trims
  amount fields (like email and phone fields already normalize); decide for text fields in general, with explicit
  exceptions (never trim passwords; text areas may need leading whitespace).
- **Numeric field classes:** replace `AmountField(valueIsFloat: ...)` (a boolean flag argument) with separate classes,
  e.g. `IntegerField` (`?int`), `FloatField` (`?float`) and possibly a `DecimalField` for money (string-based decimal
  with bcmath, no float rounding errors). Each class has only the getter that fits its type, so a wrong getter is a
  PHPStan error instead of a runtime exception. `NumericField` (an integer field with its own renderer) is part of
  this decision.
- **Rules:** how `FormRule` implementations get typed values (e.g. rule per value type) instead of `getRawValue()`.
- **Error messages:** the hard-coded German messages (e.g. "Die ungültige Eingabe wurde ignoriert.") — keep, make
  configurable, or translate (may become a separate plan).

Verify: design approved; open questions listed.

Handover notes:

### Task 2: Typed value storage in `FormField` and `InputField` subclasses

Implement the design for `FormField` and all single-value input fields (`InputField` and subclasses, `TextAreaField`).
Narrow constructor parameters as decided in the design (based on `docs/form-typed-values/value-types.md`, including
the `TextAreaField` lines feature), remove `mixed`, make classes `final` unless they are intended extension
points.

Verify: tests updated/extended; no baseline entries left for touched files; `ddev composer check` green.

Handover notes:

### Task 3: Option and multi-value fields

Same for `OptionsField`, `RadioOptionsField`, `SelectOptionsField`, `CheckboxOptionsField`, `BooleanField`,
`ToggleField`. Consider splitting single and multiple variants into separate classes if the design says so.

Verify: as task 2.

Handover notes:

### Task 4: Numeric, date/time and special fields

Same for `AmountField`, `NumericField`, `DateTimeFieldCore`, `DateField`, `TimeField`, `PhoneNumberField`,
`IbanNumberField`, `ZipCodeField`, `HiddenField`, `CsrfTokenField`, `PasswordField`.

Verify: as task 2.

Handover notes:

### Task 5: `FileField`

Typed upload value objects; move `$_SESSION`/`$_SERVER` access out of the field (injected storage), so the logic can be
unit tested.

Verify: as task 2, plus unit tests with an in-memory storage double.

Handover notes:

### Task 6: Form rules and listeners

Adapt `src/form/rule/` and `src/form/listener/` to the typed values; remove all `getRawValue()` usages in `src/`.
Remove `getRawValue()` from `FormField`.

Verify: `grep -rn getRawValue src/` is empty; as task 2.

Handover notes:

### Task 7: Finish `src/form/`

- Remaining baseline entries in `src/form/` (renderers, collections, `FormComponent`) fixed, enums renamed to `*Enum`
  (`InputTypeValue`, `AutoCompleteValue`, layout enums).
- `UPGRADE.md` `[v4.0.0]` form section complete and reviewed: every removed/renamed class, method and argument with a
  before/after example.
- `README.md` updated.

Verify: `phpstan-baseline.neon` has no entries for `src/form/`; `example/` and the yuf skeleton still work.

Handover notes:

## Out of scope (separate plans)

- Template engine (`src/template/`).
- Changes to the rendered form HTML beyond what the typed values require.
- Other areas of the library.