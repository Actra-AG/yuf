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

### Task 4b: Phone, zip code, IBAN, hidden and CSRF fields

`PhoneNumberField` (own `valueHasChanged()` removed), `ZipCodeField` (+ `ZipCodeValidator`), `IbanNumberField` (+
`IbanValidator`), `PhoneNumberField` on `SettableStringInputField`, `CsrfTokenField` (extends `InputField` directly, no
getter, no setter) with `CsrfTokenSource` and `SessionCsrfTokenSource`, check `PasswordField` (on `StringInputField`,
no setter, no normalization, never rendered back). Messages from `FormMessages`.

Verify: as task 2.

Handover notes:

### Task 5: `FileField`

Design section 3.11: `UploadedFile` (replaces `FileDataModel`), `UploadInput`, `FormInput::getUploads()` (narrowing of
`$_FILES` into `FormInput`), `FileUploadStorage` with `SessionFileUploadStorage` as default (the only class touching
`$_SESSION`/`$_SERVER`/clock), constants `ERRMSG_*`/`VALUE_*` removed, messages from `FormMessages`, `FileFieldRenderer`
text from `FormMessages::removeFile`, so the logic can be unit tested.

Verify: as task 2, plus unit tests with an in-memory storage double.

Handover notes:

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