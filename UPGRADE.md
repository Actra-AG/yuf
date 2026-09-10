# Upgrade Guide

This document tracks relevant changes and upgrade instructions for developers.

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