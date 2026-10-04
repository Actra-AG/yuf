# Plan: Form fields with typed values (v4)

Status: planned, design approved (2026-10-04), refined in review. Builds on
[docs/form-typed-values/plan.md](../form-typed-values/plan.md) (v3.3.0), which must be released first.

## Goal

Every form field stores and returns its value with a precise type. `mixed` value storage and `getRawValue()` are
removed, the form code in `src/form/` has no PHPStan baseline entries left, and no feature is lost. Breaking changes
are allowed and documented in `UPGRADE.md` with before/after examples.

## Rules for every task

- Work on the branch `v4`. v3 patches are made on `main` and then merged into `v4`; `v4` is merged into `main` for
  the v4.0.0 release.
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

Done (2026-10-04): [design.md](design.md) written, waiting for the user's approval (15 open questions with
recommended answers in section 7). No code changed.

Key recommendations: abstract bases per value family instead of generics (`FormField` holds no value; `TextualField` >
`InputField`/`TextAreaField`; `SingleOptionsField`/`MultiOptionsField`; `BooleanField`, `FileField`); no generic
`getValue()`, `getRawValue()`/`getOriginalValue()`/`setValue()` removed; `FormInput` narrows request data once,
invalid input resets the value and gives one error without running rules; `AmountField` becomes `IntegerField`,
`FloatField` and bcmath `DecimalField(scale:)`; `TextAreaField` stays string-only with `getValues()` plus
`addEachRule()`; typed rule bases per value type, pure rules, checks moved into the fields; `FormMessages` for the
German texts; `Multi*` option classes; `FileUploadStorage` and `CsrfTokenSource` as injected storage.

Proposed task split changes (accepted by the user and applied to tasks 2 to 7 below): FormField's `mixed` storage cannot
be removed per family while tasks must stay green, so tasks 2 to 5 use a temporary `@internal` bridge (new bases
override the legacy `getRawValue()`/`setValue()`/`validate(array)`), removed in task 6; `FormInput`/`FormMessages` move
into task 2; the options renderers belong to task 3; task 4 is split in 4a (numbers, date) and 4b (phone, zip, iban,
hidden, csrf); typed rule bases and the `validate(FormInput)` switch belong to task 6; task 7 keeps enums, collections,
remaining entries.

Findings that differ from or add to the notes above: `MinValueRule`/`MaxValueRule`/`ValueBetweenRule` throw for every
posted (string) value and have no tests; `PasswordField` is rendered back with the posted password; `ToggleField`
children never get `topFormComponent`; `InputFieldRenderer` accepts `OptionsField` but reads `inputType`; baseline count
(140 entries in 119 blocks) matches the table; no template tag depends on the field value API.

Task 1 finished (2026-10-04): the user answered all 15 open questions, the decisions are applied in
[design.md](design.md) (section 7 "Decisions", status "approved", no open question left). Changes to the recommendations
that tasks 2 to 7 must follow: typed setters instead of removing `setValue()` (refined in review: public setters change
only the current value, protected `setInitialValue()` sets current and initial value, 3.1); no `getValueAsString()` on
number and date/time fields; `TimeField` holds the new `actra\yuf\common\TimeOfDay` (3.13); `FormMessages` has English
defaults plus `FormMessages::german()` (3.9, message table); extension points and all rules stay non-final (3.12); a
scalar posted to a multi field is invalid input; `Form` gets `validate(?FormInput)`/`isSent(?FormInput)` in task 6
(3.11); `MinValueRule`/`MaxValueRule` migration example (design section 5).

Refinements from the review (applied in [design.md](design.md), tasks below follow them):

- **Split setters:** public `setValue()`/`setValues()`/`setChecked()` change only the current value (the initial value =
  constructor value stays, `valueHasChanged()` compares with it); protected `setInitialValue()` /
  `setInitialValues()` / `setInitiallyChecked()` set current and initial value and throw a `LogicException` once
  `validate(FormInput)` or `validateCurrentValue()` has run on the field (3.1).
- **Form name check stays:** the static state moves from `Form` into the `@internal` class `FormNameRegistry`
  (`register()`, `reset()`), the one deliberately kept global state in `src/form/` (3.11).
- **Custom rules** extend a typed base (`StringRule`, `IntegerRule`, ...), never `FormRule` (3.8).
- **`PasswordField` and `CsrfTokenField` have no public setter:** `StringInputField` (no setter) and
  `SettableStringInputField` (adds `setValue(string)`); `CsrfTokenField` extends `InputField` directly, no getter (3.2).

### Task 2: Typed value storage in `FormField` and the string input fields

Implement design sections 3.1 to 3.6 and 3.9 for `FormField` and the single-text fields. Introduce the `@internal`
bridge (design section 6: new bases override the legacy `getRawValue()`/`setValue(mixed)`/`isValueEmpty()`/
`validate(array, bool)`), `FormInput` (`fromArray()` with query part, shapes, `InputShapeEnum`; no
`getUploads()`/`fromGlobals()` yet), `FormMessages` incl. `german()` and `Form(messages:)` with the `addField()`
handover, `TextualField`, `InputField`, `StringInputField` (value and `getValueAsString()`, no public setter),
`SettableStringInputField` (adds `setValue(string)`), `TextField`, `EmailField`, `HiddenField` (string), `PasswordField`
(extends `StringInputField`: no setter, no pre-fill), `TextAreaField` (`getValues()` stays), `normalize()` rules, the
public setters (current value only), the protected `setInitialValue()` (current and initial value; `LogicException`
if `validate()`/`validateCurrentValue()` already ran, via a private flag in `FormField`), and the renderers of these
fields (`InputFieldRenderer`, `TextAreaRenderer`) without `getRawValue()`. Keep the German texts of these fields working
through `FormMessages::german()`. Make classes `final` unless they are extension points (design 3.12). The matching
`UPGRADE.md` entries (typed setters and the split into `setValue()` and protected `setInitialValue()`, removed
`getOriginalValue()`/`setOriginalValue()`, `FormMessages` migration, no normalization/rendering of passwords, no setter
on `PasswordField`) belong to this task.

Verify: tests updated/extended (`FormInputTest`, one test per `readInput()` shape row); no baseline entries left for
touched files; `ddev composer check` green.

Handover notes:

Done (2026-10-04), `ddev composer check` green, no baseline entries left in the touched files.

**What was built**

- New: `FormInput` (`fromArray(data, files, query)`, `getShape()`, `getText()`, `getList()`, `hasQueryKey()`,
  `getQueryText()`; `mixed` only in `fromArray()`/`toStringList()`; data wins over files; no `getUploads()`/
  `fromGlobals()` yet), `InputShapeEnum`, `FormMessages` (+ `german()`), `PasswordPurposeEnum` (decision 18),
  `TextualField`, `StringInputField`, `SettableStringInputField`. Changed: `InputField` (no value, no
  `getValueAsString()`), `TextField`, `EmailField` (final, `normalize()` canonicalizes), `HiddenField`, `PasswordField`
  (final, `purpose`), `TextAreaField`, `PhoneNumberField` (final, rebased), `DateTimeFieldCore` (rebased),
  `FormField`, `Form(messages:)`, `InputFieldRenderer`, `TextAreaRenderer`.
- `TextualField` owns the pipeline `readInput(FormInput)` -> `normalize()` -> `accept()`, the text storage
  (`getText()`, `changeText()` for public setters, `changeInitialText()` for `setInitialValue()`), `isValueEmpty()`,
  `valueHasChanged()` (plain comparison), `renderValue()`. `FormField` got `validateCurrentValue()` (`validate()` now
  calls it), the private flags `inputReceived` / `inputRejected`, `rejectInput(string)` (one error, rules skipped until
  the next `validate()` with input), `assertInitialValueCanBeSet()` (`LogicException`), `public FormMessages $messages`
  (set by `Form::addField()`, English default without a form). Legacy German message in `FormField::setValue()` now
  comes from `messages->invalidInput`. `FormField` baseline entries fixed with explicit `mixed`/phpdoc types.

**Deviations from the design (and why)**

1. **Typed setter signature.** PHP does not allow `setValue(string)` in a child of `FormField::setValue(mixed)`, and
   `FileField`/`ToggleField` still need the legacy method. So `SettableStringInputField::setValue()` and
   `TextAreaField::setValue()` are declared `setValue(mixed $value)` and throw a `TypeError` for non-strings (documented
   in the phpdoc). They become `setValue(string)` in task 6. Consequence: until then PHPStan does not report a wrong
   type, and `PasswordField` (no setter in the design) still inherits a public `setValue()`; `TextualField::setValue()`
   throws a `LogicException` ("has no setter") so nothing is silently ignored. Same for `setOriginalValue()` (always
   throws `LogicException` on textual fields); `getOriginalValue()` stays as bridge (returns the initial text, needed by
   `PhoneNumberField::valueHasChanged()` until 4b).
2. **`DateTimeFieldCore` and `PhoneNumberField` were rebased** onto `SettableStringInputField` (one-line change each),
   because they extended `InputField`, which no longer has a value. Their behaviour changed with the new input path
   (trim, reset on array input). `PhoneNumberField` is `final` as designed; `DateField`/`TimeField`/`AmountField`/
   `NumericField`/`ZipCodeField`/`IbanNumberField`/`CsrfTokenField` were not touched.
3. **`HiddenField` is not final** because `CsrfTokenField extends HiddenField` until task 4b. It is string-only
   (`?string` constructor value, no `int|float|bool`); `valueIsInt` + `getValueAsInt()` stay as temporary bridge until
   4a (`HiddenIntegerField`). The int check is done in `validateCurrentValue()` with `messages->invalidValue` (lazy), not
   with a `ValidAmountRule` fixed at construction; invalid values are kept (as in v3), the error is added.
4. **`InputField` has no `getValueAsString()` any more** (moved to `StringInputField`, as designed). `DateField`/`TimeField`
   still get it through the rebased `DateTimeFieldCore`; 4a removes it there.
5. **`addRule(FormRule)`** stays on `FormField` (typed `StringRule` comes in task 6); `EmailField` still uses
   `ValidEmailAddressRule` (untouched, its `setValue()` call is now a no-op with the canonical value), `normalize()` does
   the canonicalization. `PhoneNumberRule`/`ValidDateRule`/`ValidTimeRule` still call `setValue()` (rules untouched).
6. `getRawValue()` of textual fields returns `string` (`''` for empty, `null` only for `getRawValue(true)` on empty,
   conditional phpdoc return type) - was `null` for no value. The array-based `validate(array, bool)` builds one
   `FormInput` per field (`readInputData()` hook; removed with the bridge).
7. `PasswordField`: `autoComplete` argument removed, `purpose` required (decision 18); `PasswordField` has no initial
   value (`StringInputField::setInitialValue()` is protected and unused).
8. `FormMessages` placeholders: `invalidOption` uses `[field]` (v3 text had the field name appended; `german()` keeps the
   exact English v3 text with `[field]` placeholder, nothing uses it yet - task 3).

**The bridge (temporary, remove in task 6)**

`FormField`: `getRawValue()`, `setValue(mixed)`, `getOriginalValue()`, `setOriginalValue(mixed)`, `initializeLegacyValue()`,
`readInputData()`, `validate(array, bool)` signature, private `value`/`originalValue`/`acceptArrayAsValue`, the
`getValueAs*OrFail()` helpers (they now read `getRawValue()`). `TextualField`: `getRawValue()`, `setValue()` (throws),
`getOriginalValue()`, `setOriginalValue()` (throws), `initializeLegacyValue()` (no-op), `readInputData()`.
`SettableStringInputField`/`TextAreaField`: `setValue(mixed)` becomes `setValue(string)`. `HiddenField`: `valueIsInt`,
`getValueAsInt()` (until 4a). `DateTimeFieldCore`/`PhoneNumberField`: rebased on `SettableStringInputField`, replaced in
4a/4b. All marked `@internal` in phpdoc.

**Tests changed on purpose**

`TextFieldValueTest`, `TextAreaFieldValueTest`, `PasswordFieldValueTest`, `HiddenFieldValueTest` rewritten (typed string
value, `getValueAsString()` instead of `getRawValue()`, trim/ZWSP, reset on invalid input, setters vs. initial value; the
array value of `TextAreaField` and the `int|float|bool` constructor values of `HiddenField` are gone; `getValues()`
provider cases kept). Edited: `EmailFieldValueTest`, `IbanNumberFieldValueTest`, `ZipCodeFieldValueTest`,
`PhoneNumberFieldValueTest`, `DateTimeFieldValueTest`, `AmountFieldValueTest`, `CsrfTokenFieldValueTest`,
`InputFieldGetValueAsStringTest` (no value is `''` not `null`; array input resets instead of keeping the value; input is
trimmed; PasswordField needs `purpose`). New: `FormInputTest`, `FormMessagesTest`, `PasswordPurposeEnumTest`,
`FormMessagesHandoverTest`, `InitialValueTest` (+ `tests/Double/form/InitialValue*Field`), `TextualFieldRenderersTest`.

**Baseline:** 198 entries removed, none added; no entry left for `FormField`, `Form`, `TextAreaField`,
`DateTimeFieldCore`, `PhoneNumberField`, `InputFieldRenderer`, `TextAreaRenderer` (also gone as side effect:
`IbanNumberField`, `ZipCodeField`, `PhoneNumberRule`, `ZipCodeRule`, one each in `ToggleField`, `DefaultFormRenderer`).

**For tasks 3 to 7**

- The legacy `FormField::setValue(mixed)` still exists, so a new family cannot declare a narrower public setter either:
  use the same `mixed` + `TypeError` pattern until task 6 (or avoid the clash otherwise).
- Use `rejectInput()`/`inputRejected` for invalid option/list input; `assertInitialValueCanBeSet()` for every
  `setInitialValue*()`; `messages` for texts (`invalidInput`, `invalidOption`, `selectEmptyOption` ...).
- The old rules still run via `addRule(FormRule)`; typed rules (task 6) replace them, then `ValidEmailAddressRule` must
  not call `setValue()` anymore (normalization is done by the field).
- 4a: remove `getValueAsString()` from the rebased `DateTimeFieldCore`; `AmountField` still extends `TextField` and
  validates with `ValidAmountRule` on the text. 4b: `CsrfTokenField` onto `InputField`, then `HiddenField` can be
  `final`; `PhoneNumberField::valueHasChanged()` can go (plain comparison now); `ZipCodeField`/`IbanNumberField` were not
  changed (still `validate(array, bool)` overrides).
- Task 7 / `README.md`: lines about `HiddenField(value: $id, valueIsInt: true)` (needs a string value now) and
  `getValueAsString()` on `InputField` need an update; `UPGRADE.md` v4 section: text fields part is written, more
  sections follow per task.

### Task 3: Option and multi-value fields

Implement design sections 3.3 and 3.10: `OptionsField`, `SingleOptionsField`, `MultiOptionsField`, `isSelected()`, the
`Multi*` split (`MultiSelectOptionsField`, `MultiToggleField`; flags removed), `BooleanField` as `bool` (no longer a
`CheckboxOptionsField`, `setChecked()`, protected `setInitiallyChecked()`), typed setters (`setValue(?string)`,
`setValues(list<string>)`) plus protected `setInitialValue(?string)`/`setInitialValues(list<string>)`,
`ToggleChildren` + `ToggleFieldRenderer`, the options renderers (`DefaultOptionsRenderer`, `SelectOptionsRenderer`,
`CheckboxItemRenderer`, ...) without `getRawValue()`, `FormOptions` final, invalid-option handling in `readInput()` with
`FormMessages`. A scalar posted to a multi field is invalid input (reset, one error). All option fields stay
non-final. `UPGRADE.md`: `Multi*` split, scalar-to-multi behaviour change, removed flags.

Verify: as task 2.

Handover notes:

Done (2026-10-04), `ddev composer check` green (604 tests), 32 baseline entries removed, none added.

**What was built**

- New: `SingleOptionsField` (`string`, `''` = none; `getValueAsString()`, `setValue(?string)`, protected
  `setInitialValue(?string)`), `MultiOptionsField` (`list<string>`; `getValues()`, `setValues(list<string>)`, protected
  `setInitialValues(list<string>)`, `getAddedValues()`, `getRemovedValues()`), `MultiSelectOptionsField`,
  `MultiToggleField`, `ToggleChildren` (final, child registry + validation of the children of the selected options +
  form handover), `ToggleFieldRenderer`, the trait `SelectOptionsSettings` (cssClasses, placeholder, empty option,
  data attributes shared by the two select classes). `OptionsField` (abstract) now has no value: `isSelected()`,
  `isMultiple()`, `readInput()` (abstract), `rejectInvalidOption()`.
- Changed: `RadioOptionsField`, `SelectOptionsField`, `ToggleField` extend `SingleOptionsField`;
  `CheckboxOptionsField` extends `MultiOptionsField`; `BooleanField` extends `FormField` (`bool`, `isChecked()`,
  `setChecked()`, protected `setInitiallyChecked()`, public const `CHECKED_KEY`); `FormOptions` final;
  `DefaultOptionsRenderer`, `SelectOptionsRenderer` (`SelectOptionsField|MultiSelectOptionsField`),
  `CheckboxItemRenderer` (`CheckboxOptionsField|BooleanField`) use `isSelected()`; new `BooleanFieldListRenderer`
  (v3 list markup of `BooleanField`).
- Removed: the flags `acceptMultipleSelections`/`multiple`, `ToggleField::$defaultChildFieldRenderer` (class string,
  now `setDefaultChildFieldRenderer(Closure)`), `ToggleField::getHtmlTag()`/`changeValueToArray()`,
  `getValues()` of single fields and of `BooleanField`, `ValidateAgainstOptions` is no longer added (class kept, see
  below), `FormField::getValueAsStringOrFail()`/`getValuesAsStringListOrFail()` (unused now).
- `readInput()` per family as in design 3.3: single: TEXT must be a key (or `''`), MISSING gives `''`, list/invalid is
  invalid input; multi: LIST keys must exist (`''` entries dropped), MISSING gives `[]`, TEXT and INVALID are invalid
  input (scalar to a multi field); boolean: text or one-entry list `checked`, MISSING unchecked, else invalid input.
  Rejected input resets the value, adds one error (`rejectInput()`) and skips the rules.

**`FormInput` key addition (approved in review)**

`FormInput` stores an array of strings with its keys (`array<string, array<int|string, string>>`, int and string keys,
order kept). `getList(name): ?list<string>` is unchanged for callers (values, keys dropped); new
`getMap(name): ?array<int|string, string>` returns the keys too. Same shape `LIST` for both (no new shape: nothing
needs a distinction). Nested arrays and non-string entries stay `INVALID`. Tests in `FormInputTest`; design 3.3 has a
paragraph marked "(added in review)" and decision 19.

**Deviations from the design (and why)**

1. **Typed setter on single fields is `setValue(mixed)`** with a `TypeError` for anything but `?string` (same bridge
   pattern as task 2: PHP forbids narrowing `FormField::setValue(mixed)`); becomes `setValue(?string)` in task 6.
   `MultiOptionsField::setValue()` (legacy) throws a `LogicException` pointing to `setValues()`; `setValues()` is a
   typed new method (no clash). `BooleanField::setValue()`/`setOriginalValue()` and `OptionsField::setOriginalValue()`
   throw a `LogicException` (like `TextualField`).
2. **`BooleanField` is not final** (design 3.12 listed it as final, but 3.1/5 give it a protected
   `setInitiallyChecked()` for subclasses, and v3 was open). Confirmed in review; design 3.12 and decision 9 updated.
3. **`BooleanField` keeps the v3 markup for all layouts** (review): `NONE`, `DEFINITION_LIST` and `LEGEND_AND_LIST` render
   the v3 list with one checkbox (`id="name_checked"`), because `BooleanFieldListRenderer` builds a short-lived
   `CheckboxOptionsField` copy of the field (name, label, id, info, required, errors, checked state) and lets the
   options renderers render it. `CHECKBOX_ITEM` (default) uses `CheckboxItemRenderer`. No HTML change and no layout
   exception; `BooleanFieldV3MarkupTest` pins the full form HTML of v3.3.1 (all layouts, checked/unchecked with error).
4. **`valueHasChanged()` of multi fields compares the selection as a set** (via `getAddedValues()`/
   `getRemovedValues()`), not the arrays strictly: posted order follows the options, a loaded order may differ.
5. **Empty keys are dropped in constructor and setters of multi fields too** (not only at input), so `getValues()`
   keeps the v3.3.0 meaning ("no `''` entries") without a filter; non-string entries are a `TypeError`.
6. **`MultiOptionsField::renderValue()` returns `''`** (a list has no text; v3 threw for arrays).
7. **Messages are resolved lazily:** `RadioOptionsField` keeps its default `RequiredRule` and sets its message from
   `messages->selectOneOption` in `validateCurrentValue()` (the field gets its messages only in `Form::addField()`);
   `emptyValueLabel` of the select classes is a computed property (individual label, else `selectEmptyOption` if
   required, else `''`). The invalid option text uses `[field]` (`str_replace`).
8. **Select presentation settings** are shared by a trait (`SelectOptionsSettings`), because `SelectOptionsField` and
   `MultiSelectOptionsField` cannot share a base class (different value types) and duplicating ~60 lines was worse.
   The three `readonly` properties became `private(set)` (PHPStan rejects assigning `readonly` from a trait method).
9. **`ToggleField`/`MultiToggleField` set `ToggleFieldRenderer` as their renderer in the constructor** (via
    `getDefaultRenderer()`, so a subclass can override it). Reason: `Form`'s renderer would otherwise wrap the toggle
    in a `<dl>` (v3 ignored the renderer through a `getHtmlTag()` override). Side effect: like every field, a toggle
    can be rendered only once (the `FormRenderer::setHtmlTag()` guard); v3 toggles could be rendered repeatedly.
    `ToggleChildren` hands `topFormComponent` and `messages` to the children when the toggle is already in a form
    and again before the children are validated (the toggle is usually added to the form after its children).
10. `FormOptions::$data` is documented `array<int|string, HtmlText>` (design: `array<string, HtmlText>`): numeric
    string keys become `int` keys in PHP, so `string` would be wrong; renderers cast with `(string)$key`.
11. `ValidateAgainstOptions` (and its baseline entry) stays: its removal is listed in task 6; it is no longer used by
    any field.
12. `README.md` was not touched (task 7).

**The bridge (added or changed in this task, remove in task 6)**

`OptionsField`: `readInputData(array)` (builds a `FormInput`), `initializeLegacyValue()` (no-op),
`setOriginalValue()` (throws). `SingleOptionsField`/`MultiOptionsField`/`BooleanField`: `getRawValue()` (`string`,
`list<string>`, `bool`; legacy rules like `MinLengthRule` still read it), `getOriginalValue()`, `setValue(mixed)`.
`ToggleField`/`MultiToggleField` and `ToggleChildren::validateSelected()` keep the `validate(array, bool)` signature,
move to the `validate(FormInput)` template hook in task 6 (the children must be validated after the field itself, with
the same `FormInput`). `FormField::getAddedValues()`/`getRemovedValues()`/`isValueEmpty()`/`valueHasChanged()` still
read the unset legacy `$value` (`Error` on uninitialized property) for fields that do not override them: textual
fields and single/boolean fields do not override `getAddedValues()`/`getRemovedValues()`, so calling them there throws
(they existed on every field in v3 and returned `[]`); they go away with the bridge.

**Tests changed on purpose**

Rewritten: `RadioOptionsFieldValueTest`, `SelectOptionsFieldValueTest`, `ToggleFieldValueTest`,
`CheckboxOptionsFieldValueTest` (typed getters instead of `getRawValue()`; reset to empty instead of storing unknown
options and arrays; one error and no second "required" error; scalar to a multi field is invalid input, also in the
v3.3.0 `getValues()` wrap tests; no `[null]` start of a multi toggle; no array value for a single select; no
`getValues()` on single fields; the `getValues()` "never throws after validation" provider kept for checkbox). The
`BooleanField` tests moved to `BooleanFieldValueTest` (`getValues()` removed, text and list `checked`). `FormInputTest`
extended (keys). New: `MultiSelectOptionsFieldValueTest`, `MultiToggleFieldValueTest`, `BooleanFieldValueTest`,
`OptionsInitialValueTest` (+ `tests/Double/form/InitialValue{Radio,Checkbox}OptionsField`, `InitialValueBooleanField`),
`ToggleChildrenTest`, `FormOptionsTest`, `renderer/OptionsFieldRenderersTest`.

**Baseline:** 32 entries removed (`OptionsField` 3, `SelectOptionsField` 6, `ToggleField` 16, `CheckboxOptionsField` 1,
`CheckboxItemRenderer` 2, `DefaultOptionsRenderer` 1, `SelectOptionsRenderer` 3), none added; no entry left in touched
files. Not touched, still with entries: `LegendAndListRenderer` (1), `ValidateAgainstOptions` (1; task 6),
`FormField` had none.

**For tasks 4a to 7**

- Task 6: the bridge `setValue(mixed)` of the single fields becomes `setValue(?string)`; delete
  `ValidateAgainstOptions`; `ToggleField`/`MultiToggleField::validate()` and `ToggleChildren::validateSelected()` need
  the `FormInput` version; the typed `addRule(StringListRule)`/`addEachRule()` belong on `MultiOptionsField`;
  `RequiredRule` stays what `addRequiredRule()` adds (`isRequired()` of the radio field relies on it).
- Task 7: `README.md` "Form Field Values" (`getValues()` on `SelectOptionsField`/`ToggleField`, `BooleanField`
  `getValues()`, `AmountField`); `CheckboxOptionsLayout`/`RadioOptionsLayout` -> `*Enum` (the `BooleanField` layout
  argument uses `CheckboxOptionsLayout`); `LegendAndListRenderer` entry; 
- 4a/4b: `FormInput::getMap()` is available for project fields; fields that need list input use `getList()`.

### Task 4a: Numbers and date/time

Implement design sections 3.7 and 3.13: `IntegerField`, `NumericField` (final subclass), `HiddenIntegerField`,
`FloatField`, `DecimalField` (bcmath, `scale` required, too many decimals rejected), `DateField`, `TimeField` with the
new `actra\yuf\common\TimeOfDay`; public typed setters and protected `setInitialValue()`; **no `getValueAsString()`** on
these fields; removal of `AmountField` and `DateTimeFieldCore`; `NumericFieldRenderer` without `getRawValue()`.
`UPGRADE.md`: the `AmountField` split, the removed `getValueAsString()` of amounts and dates with replacement, the
`MinValueRule`/`MaxValueRule` migration example (design section 5; the typed rules themselves come in task 6, so write
the example then or mark it).

Verify: as task 2, plus unit tests for `TimeOfDay`.

Handover notes:

Done (2026-10-04), `ddev composer check` green (832 tests), 5 baseline blocks (30 lines) removed, none added; no entry
left in the touched files.

**What was built**

- New: `IntegerField` (non-final, `?int`, `getValueAsInt()`, `setValue(?int)`, protected `setInitialValue(?int)`),
  `FloatField` (final, `?float`), `DecimalField` (final, `?string`, required `scale`, `getValueAsDecimal()`),
  `HiddenIntegerField` (final, `?int`), `ParsedInputField` (abstract base, see deviation 1), `actra\yuf\common\TimeOfDay`,
  `AmountParser::toDecimal(value, scale)` (bcmath, rejects more decimals). Changed: `NumericField` (final, extends
  `IntegerField`), `DateField` (final, `?DateTimeImmutable`), `TimeField` (final, `?TimeOfDay`, `getValueAsTimeOfDay()`),
  `HiddenField` (no `valueIsInt`, no `getValueAsInt()`), `FormField` (the `getValueAsIntOrFail()`/`getValueAsFloatOrFail()`
  helpers and their private helpers removed), `HiddenFieldRenderer` (takes an `InputField`), `NumericFieldRenderer`
  (no `getRawValue()` was read there; its baseline entry fixed with an explicit `LogicException`).
- Removed: `AmountField`, `DateTimeFieldCore`, `ValidAmountRule`, `ValidDateRule`, `ValidTimeRule` (nothing else used
  them). `FloatValueRule`/`NumericValueRule` stay: they were not part of the task text, nothing uses them, task 6
  removes them (design 3.7/3.8).
- The typed fields parse in `accept()` (override of the `TextualField` hook, parent called with the canonical text, so
  `getText()`, `isValueEmpty()`, `valueHasChanged()` (plain text comparison) and `renderValue()` need no change) and keep
  the native value in a private property. Invalid text: typed value `null`, the text is kept (re-rendering), the error
  is added in `validateCurrentValue()` before the listeners (`ParsedInputField`). Setters/constructor go through
  `changeText()`/`changeInitialText()`, so they use the same path.

**Deviations from the design (and why)**

1. **`ParsedInputField` (new abstract class between `InputField` and the typed fields).** The design lets the typed
   fields extend `InputField` directly. The invalid-text handling (required rule, `holdsUnparsableText()`, the error in
   `validateCurrentValue()`, the getter exception, the bridge `TypeError`) would have been copied six times, so it
   lives in one small base. It owns no value and no parser (each field has its own native property and `accept()`), so
   it is not the "second parsed-input hierarchy" the design wanted to avoid. Not an extension point for projects.
2. **No protected `setInitialValue()` on the final fields** (`FloatField`, `DecimalField`, `HiddenIntegerField`,
   `DateField`, `TimeField`). The twin only exists for subclasses; on a final class it is dead code and cannot be
   called. They take the initial value in the constructor. `IntegerField` (non-final; `NumericField` inherits it) has it.
3. **Typed setter signature:** `setValue(mixed)` with a `TypeError` for the wrong type on all new fields (same bridge as
   tasks 2 and 3; becomes `setValue(?int)` etc. in task 6). `FloatField::setValue()` accepts an `int` (like a `float`
   parameter would), `DecimalField` throws an `InvalidArgumentException` for a string that is no decimal or has too many
   decimals (also for `''`: use `null` for empty).
4. **Trailing zeros count as decimals in `DecimalField`:** `'12.500'` with scale 2 is rejected (literal reading of "more
   decimals than scale"; the value would not be rounded, but the rule stays simple and the test pins it).
5. **Canonical rendering (design 3.7: "the rendered text is the canonical text of the value"):** the stored text of a
   valid value is canonical, so `'+007'` renders `7`, `'7.50'` in a `FloatField` renders `7.5`, `DecimalField` `12.50`.
   `FloatField` uses the shortest round-trip text without exponent (`json_encode()`, `number_format()` for exponent
   forms) because `(string)$float` uses the `precision` ini setting and loses digits (and gives `1.0E+25`, which the
   parser does not accept). HTML for the v3.3.1 equivalents is identical (see tests); only such non-canonical input
   differs, and only after a re-render.
6. **`DateField`/`TimeField` constructor argument names are unchanged** (`value`, `invalidError`, required positional
   `value`); `invalidError` is the field's `individualInvalidError`. Date parsing: `checkdate()` instead of the
   `DateTimeImmutable` warning check (year 0000 is invalid now; v3 accepted it).
7. Unparsable text does **not** skip the other rules (like v3, e.g. a `MaxLengthRule` still runs on the text); only
   shape errors (array/manipulated) use `rejectInput()` (value reset, one error, no rules).
8. `HiddenIntegerField` removes only U+200B (not trimmed, exact round trip; the parser ignores whitespace) and has no
   label/required/individual error (message `FormMessages::invalidValue`), as `HiddenField(valueIsInt: true)` had.
9. The `MinValueRule`/`MaxValueRule` migration example of design section 5 is not repeated in `UPGRADE.md`: the typed
   rules do not exist before task 6, so `UPGRADE.md` only notes them (task 6 writes the example).

**The bridge (changed in this task)**

`HiddenField::valueIsInt`/`getValueAsInt()` (task 2 bridge) are gone, `FormField::getValueAsIntOrFail()`/
`getValueAsFloatOrFail()` too. Still bridge: the `setValue(mixed)` of the typed fields, `getRawValue()` (returns the
canonical or kept text of the typed fields, old rules like `MaxLengthRule` read it), `getOriginalValue()`,
`validate(array, bool)`.

**Tests changed on purpose**

Removed `AmountFieldValueTest` and `DateTimeFieldValueTest` (replaced by the new tests below; cases carried over:
accepted/rejected formats, overflow, whitespace, array input resets, missing key, getter exceptions; the
`getRawValue()`/`getValueAsString()` assertions are gone). `HiddenFieldValueTest`: `valueIsInt` cases moved to
`HiddenIntegerFieldValueTest`. `InputFieldGetValueAsStringTest`: the `AmountField` cases removed. `AmountParserTest`
extended (`toDecimal()`). New: `IntegerFieldValueTest`, `NumericFieldValueTest`, `HiddenIntegerFieldValueTest`,
`FloatFieldValueTest`, `DecimalFieldValueTest` (scale, more decimals rejected, negative, leading zeros, 60/80 digit
numbers), `DateFieldValueTest`, `TimeFieldValueTest`, `tests/Unit/common/TimeOfDayTest`,
`renderer/NumberAndDateFieldRenderersTest` (HTML rendered by v3.3.1, 19 cases), `tests/Double/form/InitialValueIntegerField`.

**Baseline:** removed `NumericFieldRenderer` (2 errors), `ValidDateRule` (3), `ValidTimeRule` (2) (together with the
deleted files), none added (one `@phpstan-ignore argument.type` with reason in `AmountParser::toDecimal()`: PHPStan
cannot see that `isDecimal()` makes the string numeric for `bcadd()`). No entry left in the touched files.

**For tasks 4b to 7**

- 4b: `HiddenField` still is not final (`CsrfTokenField extends HiddenField`); `PhoneNumberField`/`ZipCodeField`/
  `IbanNumberField` untouched. `ParsedInputField` is not needed there.
- Task 6: `setValue(mixed)` of `IntegerField`, `HiddenIntegerField`, `FloatField`, `DecimalField`, `DateField`,
  `TimeField` becomes the typed parameter; `addValueRule(IntegerRule|FloatRule|DecimalRule)` goes on `IntegerField`
  (so `NumericField`), `FloatField`, `DecimalField` (they hold the native value; call the rules only for a non-empty,
  parsed value, `hasParsedValue()`); delete `FloatValueRule` and `NumericValueRule`; write the `MinValueRule`
  migration example in `UPGRADE.md`; the invalid-value error in `ParsedInputField::validateCurrentValue()` moves to
  the read step of the `validate(FormInput)` template (before the listeners, as now).
- Task 7: `README.md` "Form Field Values" still names `AmountField`, `HiddenField(valueIsInt: true)` and
  `getValueAsInt()` on `HiddenField` (outdated now); `InputTypeValue`/`AutoCompleteValue` are still used by the new
  fields (rename with the enums).

Review change (user decision): `DecimalField` accepts trailing zeros beyond `scale` (`'12.500'` with scale 2 gives
`'12.50'`, `'12.0'` with scale 0 gives `'12'`); only significant extra decimals (`'12.505'`) are rejected
(`AmountParser::toDecimal()`, tests, UPGRADE.md and design 3.7 / decision 5 updated).

### Task 4b: Phone, zip code, IBAN, hidden and CSRF fields

`PhoneNumberField` (own `valueHasChanged()` removed), `ZipCodeField` (+ `ZipCodeValidator`), `IbanNumberField` (+
`IbanValidator`), `PhoneNumberField` on `SettableStringInputField`, `CsrfTokenField` (extends `InputField` directly, no
getter, no setter) with `CsrfTokenSource` and `SessionCsrfTokenSource`, check `PasswordField` (on `StringInputField`,
no setter, no normalization, never rendered back). Messages from `FormMessages`.

Verify: as task 2.

Handover notes:

Done (2026-10-04), `ddev composer check` green (994 tests), 1 baseline block (6 lines) removed, none added; no entry
left in the touched files (the only entry of a touched file was `ValidCsrfTokenValue`, deleted with it).

**What was built**

- New: `ZipCodeValidator::validate(zipCode, countryCode)` and `IbanValidator::validate(input)` (`final`, pure static
  functions in `actra\yuf\datacheck\validatorTypes`, next to the existing `IpValidator`/`DomainValidator`, same style);
  `CsrfTokenSource` (interface: `getToken()`, `isValid(string)`) and `SessionCsrfTokenSource` (`final readonly`, wraps
  `CsrfToken`) in `actra\yuf\security`; test double `tests/Double/security/InMemoryCsrfTokenSource`.
- Changed: `PhoneNumberField` (`normalize()` canonicalizes to the internal format, `readAdditionalInput()` reads the
  country code, `validateCurrentValue()` adds `invalidErrorMessage`, own `valueHasChanged()` removed, the `validate()`
  override and `PhoneNumberRule` gone), `ZipCodeField` (final, `ZipCodeValidator`, error from `individualInvalidError`
  or `FormMessages::invalidZipCode`), `IbanNumberField` (final, `IbanValidator`, error only if the other rules passed,
  as in v3), `HiddenField` (final), `CsrfTokenField` (extends `InputField`, optional `CsrfTokenSource` argument,
  `accept()` checks the token, `renderValue()` is the token of the source), `Form` (new last constructor argument
  `?CsrfTokenSource $csrfTokenSource`, CSRF check, see bridge), `PasswordField` (checked: final, `StringInputField`,
  no normalization, `renderValue()` is `''`; no change needed).
- Removed: `ZipCodeRule`, `PhoneNumberRule`, `ValidCsrfTokenValue` (design 3.8, nothing else used them).
  `RequiredRule` stays (task 6).

**Deviations from the design (and why)**

1. **Validators are static classes in `datacheck\validatorTypes`** (the design only names them): the existing
   validators there are `final`-less static classes with `validate()`; pure static functions have no state. Instances
   would only add boilerplate.
2. **`IbanValidator` is stricter on nonsense input:** only `[a-z0-9]` after removing spaces is accepted (v3 ignored
   unknown characters and raised an "undefined array key" warning) and more than 34 characters are rejected (a post of
   megabytes made `bcmod()` run on a huge number). A 300000-case differential test against the v3.3.1 code (random
   strings of known and unknown countries) gave no difference for input with letters and digits. The per-country
   length table of v3 was never used for validation (only `isset()`), so it became a list of country codes; length is
   still not checked (the table has a wrong value for CR, 21 instead of 22, so a check would reject valid IBANs).
3. **CSRF is checked in the field, in a second pass of `Form`:** the design says "compares in `accept()`". The field
   calls `CsrfTokenSource::isValid()` in `accept()` (stores the result) and adds the `invalidCsrfToken` error in
   `validateCurrentValue()` (unless the input was rejected and has its error). The GET fallback needs the query part,
   which the array based `validate()` does not carry, so `CsrfTokenField::readAdditionalInput()` reads it from the
   `FormInput`. `Form::validate()` skips the field in the first pass and validates it afterwards (only if all other
   fields are valid, as in v3), with `FormInput::fromArray(data: $inputData, query: $_GET)` and the new bridge method
   `validateInput()`; it then adds `messages->invalidCsrfToken` to the form (v3: the rule message). Observable behaviour
   is the v3 behaviour (pinned by `SpecialFieldRenderersTest` with the HTML of v3.3.1, `FormCsrfTest`). Fixed on the
   way: since task 2 the fallback to `$_GET` was dead (the empty value was `''`, v3 checked `null`).
4. **Phone constructor values are canonicalized** (design 3.6: constructor, setters and `normalize()` share one path),
   so `new PhoneNumberField(value: '044 668 18 00')` holds `+41.446681800`; the country code is assigned before
   `parent::__construct()` for that reason. Rendering is unchanged (v3.3.1 rendered the same international format).
5. **Security fix, HTML change:** `PhoneNumberField::renderValue()` returned the unencoded text if the field had an
   error (v3.3.1: `"><b>x` broke out of the `value` attribute). It is encoded now (listed in `UPGRADE.md`). All other
   HTML equals v3.3.1, except the already documented trimming of posted zip codes and IBANs (task 2, normalization).
6. **Phone error timing:** the invalid-number error is added before the listeners run (like the typed fields of 4a),
   v3 added it with the rules after the "before validation" listeners. Zip code: same. IBAN: after the rules, as in v3.
7. **`readAdditionalInput(FormInput)` sits on `TextualField`** (design: on `FormField`, task 6). Called by the final
   `readInput()` before the value is read.

**The bridge (changed in this task, remove in task 6)**

New, `@internal`: `TextualField::validateInput(FormInput): bool` (needed for the query part, and handy in tests) and
`FormField::startReadingInput()` (protected, shared with `validate(array)`). Task 6: `validate(FormInput)` replaces
`validateInput()`, and `Form::validate(?FormInput)` passes its `FormInput` (with the query from `fromGlobals()`) to
`CsrfTokenField` instead of building one from `$_GET`; the second CSRF pass and the `instanceof CsrfTokenField` skip in
`Form::validate()` / `validateCsrf()` stay as they are (or move into a `Form` method). `readAdditionalInput()` can move
to the `FormField` template. `TextualField::setValue()` (throws) still gives `CsrfTokenField` and `PasswordField` a
public `setValue()` until the bridge is removed.

**Tests changed on purpose**

`PhoneNumberFieldValueTest`: the constructor value is canonical (was "not formatted"), `getValueAsString()` instead of
`getRawValue()`. `ZipCodeFieldValueTest`, `IbanNumberFieldValueTest`: `getValueAsString()`, array input resets the value
(was "keeps previous value" in the name only). `CsrfTokenFieldValueTest` rewritten (the field has no getter, tests use
the in-memory source). New: `ZipCodeValidatorTest`, `IbanValidatorTest`, `FormCsrfTest`, `SessionCsrfTokenSourceTest`,
`renderer/SpecialFieldRenderersTest` (HTML of v3.3.1 for phone, zip code, IBAN, hidden, password, CSRF and three forms,
one test per case; the XSS case and trimmed posted zip code/IBAN differ on purpose, see deviation 5).

**Baseline:** removed the block of `ValidCsrfTokenValue`; none added. `CsrfToken::getToken()` (1 entry, `mixed` from
`$_SESSION`) was not touched (not a form class).

**For tasks 5 to 7**

- Task 5: `FileField` still extends the legacy `validate(array, bool)` path; it can use the same pattern for the
  session class (`SessionFileUploadStorage` like `SessionCsrfTokenSource`, `?FileUploadStorage` argument on the field,
  in-memory double in `tests/Double/`). `Form` has the optional `csrfTokenSource` argument now; a `fileUploadStorage`
  argument is not needed (the field gets it directly).
- Task 6: see "The bridge" above. `Form` tests that call `validate()` need to save/restore `$_GET/$_POST/$_FILES`
  (`FormCsrfTest`, `SpecialFieldRenderersTest`) until `Form::validate(?FormInput)` exists; then they can pass a
  `FormInput`. `RequiredRule` is still used by the three fields (`addRule(RequiredRule)`).
- Task 7: `README.md` does not mention the new classes; check the sections on CSRF (`ValidCsrfTokenValue`) and zip code
  / phone validation if they exist.

### Task 5: `FileField`

Design section 3.11: `UploadedFile` (replaces `FileDataModel`), `UploadInput`, `FormInput::getUploads()` (narrowing of
`$_FILES` into `FormInput`), `FileUploadStorage` with `SessionFileUploadStorage` as default (the only class touching
`$_SESSION`/`$_SERVER`/clock), constants `ERRMSG_*`/`VALUE_*` removed, messages from `FormMessages`, `FileFieldRenderer`
text from `FormMessages::removeFile`, so the logic can be unit tested.

Verify: as task 2, plus unit tests with an in-memory storage double.

Handover notes:

Done (2026-10-04), `ddev composer check` green (1165 tests), 16 baseline entries removed (all of `FileField`), none
added; no entry left in the touched files. A real upload was tried against the PHP built-in server (two files, one
empty, add by pointer, duplicate, single `name="file"` input, remove, manipulated `$_FILES`, other session with the
same pointer): see "Not unit tested".

**What was built**

- New: `UploadedFile` (`final readonly`: `name`, `type`, `size`, `path`, `getHash()` = sha1 of the path) and
  `UploadInput` (`name`, `tmpName`, `type`, `error`, `size`) in `actra\yuf\form\model`; `FileUploadStorage` (interface:
  `load`, `save`, `store`, `delete`, `clear`, `removeExpired`) and `SessionFileUploadStorage` (`final readonly`,
  `__construct(rootDirectory)`, `forCurrentRequest()`) in `actra\yuf\form\upload`; `FormInput::getUploads(name)` and
  `hasMalformedUpload(name)`; test double `tests/Double/form/InMemoryFileUploadStorage`.
- Changed: `FileField` (final, `?FileUploadStorage $storage` last argument, no `$_SESSION`/`$_SERVER`/`$_FILES`/file
  system access, `readInput(FormInput)` private, messages lazy from `FormMessages`), `FileFieldRenderer` (remove text
  from `FormMessages::removeFile`, loop body rewritten to lines <= 120), `FormInput` (uploads).
- Removed: `FileDataModel`, the constants `VALUE_*` and `ERRMSG_*`, `FileField::setValue()` (throws), the protected
  `convertMultiFileArray()`, `addFilesFromDataArray()` and the private directory helpers (now in the storage).
- `FileField` keeps `getFiles()`, `getRemovedValues()` (`[hash]` if removal was requested, also for an unknown hash),
  `getAddedValues()` (the files, `list<UploadedFile>`), `removeOldFiles()`, `clearData()`, `uniqueSessFileStorePointer`,
  `maxFileUploadCount` (corrected to 1), the `(max. N)` label info, the optional `requiredError`.
- v3 features that exist and are kept: max file count, keeping files across failed validations (pointer `_UID`),
  removing a file (`_removeAttachment` = hash), duplicate file name, empty file, error codes (too big for
  `INI_SIZE`/`FORM_SIZE`, incomplete for `PARTIAL`, `NO_FILE` ignored, everything else technical), the "uploaded files"
  list. **v3 had no maximum file size, mime type or extension check** (only the PHP ini limits): nothing was lost and
  nothing was added.

**Deviations from the design (and why)**

1. `FileUploadStorage::store()` returns `?UploadedFile` (design: `UploadedFile`). v3 ignored the result of
   `move_uploaded_file()` and added a file that did not exist; `null` makes the field add the technical error instead.
2. **Upload structure from `$_FILES` that is built like an upload but is broken is invalid input** (one
   `FormMessages::invalidInput` error, rules skipped via `rejectInput()`, the files uploaded before stay): the design
   said `TEXT`/`LIST`/`INVALID` are ignored (still true for everything that is not built like an upload: text, list of
   texts, arrays without any of the keys `name`, `type`, `tmp_name`, `error`, `size`). Needed for the requested
   "manipulated structures are invalid input"; a v3 structure with missing keys was silently ignored. Hence the extra
   `FormInput::hasMalformedUpload()`; `getUploads()` returns `[]` for those.
3. Single (`name="file"`) and multiple (`name="file[]"`, also with string keys) structures are both supported (v3
   assumed `[]`); all five values must have the right type (`error` and `size` `int`, the others `string`), all five
   columns of a multi structure must have exactly the keys of `name`. `name` and `type` of an upload are trimmed (v3
   did the same in `convertMultiFileArray()`).
4. `FormInput::fromArray()`: an entry of `$files` named like a `$data` entry is no upload (the v3 precedence
   `$_POST + $_FILES`). The file-name `tmp_name` is not checked by `FormInput`, only by the storage.
5. **Namespace `actra\yuf\form\upload`** for the storage pair (the design did not name one); the value objects stay in
   `form\model`, where `FileDataModel` was.
6. `UploadedFile::getHash()` (design: only "key = sha1 of the stored path"); `UploadedFile` has no `error` (always
   accepted). `UploadInput.error` stays an `int` (`UPLOAD_ERR_*`, mapped with `match` in the field), no enum.
7. `SessionFileUploadStorage` keeps plain arrays in the session (`name`, `type`, `size`, `path`) and narrows them when
   loading, instead of serialized `FileDataModel` objects: removing the class would otherwise leave
   `__PHP_Incomplete_Class` objects. Old sessions are therefore not read (the user uploads again).
8. No injected clock: `removeExpired()` calls `time()` in the session storage (design: the session storage is the only
   class touching the clock); the test sets the modification time of the directories instead.
9. Messages: the default texts are built like v3 (the text with its quotes as it is, only the file name encoded, so the
   duplicate text still renders `"name"`, not `&quot;name&quot;`); an individual `HtmlText` is used as before
   (placeholders replaced). The texts of `FormMessages` have no trailing space (v3 constants had); the field adds one
   space and the encoded file name.
10. `FileField` is `final` (design 3.12) and `valueHasChanged()` is "has files" (v3: the value differed from `[]`).

**Security notes (v3 -> v4)**

- Unchanged level: PHP's `move_uploaded_file()` is the guard against a fake `tmp_name` (now `store()` also checks
  `is_uploaded_file()` and returns `null` instead of recording a non-existing file); the stored file is named after
  PHP's temp file (`basename(tmp_name)` plus counter), never after the client name; the pointer is sanitized (only
  `[a-zA-Z0-9_]`) before it becomes part of a path, and now also checked in the storage (`InvalidArgumentException`);
  client `type` and `name` are never trusted for decisions (v3 did not check the type either: no improvement possible
  without a feature the project must configure, so none was added; consumers should use `basename($file->name)`).
- Improvements: an empty posted `_UID` is ignored (v3 accepted `''`, which stored the files in the root directory and
  made `clearData()` remove it); `SERVER_NAME` (can come from the `Host` header) is sanitized before it becomes a
  directory name; paths read from the session are only used if they are below the root directory and have no `..`
  segment (a manipulated session cannot make `delete()` unlink other files); the file list of the session is narrowed
  (no objects from the session); manipulated `$_FILES` structures give an error instead of a `TypeError` / warnings;
  `store()` failures give an error instead of a phantom file; `mkdir` creates the root directory recursively (v3
  created it separately).
- Known and unchanged: the directory permissions follow the umask (v3 too); two users who send the same pointer share
  one directory, but each session only lists its own files; the client file name is stored as display name only.

**The bridge (changed in this task, remove in task 6)**

`FileField` overrides, all `@internal`: `readInputData(array)` (builds `FormInput::fromArray(data: [], files:
$inputData)`; the array is `$_POST + $_FILES` of `Form::validate()`, so the precedence of POST is kept by the merge),
`initializeLegacyValue()` (no-op), `getRawValue()` (returns the files), `getOriginalValue()` (`[]`), `setValue()` and
`setOriginalValue()` (throw `LogicException`). `FileField::validate(array, bool)` is the inherited base method. Task 6:
`readInputData()` becomes `readInput(FormInput)` of the new `validate(FormInput)` template, `Form::validate(?FormInput)`
builds `FormInput::fromArray(data: $methodPost ? $_POST : $_GET, files: $_FILES, query: $_GET)` (the `getUploads()`
precedence rule is already implemented), and the `getRawValue()`/`getOriginalValue()` overrides are deleted.

**Not unit tested** (and why)

`SessionFileUploadStorage::store()` on a real upload: `is_uploaded_file()` and `move_uploaded_file()` only accept files
PHP received in the HTTP request. The tests check that every other file is refused and left alone; everything else of
the storage (load/save/delete/clear/expiry, narrowing of the session, path checks, `SERVER_NAME`) uses a temp
directory. The successful path was run by hand with `curl -F` against `php -S` (this found a bug the unit tests cannot
see: the root directory was not created; fixed with a recursive `mkdir`).

**Tests changed on purpose**

`FileFieldValueTest` rewritten (the v3.3.0 tests checked the session pointer entry, `getRawValue()` and `setValue()`
with arrays; the field has no setter and no session access now; `validate(overwriteValue: false)` keeps no files).
`FormInputTest` extended (uploads). New: `FileFieldMarkupTest` (HTML of v3.3.2 for 16 validation states and the file
list, rendered with the v3.3.2 code in a scratch copy; the remove text differs on purpose, tests for English and encoded
text), `UploadedFileTest`, `SessionFileUploadStorageTest`, `tests/Double/form/InMemoryFileUploadStorage`.

**Baseline:** 16 entries removed (`FileField`), none added; no entry left in the touched files (`FormInput`,
`FileFieldRenderer`, `FileField` had none left).

**For tasks 6 and 7**

- Task 6: see "The bridge" above. `FileField::readInput()` is already the shape of the template (read pointer and
  removal request, load, apply, save); the `extra input` (`_UID`, `_removeAttachment`) is read there, not in a
  `readAdditionalInput()` hook (that sits on `TextualField`), move it when the hook moves to `FormField`.
  `FormMessagesTest` and `FileFieldMarkupTest` depend on `FormMessages::german()` texts.
- Task 7: `README.md` does not mention `FileField` (nothing to update); `UPGRADE.md` has the part 5 entry. The
  `FileFieldRenderer` is not final (extension point) and has no baseline entry; its markup still builds the file list as
  one encoded string. `FileHandler` (`src/common`) is unrelated (name clash only).

### Task 6: Rules, validation boundary and bridge removal

Design sections 3.3, 3.8 and 3.11:

- Typed rule bases (`StringRule`, `StringListRule`, `IntegerRule`, `FloatRule`, `DecimalRule`), retyped rules, the new
  count and numeric Min/Max rules, `addEachRule()`, removal of the obsolete rules (`ValueBetweenRule` dropped).
  `FormRule`, the bases and all concrete rules stay non-final. Custom rules must extend a typed base (migration
  example in design section 5). Adapt `src/form/listener/`.
- `FormField::validate(FormInput)` as `final` template and `validateCurrentValue()`; `Form::validate(?FormInput)` and
  `Form::isSent(?FormInput)` with `FormInput::fromGlobals()` as the only superglobal access in `src/form/`; the static
  `$formNameList` moves out of `Form` into the `@internal` class `FormNameRegistry` (`register(string): void`, throws
  the same `LogicException`; `reset(): void` for tests; decision 13, design 3.11), called from the `Form` constructor as
  today; placed here because it is the same `Form` clean-up as the `validate()`/`FormInput` switch. Tests that build the
  same form name twice call `FormNameRegistry::reset()`.
- Remove the bridge: `getRawValue()`, `getOriginalValue()`, `setOriginalValue()` and the bridge `setValue(mixed)`
  (typed public setters and protected `setInitialValue()` stay).
- `UPGRADE.md`: removed rules with replacements, custom rules extend a typed base (`FormRule` to `StringRule`),
  `validate()` signature. The form name check is unchanged for projects (no `UPGRADE.md` entry).

Verify: `grep -rn getRawValue src/` is empty; as task 2.

Handover notes:


Done (2026-10-04), `ddev composer check` green (1253 tests), 19 baseline entries removed (all rules), none added; 17
entries (13 blocks) remain in `src/form/`. `example/` returns 200 with "Hello World!".

**What was built**

- New rules (`actra\yuf\form\rule`, all non-final): bases `StringRule`, `StringListRule`, `IntegerRule`, `FloatRule`,
  `DecimalRule` (with `compare()`/`toLimit()` helpers, bcmath); `MinCountRule`/`MaxCountRule`, `IntegerMinRule`/
  `IntegerMaxRule`, `FloatMinRule`/`FloatMaxRule`, `DecimalMinRule`/`DecimalMaxRule` (arguments `min`/`max`/
  `minCount`/`maxCount`, `errorMessage`). Retyped: `MinLengthRule`, `MaxLengthRule`, `RegexRule`, `ValidValueRule`
  (`list<string>`, strict), `ValidEmailAddressRule` (no `setValue()`). `FormRule` only stores the message.
- Removed rules: `RequiredRule`, `FloatValueRule`, `NumericValueRule`, `NoArrayRule`, `ValueBetweenRule`,
  `MinValueRule`, `MaxValueRule`, `ValidateAgainstOptions`.
- `FormField`: no value, abstract `renderValue()`/`isValueEmpty()`/`valueHasChanged()`/`readInput(FormInput)`, hooks
  `readAdditionalInput()`, `checkRules()`, `validateChildFields()`; `final validate(FormInput)`; `validateCurrentValue()`;
  required check via `addRequiredRule()` (stores one message; a second call replaces it) and `isRequired()`.
  Typed `addRule()` on `TextualField` (`StringRule`), `SingleOptionsField` (`StringRule`), `MultiOptionsField`
  (`StringListRule`, `addEachRule(StringRule)`); `addEachRule()` on `TextAreaField`; `addValueRule()` on `IntegerField`,
  `FloatField`, `DecimalField`. Value rules run only for a parsed, non-empty value.
- `Form::validate(?FormInput)`/`isSent(?FormInput)`, `FormInput::fromGlobals(methodPost)` (the only `$_POST/$_GET/$_FILES`
  access), CSRF field validated with the same `FormInput` (second pass kept), `FormNameRegistry` (`@internal`).
- Real typed setters everywhere; `PasswordField`, `CsrfTokenField`, `FileField`, `BooleanField`, `MultiOptionsField` have
  no `setValue()`; `getAddedValues()`/`getRemovedValues()` only on `MultiOptionsField` and `FileField`.
- Toggle children: `ToggleChildren::validateSelected(FormInput)` called from the `validateChildFields()` hook.
  `FileField` reads pointer and removal request in `readAdditionalInput()`.

**Deviations (and why)**

1. `SingleOptionsField::addRule(StringRule)` was added (design lists typed `addRule()` only for text and multi fields);
   without it a rule on a select/radio field (v3 allowed any rule) would be lost.
2. `ValidValueRule` takes `list<string>` and compares strictly (v3 loose); `ValidateAgainstOptions` is deleted.
3. `FormNameRegistry` compares strictly (v3 `in_array` loose: `'1'` and `'01'` collided).
4. `validateCurrentValue()` of a toggle validates only its own value (children need the `FormInput`); v3
   `validate(..., overwriteValue: false)` also validated the children.
5. The invalid-value error of `ParsedInputField` stays in its `validateCurrentValue()` override (not moved to the read
   step); behaviour identical. `IbanNumberField`, `ZipCodeField`, `PhoneNumberField`, `CsrfTokenField` keep their
   `validateCurrentValue()` overrides.
6. `MultiOptionsField::toKeyList()` keeps a phpdoc `array<array-key, mixed>` (runtime `TypeError` guard for non-strings).
7. `grep getRawValue src/` still matches `src/table/` (`TableItemModel::getRawValue()`, unrelated).

**Bridge removed (checklist)**: `getRawValue`, `getOriginalValue`, `setOriginalValue`, `initializeLegacyValue`,
`readInputData`, `validate(array, bool)`, `validateInput`, `TextualField::setValue` (throwing), bridge `setValue(mixed)` and
`createValueTypeError`, legacy `FormField::$value`/`acceptArrayAsValue`, `FormField` `value` constructor argument,
`MultiOptionsField::setValue`, `BooleanField::setValue`, `FileField::setValue`. Only `FormNameRegistry` has `@internal`.
HTML output: unchanged (all markup tests pass).

**Tests changed on purpose**: all `validate(inputData:)` calls now `validate(input: FormInput::fromArray(...))`,
`overwriteValue: false` is `validateCurrentValue()`; removed tests for `getRawValue(true)`, `setOriginalValue()`,
`getOriginalValue()`, wrong-type `TypeError` setters (PHPStan checks now), legacy throwing setters (now "has no
`setValue()`" via reflection), `validateInput()` duplicates; `FileFieldValueTest`/`FileFieldMarkupTest` use a `request()`
helper; `FormCsrfTest`/`SpecialFieldRenderersTest` pass a `FormInput` instead of superglobals; seven form-building tests
call `FormNameRegistry::reset()` in `setUp()`. New: `FormNameRegistryTest`, `FormValidateTest`, `FormFieldListenerTest`,
`FieldRulesTest`, `rule/StringRulesTest`, `rule/NumericRulesTest`, `rule/StringListRulesTest`, `FormInputTest` (globals),
doubles `NoSpacesRule`, `RecordingFieldListener`.

**Remaining baseline in `src/form/`** (17 entries): `FormComponent` 2, `FormRenderer` 1, `FormInfo` 3, `ErrorCollection` 1,
`DefaultCollectionRenderer` 1, `DefaultFormRenderer` 1, `DefinitionListRenderer` 3, `FormControlRenderer` 1,
`FormInfoRenderer` 3, `LegendAndListRenderer` 1.

**For task 7**: fix those entries and rename the enums; `README.md` still mentions `AmountField`, `valueIsInt`,
`getRawValue()`, `addRule(new RequiredRule())`; `UPGRADE.md` part 6 is written.

Review changes (user decisions):
- `validateCurrentValue()` of `ToggleField`/`MultiToggleField` validates the children of the selected options with
  their current values again (as in v3): new hook `FormField::validateChildFieldsWithCurrentValues()` and
  `ToggleChildren::validateSelectedCurrentValues()`; inside `validate()` the children are still validated once, with
  the input (`ToggleChildrenTest`).
- The argument of `addRule()`, `addEachRule()` and `addValueRule()` keeps the v3 name `formRule`, so existing calls
  with named arguments stay valid (UPGRADE.md updated).

### Task 7: Finish `src/form/`

- Remaining baseline entries in `src/form/` (renderers, collections, `FormComponent`, `FormInfo`) fixed, enums renamed
  to `*Enum` (`InputTypeValue`, `AutoCompleteValue`, layout enums), `addError(HtmlText)`, `FormControl` "cancel" text
  from `FormMessages`. Collections and renderers stay non-final (design 3.12).
- `UPGRADE.md` `[v4.0.0]` form section complete and reviewed: every removed/renamed class, method and argument with a
  before/after example.
- `README.md` updated.

Verify: `phpstan-baseline.neon` has no entries for `src/form/`; `example/` and the yuf skeleton still work.

Handover notes:

## Out of scope (separate plans)

- Template engine (`src/template/`).
- Changes to the rendered form HTML beyond what the typed values require.
- Other areas of the library.