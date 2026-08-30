# yuf - A Smart, Fast, and Lightweight PHP Framework

**yuf** (pronounced "[jʌf]" or "[jʊf]") is a smart, fast, and lightweight PHP framework designed with a focus on
simplicity and performance. It has zero external dependencies, other than the `actra/autoloader` library which is
required for all setups.

## Key Features

- **Extremely Lightweight**: Minimal overhead and fast execution.
- **Zero Dependencies**: Core framework functions without heavy external libraries.
- **Composer Ready**: Easy installation via Packagist.
- **Standalone Support**: Works perfectly without Composer.
- **Forced Autoloading**: Always uses the specialized `actra/autoloader` for maximum performance and control.
- **Built-in Security**: Includes features like CSP (Content Security Policy) nonce support.
- **Authentication Helpers**: Supports access rights, password login handling, and optional per-user IP whitelists.

## Requirements

- PHP 8.5 or higher
- Common PHP extensions: `mbstring`, `openssl`, `pdo`, `intl`, `bcmath`, `simplexml`, `dom`, `iconv`, `curl`, `libxml`,
  `ctype`

### Installation

Install `yuf` and `actra/autoloader` via Composer or download them manually. Note that `yuf` always requires
`actra/autoloader` to be manually initialized.

### Via Composer (Recommended)

```bash
composer require actra/yuf
```

### Manual Installation

1. Download the source code from [GitHub](https://github.com/Actra-AG/yuf).
2. Download `actra/autoloader` (https://github.com/Actra-AG/autoloader) and place it in your project.
3. Reference the `Autoloader.php` when initializing the `Core` class.

## Quick Start

1. Create a `.env.php` file based on `.env.example.php`.
2. Create an `index.php` in your document root based on `index.example.php`.
3. Initialize the Framework Core and provide the path to `Autoloader.php` if not using the default.

## Database Query Helpers

`DbQuery` can be created from an SQL query and extended dynamically before execution.

```php
$query = DbQuery::createFromSqlQuery(
    query: 'SELECT users.* FROM users WHERE users.active = ?',
    parameters: [1]
);
$query->addJoinPart(joinPart: 'LEFT JOIN groups ON groups.id = users.group_id', parameters: []);
$query->addWherePart(wherePart: 'groups.name = ?', parameters: ['admin']);
$query->addOrderPart(column: 'users.name');
```

`addJoinPart()` appends joins between the `FROM` and `WHERE` parts and accepts parameters in the same way as
`addWherePart()`. Every added part must contain exactly one parameter per `?` placeholder, and each condition added with
`addWherePart()` is wrapped in parentheses before the conditions are combined with `AND`.

`addOrderPart()` only accepts columns consisting of letters, digits, `_`, `.` and backticks, because an order column
cannot be bound as a `?` placeholder. Never pass a user-controlled value which was not checked against your own
whitelist of sortable columns.

`addOrderPart()` without `parameters` accepts column names only (one or several, separated by a comma), which are
validated and escaped because they cannot be bound as parameters. As soon as `parameters` are given, the first argument
is an SQL expression instead, e.g., to sort by the relevance of a fulltext search:

```php
$query->addWherePart(
    wherePart: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
    parameters: [$searchTerm]
);
$query->addOrderPart(
    column: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
    parameters: [$searchTerm],
    ascending: false
);
```

Such an expression is taken over unchanged and must therefore never contain user input; its values belong into
`parameters`. It must not end with `ASC` or `DESC`, because the sort direction is added according to `ascending`.
`clearOrderParts()` removes all sorting that has been added so far.

The query passed to `createFromSqlQuery()` must consist of `SELECT`, `FROM`, optional joins and an optional `WHERE`
only. `GROUP BY`, `HAVING`, `ORDER BY`, `LIMIT` and `UNION` are rejected, because sorting and paging are added by
`DbQuery` itself (`addOrderPart()` and the offset/row count of `selectFromDb()`).

## REST/API Endpoints

`yuf` includes lightweight helpers for building REST-style endpoints without adding external dependencies.

Useful backend/API features include:

- `HttpRequest::getRequestMethod()` returns a typed `RequestMethodEnum`.
- `BaseView::getJsonRequestBody()` reads and validates JSON request bodies.
- `JsonRequestBody` provides typed accessors for required and optional string, integer, float, and array values.
- `BaseView::setSuccessResponseContent()` creates standardized success responses.
- `BaseView::setErrorResponseContent()` creates standardized error responses and can set the HTTP status code.

JSON success responses use this structure:

```json
{
  "success": true,
  "data": {}
}
```

JSON error responses use this structure:

```json
{
  "success": false,
  "error": {
    "code": 0,
    "message": "Error message"
  }
}
```

Optional additional response data is returned in a top-level `data` property.

## Template Tags

`yuf` templates support custom tags for common rendering logic.

### Conditional snippet rendering

The `tst:if` tag can check whether a snippet file exists in the configured snippets directory by using
`compare="hasSnippet"`.

```html

<tst:if compare="hasSnippet" operator="eq" against="example.html">
  <tst:snippet name="example.html"/>
</tst:if>
```

The value of `against` is resolved relative to `Core::get()->snippetsDirectory`.

## Documentation

For more detailed examples, please refer to:

- `.env.example.php`: Configuration examples.
- `index.example.php`: Full usage example with manual autoloader initialization.
- [UPGRADE.md](UPGRADE.md): Guide for developers updating to or working with new versions.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---
© 2026 [Actra AG](https://www.actra.ch)