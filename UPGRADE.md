# Upgrade Guide

This document tracks relevant changes for both frontend and backend developers.

## HTML & CSS (Frontend)

### v2.2.0 – July 4, 2026

* **Template Tags:**
    * `tst:if` now supports `compare="hasSnippet"` to check whether a snippet file exists in the configured snippets
      directory.
    * This can be used together with `operator="eq"` and `against="snippet-file.html"` to conditionally render content
      only when a snippet is available.

### v1.7.0 – May 25, 2026

* **ActionsColumn Styling:**
    * The default CSS class for the table cell (`<td>`) has been changed from `action` to `td-action`.
    * If a cell contains multiple action links, they are now wrapped in a container for better styling control. The
      default CSS class for this container is `td-action-group`, but it can now be customized via the public property
      `ActionsColumn::$tdActionGroupClass`.

### v1.5.0 – May 18, 2026

* **Navigation Refinement:** `NavigationItem` property `activeCssClass` renamed to `activeSubToggleClass` and
  `inactiveCssClass` renamed to `inactiveSubToggleClass`.
* **Logic Change:** The `cssClass` property in `HtmlDataObject` is now only populated if the navigation item has
  children that the user has access to. Otherwise, it remains empty.

### v1.3.0 – May 15, 2026

* **Navigation Styling:** `NavigationItem` now supports configurable CSS classes via `activeCssClass` and
  `inactiveCssClass`.
* **BREAKING CHANGE:** The `HtmlDataObject` property `buttonClass` has been renamed to `cssClass`. Templates using this
  property must be updated.

### v1.0.7 – May 6, 2026

* **ActionsColumn Rendering:** The `<ul>` and `<li>` tags were removed. Multiple action links are now simply separated
  by a newline (`PHP_EOL`).

## Backend & API

### v3.2.0 - August 30, 2026

* **Database Query Helpers:**
    * `DbQuery::addOrderPart()` got an optional `parameters` argument (before `ascending`): with parameters, the first
      argument is an SQL expression instead of a column name, e.g. to sort by the relevance of a `MATCH() AGAINST()`
      fulltext search. Its values are bound between those of the `WHERE` part and the ones of the `LIMIT`.

      ```php
      $dbQuery->addOrderPart(
          column: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
          parameters: [$searchTerm],
          ascending: false
      );
      ```

      **Attention:** call `addOrderPart()` with named arguments, because `ascending` is no longer the second argument.
      A call like `addOrderPart($column, false)` now passes `false` to `parameters` and results in a `TypeError`.
    * `DbQuery::addOrderPart()` accepts several columns separated by a comma and applies the sort direction to each of
      them (before, only the last column of such a list was sorted descending).
    * Added `DbQuery::clearOrderParts()` to remove all sorting which has been added so far.
    * Parts added by `addJoinPart()` / `addWherePart()` are normalized to a single line, so multi-line conditions no
      longer keep their line breaks and indentation within the generated query.
    * The generated queries are checked for a matching amount of parameters and `?` placeholders before they are
      executed, so a mismatch is reported as a `LogicException` instead of a `PDOException`.
    * **Attention:** an order expression must not end with `ASC` or `DESC`, because the sort direction is added
      according to the `ascending` argument. Such an expression now throws a `LogicException` instead of producing an
      invalid `... DESC ASC`.
* **Tables:**
    * A sorting which has been chosen by the user now replaces the sorting of the given `DbQuery` instead of being
      appended to it, so a click on a sortable column header also takes effect if the query is sorted already (e.g. by
      the relevance of a fulltext search). The default sorting of the table is still appended.

### v3.1.0 - August 30, 2026

* **Database Query Helpers:**
    * Added `DbQuery::addJoinPart()` to append joins between the `FROM` and `WHERE` parts.
    * `DbQuery` now keeps join parts separately so generated result queries and total-count queries include the same
      joins. All join types (`LEFT [OUTER] JOIN`, `INNER JOIN`, `CROSS JOIN`, `NATURAL JOIN`, `STRAIGHT_JOIN`, ...)
      are recognized as one complete join clause each.
    * **Fixed:** parameters are now stored per query section, so parts added by `addJoinPart()` / `addWherePart()`
      no longer shift the parameters of the original query out of order.
    * **Fixed:** conditions added with `addWherePart()` are wrapped in parentheses, so a condition containing `OR`
      can no longer change the meaning of the other conditions.
    * **Security:** `addOrderPart()` now rejects columns containing anything other than letters, digits, `_`, `.`
      and backticks. An order column cannot be bound as a `?` placeholder, so a user-controlled value could previously
      be injected into the query. Every part of a qualified column is wrapped in backticks (e.g. `t.group` becomes
      `` `t`.`group` ``), because it could be a reserved word.
    * **Attention:** `DbQuery::createFromSqlQuery()` now throws a `LogicException` for queries containing
      `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT` or `UNION` (these silently produced wrong results, especially for
      `getTotalAmount()`), for unbalanced parentheses and for a parameter count which does not match the amount of
      `?` placeholders. The same placeholder check applies to `addJoinPart()` and `addWherePart()`.

### v3.0.0 - July 6, 2026

* **Authentication IP Whitelist:**
    * `AuthUser` now requires an `ipWhitelist` array constructor argument.
    * If the whitelist is not empty, login attempts are only accepted when the remote IP address matches the whitelist.
    * Failed whitelist checks now use `AuthResult::ERROR_IP_NOT_ALLOWED`.

* **BREAKING CHANGE:** Classes extending `AuthUser` or instantiating it must pass the new `ipWhitelist` constructor
  argument.

* **BREAKING CHANGE:** `AuthResult::renderErrorMessage()` has been removed. Use `AuthResult::render()` or handle
  user-facing error messages in application code.

### v2.1.1 - June 14, 2026

* **JSON Request Body Validation:**
    * `JsonRequestBody::getOptionalFloat()` and `JsonRequestBody::getRequiredFloat()` now also accept JSON integer
      values and cast them to floats.

### v2.1.0 - June 14, 2026

* **JSON Request Body Validation:**
    * Added `JsonRequestBody::getRequiredFloat()` and `JsonRequestBody::getOptionalFloat()` for validating float values
      in JSON request bodies.
    * Improved JSON validation error messages by including the expected value type.
    * Integer accessors now require actual JSON integer values. Numeric strings are no longer converted automatically.

### v2.0.0 - June 13, 2026

* **REST/API Endpoint Support:**
    * Added `RequestMethodEnum` with `GET`, `POST`, `PUT`, `PATCH`, and `DELETE`.
    * `HttpRequest::getRequestMethod()` now returns `RequestMethodEnum` instead of a raw string.
    * Added `RequestBody` and `JsonRequestBody` for reading and validating JSON request bodies.
    * Added `BaseView::getJsonRequestBody()` to retrieve the parsed JSON body and automatically send an error response
      for invalid JSON.
    * `BaseView::setSuccessResponseContent()` now supports immediate response sending via the `sendAndExit` parameter.
    * `BaseView::setErrorResponseContent()` now accepts an `HttpStatusCode`, optional response data, and the
      `sendAndExit` parameter.

* **BREAKING CHANGE:** Code comparing the request method as a string must now compare against `RequestMethodEnum` cases.

  Before:

    ```php
    if (HttpRequest::getRequestMethod() === 'POST') {
        // ...
    }
    ```

  After:

    ```php
    if (HttpRequest::getRequestMethod() === RequestMethodEnum::POST) {
        // ...
    }
    ```

* **BREAKING CHANGE:** JSON response envelopes changed.

  Success responses now use:

    ```json
    {
      "success": true,
      "data": {}
    }
    ```

  Error responses now use:

    ```json
    {
      "success": false,
      "error": {
        "code": null,
        "message": "Error message"
      }
    }
    ```

### v1.7.0 – May 25, 2026

* **Table Column Enhancements & Refactoring:**
    * In `AbstractTableColumn`, `cellCssClasses` and `columnCssClasses` are now `private(set)`.
    * `tableIdentifier` in `AbstractTableColumn` is now `public`, and the methods `getTableIdentifier()` and
      `setTableIdentifier()` have been removed.
    * `SmartTable::addColumn()` now sets the `tableIdentifier` property directly.
    * `AbstractTableColumn` constructor now accepts an optional `sortableColumnClass` parameter (defaulting to `sort`)
      to customize the CSS class applied to sortable columns.
    * `ActionsColumn` now has a public property `tdActionGroupClass` to customize the wrapper div class for multiple
      action links.

### v1.6.0 – May 20, 2026

* **Form Enhancements:** `SelectOptionsField` now supports custom data attributes via `addDataAttribute()`. These
  attributes are automatically rendered by `SelectOptionsRenderer`.

### v1.4.0 – May 16, 2026

* **HtmlDocument Enhancements:** Added `getActiveHtmlId()` and `listActiveHtmlIds()` to allow retrieval of active HTML
  identifiers. Refactored internal state checking for better performance.

### v1.2.0 – May 12, 2026

* **Navigation & Access Control:** Refactored `NavigationItem` and `NavigationItemCollection`. Methods now accept
  `AccessRightCollection` instead of `AuthUser` to decouple navigation from the specific user object.

### v1.0.6 – May 6, 2026

* **ActionsColumn Constants:** Added `ActionsColumn::EDIT` and `ActionsColumn::DELETE` constants for action link
  identifiers to improve type safety and extensibility.

### v1.0.0 – April 25, 2026

* **DbSettingsModel Defaults:** The constructor now provides default values for `charset` (utf8mb4),
  `timeNamesLanguage` (de_CH), and `sqlSafeUpdates` (true).
* **Routing & Layout:** Introduced `NavigationItem` and `NavigationItemCollection` for structured layout management.
  Centralized routing logic now supports custom view directories and class prefixes.

### v0.7.0 – March 15, 2026

* **Core Autoloader:** Replaced internal autoloader with `actra/autoloader`. The `Core` constructor now requires the
  path to the autoloader if not using the default location.

### v0.6.1 – March 11, 2026

* **Database (FrameworkDB):** `PDO::ATTR_STRINGIFY_FETCHES` is now set to `false`. Database results will now return
  numeric types (int/float) as their respective PHP types instead of strings.
