# Upgrade Guide

This document tracks relevant changes and upgrade instructions for developers.

---

## [v4.0.0] – unreleased

### ⚙️ Backend & API

* **Form Fields (typed values, part 1: text fields):** `TextField`, `EmailField`, `PhoneNumberField`, `HiddenField`,
  `PasswordField`, `TextAreaField` (and the fields built on them: `ZipCodeField`, `IbanNumberField`, `AmountField`,
  `DateField`, `TimeField`) now store a `string`, not `mixed`. New class hierarchy: `FormField` > `TextualField` >
  `InputField` > `StringInputField` (value, `getValueAsString()`, no public setter) > `SettableStringInputField`
  (adds `setValue(string)`).
    * ⚠️ **Setters, initial value and original value:** the public `setValue()` changes only the current value; the
      initial value (constructor value) stays, so `valueHasChanged()` compares with it. `getOriginalValue()` and
      `setOriginalValue()` are removed. To fill a field after `parent::__construct()` (e.g. with data from the database)
      a subclass calls the new protected `setInitialValue(string)` (current and initial value). It throws a
      `LogicException` as soon as `validate()` or `validateCurrentValue()` has run on the field. A project can also
      pass the value to the constructor.
      ```php
      // Before
      $field->setValue($row->name);
      $field->setOriginalValue($row->name);

      // After: constructor value (initial value) ...
      $field = new TextField(name: 'name', label: $label, value: $row->name);
      // ... or in a subclass, right after parent::__construct()
      $this->setInitialValue($customer->name);
      // change the value later (the initial value stays): setValue(string)
      $field->setValue($row->name);
      ```
    * ⚠️ **Invalid input resets the value:** array or manipulated input (`name[]=x`) is no longer ignored while the
      previous value stays. The value is reset to `''`, exactly one error "invalid input" is added and the rules do not
      run (no second "required" error). `validate()` returns `false`.
    * ⚠️ **Normalization:** `TextField` and the fields built on it (also `DateField`, `TimeField`, `ZipCodeField`,
      `IbanNumberField`, `AmountField`) store the trimmed text without zero-width spaces (U+200B), also for constructor
      values and setters. `EmailField` stores a valid address in its canonical form, `TextAreaField` and `HiddenField`
      remove only zero-width spaces (not trimmed), `PasswordField` is not normalized at all (v3 removed U+200B).
      An empty field has the value `''` (`getRawValue()` returned `null` for a missing key).
    * ⚠️ **`HiddenField` is string-only:** the constructor accepts `?string`, not `int|float|bool`
      (`new HiddenField(name: 'id', value: (string)$id, valueIsInt: true)`). `HiddenIntegerField` replaces the
      `valueIsInt` flag in a later step of v4.
    * ⚠️ **`TextAreaField` is string-only:** the array value (one entry per line) is removed. Use the string value and
      `getValues()` (trimmed lines, no empty lines):
      ```php
      // Before
      $field = new TextAreaField(name: 'ns', label: $label, value: $nameservers);   // list<string>
      $nameservers = (array)$field->getRawValue();

      // After
      $field = new TextAreaField(name: 'ns', label: $label, value: implode(PHP_EOL, $nameservers));
      $nameservers = $field->getValues();                      // list<string>
      $field->setValue(implode(PHP_EOL, $nameserversFromDatabase));
      ```
      A subclass that overrode `validate()` to parse the lines must move its checks into rules (the per-line rule
      `addEachRule()` follows in v4).
    * ⚠️ **`PasswordField`:** the free `autoComplete` argument is replaced by the required argument
      `PasswordPurposeEnum $purpose` (`CURRENT`: login or confirming the password, `NEW`: registration, password
      change, reset). The field always renders the matching `autocomplete` attribute. A password field without
      `autoComplete` needs a purpose now. The class is `final`, has no public setter and no initial value, is not
      normalized and is never rendered back (`renderValue()` is `''`).
      ```php
      // Before
      new PasswordField(name: 'pw', label: $label, requiredError: $required,
          autoComplete: AutoCompleteValue::CURRENT_PASSWORD);

      // After
      new PasswordField(name: 'pw', label: $label, requiredError: $required, purpose: PasswordPurposeEnum::CURRENT);
      ```
    * ⚠️ **`final` classes:** `EmailField`, `PasswordField` and `PhoneNumberField` are `final`. Customize them with the
      constructor, setters, rules, listeners and the renderer. `TextField`, `TextAreaField` and `HiddenField` stay
      open.
    * ⚠️ `InputFieldRenderer` takes an `InputField` only (it already read the `inputType` of the field, which an
      `OptionsField` does not have).
    * The v3.3.0 getters stay: `getValueAsString()` (now on `StringInputField` and `TextAreaField`), `getValues()` of
      `TextAreaField`, `getValueAsInt()` of `HiddenField`.
* **Form messages:** the German texts of the form code ("Die ungültige Eingabe wurde ignoriert.", "Der angegebene Wert
  ist ungültig.", ...) are no longer hard-coded. New `FormMessages` with English defaults and `FormMessages::german()`
  with the v3 texts; the form hands them to its fields.
  ⚠️ **Attention:** without the argument the fields show the English texts.
  ```php
  // Before: German texts hard-coded
  $form = new Form(name: 'contact');

  // After: keep the German texts with one line
  $form = new Form(name: 'contact', messages: FormMessages::german());
  // own texts (named arguments, the rest stays English): new FormMessages(invalidInput: 'Ungültige Eingabe.')
  ```
* **Added:** `FormInput` (request data narrowed to text, list, missing or invalid, `FormInput::fromArray()`) and
  `InputShapeEnum`; fields read their request value from it. `FormField::validateCurrentValue()` runs the listeners and
  rules without reading input.
* **Temporary (bridge):** until all fields have typed values, `getRawValue()`, `setValue(mixed)` and the array-based
  `validate(array, bool)` still exist and are marked `@internal`. They are removed in a later step of v4.

---

## [v3.3.1] – 2026-10-04

### 🎨 HTML & CSS (Frontend)

* **Form Rendering:**
    * 🩹 **Fixed (security):** `PasswordField` no longer renders the posted password back into the `value` attribute
      when a form is shown again (e.g. with validation errors), so it cannot end up in the page source or caches.
      The posted value is still available via `getValueAsString()`; the user has to type the password again.

---

## [v3.3.0] – 2026-10-04

### ⚙️ Backend & API

* **Form Field Values:**
    * Added typed value getters, so no casting of `getRawValue()` is needed anymore. Call them after a successful
      validation; they throw an `UnexpectedValueException` for values that cannot be converted.
        * `getValueAsString(): string` on `InputField` (all subclasses), `TextAreaField`, `RadioOptionsField`,
          `SelectOptionsField` and `ToggleField` (single selection; `null` becomes `''`).
        * `getValueAsInt(): ?int` and `getValueAsFloat(): ?float` on `AmountField` (so `NumericField`),
          `getValueAsInt(): ?int` on `HiddenField` (`null` for an empty value).
        * `getValues(): array` (`list<string>`) on `CheckboxOptionsField` (so `BooleanField`), `SelectOptionsField`,
          `ToggleField` and `TextAreaField`.
      ```php
      // Before
      $name = ScalarCast::toString($field->getRawValue());
      $quantity = (int)$quantityField->getRawValue();

      // After
      $name = $field->getValueAsString();
      $quantity = $quantityField->getValueAsInt(); // ?int, null if empty
      ```
    * `TextAreaField::getValues()` returns one trimmed entry per line (CRLF-safe, without empty lines). A subclass that
      parsed the lines itself in `validate()` can use it instead:
      ```php
      // Before
      $lines = array_filter(array_map('trim', preg_split('/\R/', ScalarCast::toString($field->getRawValue()))));

      // After
      $lines = $field->getValues();
      ```
    * `HiddenField` got an optional `valueIsInt` argument. Use it for IDs, e.g.
      `new HiddenField(name: 'id', value: $id, valueIsInt: true)`: manipulated input then becomes a validation error
      instead of an exception in `getValueAsInt()`.
    * Added `actra\yuf\form\AmountParser` (accepted number formats, used by the amount rule and the numeric getters).
    * 🩹 **Fixed:** `DateField::getValueAsDateTimeImmutable()` no longer throws a `TypeError` for an empty field
      (`null`), it returns `null`.
    * 🩹 **Fixed:** `PhoneNumberField` and `ZipCodeField` no longer throw a `TypeError` for array input (`name[]=x`).
      An array phone value is rejected with the normal validation error, an array country code is ignored (the
      current country code stays).
    * 🩹 **Fixed:** `ValidAmountRule` (`AmountField`, `NumericField`) accepted decimals in integer fields.
      ⚠️ **Attention:** input that was accepted before is now a validation error:
        * Integer fields (`valueIsFloat: false`, `NumericField`) reject `'1.5'`, `'1.0'`, `'1.'`, `'.5'` and `'1e3'`.
        * Float fields reject exponent notation (`'1e3'`, `'1.5E-3'`).
        * Values outside the `int` range (integer fields) or too large for a `float` are rejected.
    * `AmountField` and `NumericField` now store posted input trimmed (`' 12 '` becomes `'12'`, also in
      `getRawValue()`). Surrounding whitespace is still accepted.
    * ⚠️ **Possible conflicts:** project subclasses that already declare one of the new methods with another
      signature cause a fatal error. Rename them or adjust the signature.
        * Public: `getValueAsString(): string`, `getValueAsInt(): ?int`, `getValueAsFloat(): ?float`,
          `getValues(): array`.
        * Protected on `FormField`: `getValueAsStringOrFail()`, `getValueAsIntOrFail()`, `getValueAsFloatOrFail()`,
          `getValuesAsStringListOrFail()`.
    * **Outlook:** `getRawValue()` and the `mixed` value storage of `FormField` will be removed in v4. The new getters
      stay; use them in new code.

---

## [v3.2.2] – 2026-09-12

### ⚙️ Backend & API

* **Phone Number Formatting:**
    * 🩹 **Fixed:** Anchored regex evaluation in `PhoneMatcher` now uses the `A` (`PCRE_ANCHORED`) modifier, ensuring
      alternation patterns in metadata-leading digits are anchored strictly to the start of the string without false
      positives.

---

## [v3.2.1] – 2026-09-10

### 🎨 HTML & CSS (Frontend)

* **Form Rendering:**
    * 🩹 **Fixed:** Optional `SelectOptionsField` instances now render their empty option without a visible label by
      default.
    * The default empty option label for required `SelectOptionsField` instances changed from German to English
      (`-- Please select --`).

---

## [v3.2.0] – 2026-08-30

### ⚙️ Backend & API

* **Database Query Helpers:**
    * `DbQuery::addOrderPart()` got an optional `parameters` argument (before `ascending`): with parameters, the first
      argument is an SQL expression instead of a column name (e.g. to sort by `MATCH() AGAINST()`). Its values are bound
      between those of the `WHERE` part and the ones of the `LIMIT`.
      ```php
      $dbQuery->addOrderPart(
          column: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
          parameters: [$searchTerm],
          ascending: false
      );
      ```
      ⚠️ **Attention:** call `addOrderPart()` with named arguments, because `ascending` is no longer the second
      argument. A call like `addOrderPart($column, false)` now passes `false` to `parameters` and results in a
      `TypeError`.
    * `DbQuery::addOrderPart()` now accepts several columns separated by a comma and applies the sort direction to each.
    * Added `DbQuery::clearOrderParts()` to remove all sorting.
    * Parts added by `addJoinPart()` / `addWherePart()` are normalized to a single line (no more line
      breaks/indentation).
    * Generated queries are checked for parameter/placeholder match before execution, throwing a `LogicException`
      instead of a `PDOException`.
    * ⚠️ **Attention:** An order expression must not end with `ASC` or `DESC` (sort direction is added automatically).
      This now throws a `LogicException`.
* **Tables:**
    * User-chosen sorting now replaces the sorting of the given `DbQuery` instead of being appended to it (e.g. clicking
      a column header works even if pre-sorted by relevance).

---

## [v3.1.0] – 2026-08-30

### ⚙️ Backend & API

* **Database Query Helpers:**
    * Added `DbQuery::addJoinPart()` to append joins between `FROM` and `WHERE` parts.
    * `DbQuery` keeps join parts separately so result and count queries include identical joins. All join types
      (`LEFT JOIN`, `INNER JOIN`, etc.) are recognized as one clause.
    * 🩹 **Fixed:** Parameters are stored per query section. `addJoinPart()` / `addWherePart()` no longer shift
      parameters of the original query.
    * 🩹 **Fixed:** Conditions added with `addWherePart()` are wrapped in parentheses to prevent `OR` conditions from
      breaking other clauses.
    * 🔒 **Security:** `addOrderPart()` now strictly rejects columns containing characters other than letters, digits,
      `_`, `.` and backticks. Fully qualified columns are wrapped in backticks (e.g. `t.group` becomes
      `` `t`.`group` ``).
    * ⚠️ **Attention:** `DbQuery::createFromSqlQuery()` now throws a `LogicException` for queries containing `GROUP BY`,
      `HAVING`, `ORDER BY`, `LIMIT` or `UNION` (which produced wrong results in `getTotalAmount()`), unbalanced
      parentheses, or parameter mismatches.

---

## [v2.2.0] – 2026-07-04

### 🎨 HTML & CSS (Frontend)

* **Template Tags:**
    * `tst:if` now supports `compare="hasSnippet"` to check whether a snippet file exists in the configured directory.
    * Can be used together with `operator="eq"` and `against="snippet-file.html"` to conditionally render content.

---

## [v2.1.1] – 2026-06-14

### ⚙️ Backend & API

* **JSON Request Body Validation:**
    * `JsonRequestBody::getOptionalFloat()` and `JsonRequestBody::getRequiredFloat()` now accept integers and cast them
      to floats.

---

## [v2.1.0] – 2026-06-14

### ⚙️ Backend & API

* **JSON Request Body Validation:**
    * Added `JsonRequestBody::getRequiredFloat()` and `JsonRequestBody::getOptionalFloat()`.
    * Improved error messages by including the expected type.
    * Integer accessors now require actual JSON integers (numeric strings are no longer cast automatically).

---

## [v2.0.0] – 2026-06-13

### 🚨 Breaking Changes

* **Request Method Verification:** Code comparing the request method as a string must now compare against
  `RequestMethodEnum` cases.
    * *Before:* `if (HttpRequest::getRequestMethod() === 'POST')`
    * *After:* `if (HttpRequest::getRequestMethod() === RequestMethodEnum::POST)`
* **JSON Response Envelope:** Success and error envelopes changed structure:
    * *Success:* `{"success": true, "data": {}}`
    * *Error:* `{"success": false, "error": {"code": null, "message": "..."}}`

### ⚙️ Backend & API

* **REST/API Endpoint Support:**
    * Added `RequestMethodEnum` with `GET`, `POST`, `PUT`, `PATCH`, and `DELETE`.
    * `HttpRequest::getRequestMethod()` now returns `RequestMethodEnum` instead of a string.
    * Added `RequestBody` and `JsonRequestBody` for JSON body validation.
    * Added `BaseView::getJsonRequestBody()` and immediate response controls (`sendAndExit`).

---

## [v1.7.0] – 2026-05-25

### 🎨 HTML & CSS (Frontend)

* **ActionsColumn Styling:**
    * Default table cell (`<td>`) class changed from `action` to `td-action`.
    * Multiple links are now wrapped in a container with the default class `td-action-group` (customizable via
      `ActionsColumn::$tdActionGroupClass`).

### ⚙️ Backend & API

* **Table Column Enhancements:**
    * In `AbstractTableColumn`, `cellCssClasses` and `columnCssClasses` are now `private(set)`.
    * `tableIdentifier` is now a public property; `getTableIdentifier()` and `setTableIdentifier()` were removed.
    * `SmartTable::addColumn()` sets `tableIdentifier` directly.
    * Added `sortableColumnClass` constructor parameter (defaulting to `sort`).

---

## [v1.6.0] – 2026-05-20

### ⚙️ Backend & API

* **Form Enhancements:** `SelectOptionsField` supports custom data attributes via `addDataAttribute()`, rendered
  automatically by `SelectOptionsRenderer`.

---

## [v1.5.0] – 2026-05-18

### 🎨 HTML & CSS (Frontend)

* **Navigation Refinement:** `NavigationItem` property `activeCssClass` renamed to `activeSubToggleClass` and
  `inactiveCssClass` to `inactiveSubToggleClass`.
* **Logic Change:** `cssClass` in `HtmlDataObject` is only populated if the user has access to the item's children.

---

## [v1.4.0] – 2026-05-16

### ⚙️ Backend & API

* **HtmlDocument Enhancements:** Added `getActiveHtmlId()` and `listActiveHtmlIds()`.

---

## [v1.3.0] – 2026-05-15

### 🚨 Breaking Changes

* **HtmlDataObject:** Property `buttonClass` renamed to `cssClass`. Templates using this property must be updated.

### 🎨 HTML & CSS (Frontend)

* **Navigation Styling:** `NavigationItem` now supports configurable CSS classes via `activeCssClass` and
  `inactiveCssClass`.

---

## [v1.2.0] – 2026-05-12

### ⚙️ Backend & API

* **Navigation & Access Control:** Refactored `NavigationItem` and `NavigationItemCollection` to accept
  `AccessRightCollection` instead of `AuthUser`.

---

## [v1.0.7] – 2026-05-06

### 🎨 HTML & CSS (Frontend)

* **ActionsColumn Rendering:** Removed `<ul>` and `<li>` tags. Multiple action links are now separated by a newline
  (`PHP_EOL`).

---

## [v1.0.6] – 2026-05-06

### ⚙️ Backend & API

* **ActionsColumn Constants:** Added `ActionsColumn::EDIT` and `ActionsColumn::DELETE` constants.

---

## [v1.0.0] – 2026-04-25

### ⚙️ Backend & API

* **DbSettingsModel Defaults:** Constructor now defaults to `utf8mb4` charset, `de_CH` language, and
  `sqlSafeUpdates = true`.
* **Routing & Layout:** Introduced structured layout/routing with custom view directories and class prefixes.

---

## [v0.7.0] – 2026-03-15

### ⚙️ Backend & API

* **Core Autoloader:** Replaced internal autoloader with `actra/autoloader`.

---

## [v0.6.1] – 2026-03-11

### ⚙️ Backend & API

* **Database (FrameworkDB):** `PDO::ATTR_STRINGIFY_FETCHES` is now set to `false`. Database results now return native
  PHP types (`int`/`float`) instead of strings.