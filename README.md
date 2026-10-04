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

The easiest way to start a new project is the [yuf skeleton](https://github.com/Actra-AG/yuf-skeleton), a minimal
"Hello World" application:

```bash
composer create-project actra/yuf-skeleton my-project
```

To set up a project manually:

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

## Forms

A `Form` holds fields. Every field stores and returns its value with a precise type, so no casting is needed (PHPStan
level 10 friendly). The form texts it creates itself (e.g. "The invalid input was ignored.") are English;
`FormMessages::german()` has the German texts of yuf v3, your own texts are named arguments of `FormMessages`.

```php
$form = new Form(name: 'order', messages: FormMessages::german());
$name = new TextField(name: 'customer', label: HtmlText::encoded(textContent: 'Name'), requiredError: $requiredError);
$quantity = new IntegerField(name: 'quantity', label: HtmlText::encoded(textContent: 'Quantity'));
$form->addField(formField: $name);
$form->addField(formField: $quantity);

if ($form->validate()) { // reads the current request, only if the form was sent
    $customer = $name->getValueAsString();
    $amount = $quantity->getValueAsInt(); // ?int, null if empty
}
echo $form->render();
```

### Typed values

Each field class has the getter (and the setter) of its value type; a getter that does not fit does not exist, so a
wrong call is a PHPStan error. Call the getters after a successful validation: a value that cannot be converted (text in
a number field, a manipulated array) throws an `UnexpectedValueException` naming the field.

| Field                                                                     | Value                         | Getter                              |
|:--------------------------------------------------------------------------|:------------------------------|:------------------------------------|
| `TextField`, `EmailField`, `PhoneNumberField`, `HiddenField`, `ZipCodeField`, `IbanNumberField`, `PasswordField` | `string` | `getValueAsString()` |
| `TextAreaField`                                                           | `string`                      | `getValueAsString()`, `getValues()` |
| `IntegerField`, `NumericField`, `HiddenIntegerField`                      | `?int`                        | `getValueAsInt()`                   |
| `FloatField`                                                              | `?float`                      | `getValueAsFloat()`                 |
| `DecimalField` (money, bcmath, `scale` required)                          | `?string`, e.g. `'12.50'`     | `getValueAsDecimal()`               |
| `DateField`                                                               | `?DateTimeImmutable`          | `getValueAsDateTimeImmutable()`     |
| `TimeField`                                                               | `?TimeOfDay`                  | `getValueAsTimeOfDay()`             |
| `RadioOptionsField`, `SelectOptionsField`, `ToggleField`                  | `string` (`''` = none)        | `getValueAsString()`                |
| `CheckboxOptionsField`, `MultiSelectOptionsField`, `MultiToggleField`     | `list<string>`                | `getValues()`                       |
| `BooleanField`                                                            | `bool`                        | `isChecked()`                       |
| `FileField`                                                               | `array<string, UploadedFile>` | `getFiles()`                        |

The setters have the type of the value: `setValue(string)`, `setValue(?int)`, `setValue(?DateTimeImmutable)`,
`setValues(list<string>)`, `setChecked(bool)`. `PasswordField`, `CsrfTokenField` and `FileField` have none.
`getValues()` of a `TextAreaField` returns one entry per line (trimmed, without empty lines).

The constructor value (`value` or `initialValue`) is the initial value; `valueHasChanged()` compares the current value
with it. A subclass that fills the field after `parent::__construct()` uses the protected `setInitialValue()`.
Invalid input (a manipulated array, an option that does not exist) resets the value, adds one error and skips the other
rules. `PasswordField` is never rendered back and `CsrfTokenField` has no getter.

```php
$password = new PasswordField(
    name: 'password',
    label: HtmlText::encoded(textContent: 'Password'),
    requiredError: $requiredError,
    purpose: PasswordPurposeEnum::NEW // CURRENT for a login: sets autocomplete="new-password" / "current-password"
);
$price = new DecimalField(name: 'price', label: HtmlText::encoded(textContent: 'Price'), scale: 2, initialValue: '12.50');
$agree = new BooleanField(name: 'agree', label: HtmlText::encoded(textContent: 'I agree'), isCheckedByDefault: false);
$tags = new MultiSelectOptionsField(name: 'tags', label: $label, formOptions: $options, initialValues: ['a']);
```

### Rules

Rules are small pure predicates with a typed parameter; the field calls them for a non-empty value only. Add them with
`addRule()` (text), `addValueRule()` (`IntegerField`, `FloatField`, `DecimalField`) or `addEachRule()` (every line of a
`TextAreaField`, every key of a multi field). A custom rule extends the base that fits the value (`StringRule`,
`StringListRule`, `IntegerRule`, `FloatRule`, `DecimalRule`):

```php
class NoSpacesRule extends StringRule
{
    public function validate(string $value): bool
    {
        return !str_contains($value, ' ');
    }
}

$name->addRule(formRule: new MinLengthRule(minLength: 3, errorMessage: $tooShort));
$name->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $noSpaces));
$quantity->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $atLeastOne));
```

### Request data

`validate()` and `isSent()` read the current request. Pass a `FormInput` to validate other data, e.g. in a test (no
superglobals needed); the sent indicator (`?order`) is part of the query:

```php
$input = FormInput::fromArray(data: ['customer' => 'Ann', 'quantity' => '2'], query: ['order' => '']);
$isValid = $form->validate(input: $input);
```

`FileField` keeps uploaded files in a `FileUploadStorage` (default: session and temp directory) and the CSRF field
gets its token from a `CsrfTokenSource` (default: session). Both can be replaced with the constructor arguments
`storage` and `csrfTokenSource` of the field or `Form`. Code that upgrades from v3 finds the changes in
[UPGRADE.md](UPGRADE.md).

## Documentation

For more detailed examples, please refer to:

- `.env.example.php`: Configuration examples.
- `index.example.php`: Full usage example with manual autoloader initialization.
- [UPGRADE.md](UPGRADE.md): Guide for developers updating to or working with new versions.

## Contributing

Follow [docs/code-quality.md](docs/code-quality.md) and [AGENTS.md](AGENTS.md). Every change must pass the static
analysis (PHPStan level 10) and all tests:

```bash
composer install
composer check
```

With DDEV: `ddev start`, then prefix the commands with `ddev` (e.g. `ddev composer check`). The "Hello World" example in
`example/` (using the sources of this repository) is then available at https://yuf.ddev.site/.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---
© 2026 [Actra AG](https://www.actra.ch)