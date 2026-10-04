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