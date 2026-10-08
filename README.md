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

## Boolean search

`SearchHelper::createBooleanQuery()` turns a search text into a `WHERE` condition with bound parameters. Pass its
`DbQueryData` to `addWherePart()`:

```php
$data = SearchHelper::createBooleanQuery(
    spaceSeparatedFieldNames: 'person.firstName person.lastName',
    queryText: $searchTerm // e.g. 'haas +kap -"old address"'
);
$query->addWherePart(wherePart: $data->query, parameters: $data->params);
```

Every word must be contained in at least one of the fields (`LIKE '%word%'`). Words are combined with `OR`; `and`,
`or`, `not` or the shorthands `+word` and `-word` before a word change that. `"quoted phrases"` are searched as one
word, the search is case-insensitive and HTML tags are removed. `%`, `_`, `?` and `\` in the search text are searched
literally. An empty search text gives `1=1`. The field names are no user input; they are validated (column names,
optionally qualified like `table.column` or quoted with backticks) and an invalid one throws an
`InvalidArgumentException`.

## Views

By default, the view of a request is the class `<viewClassPrefix>\view\<viewGroup>\php\[<fileGroup>\]<fileTitle>`
(`ClassNameViewFactory`), created with `new $className(context: $context)`. A `Route` can instead get a
`viewFactory`; `ViewMap` maps the file name to a closure, so views have any class name and receive their dependencies.
Only the view of the current request is created; a file without a mapped view is rendered without view.

Every view receives the `ViewContext` of the request and passes it to `BaseView::__construct()`. It holds the route
(`$this->context->route`), the file group and title, the `PathVars` and the `ContentHandler`
(`$this->context->content->getContentType()`), the `LocaleHandler` (`$this->context->locale`) and the template engine
(`$this->context->templateEngine`); `BaseView::getHtmlDocument()` and `getJsonRequestBody()` give the HTML document and
the JSON request body.

```php
new Route(
    path: '/',
    viewDirectory: $core->viewDirectory,
    viewGroup: 'frontend',
    viewFactory: new ViewMap()->add(
        fileTitle: 'index',
        create: fn(ViewContext $context): BaseView => new IndexView(context: $context),
    ),
);
```

## Path variables

A file name like `subscription-42.html` is split at `-` into path variables (`0` → `subscription`, `1` → `42`); the
view allows them with `maxAllowedPathVars`. `getPathVar()` returns the untyped `?string`. The typed getters of
`BaseView` throw a `NotFoundException` (404) for a wrong URL instead of turning it into ID `0`:

```php
$id = $this->getRequiredPathVarAsInt(nr: 1);     // int, 404 if missing or not an integer
$slug = $this->getRequiredPathVarAsString(nr: 2); // trimmed string, 404 if missing or empty
$page = $this->getPathVarAsInt(nr: 3) ?? 1;       // ?int, null if missing or not an integer
```

Integers must be strictly formatted: optional minus and digits only (no `+`, no spaces, no decimals); values outside
the integer range count as not an integer.

## Clearing the session on logout

`AuthSession::logOut()` resets the login and calls `AbstractSessionHandler::clearUserData()`, so the next user of the
same browser does not see the data of the previous one (breadcrumb, table and search state, uploads, CSRF token, own
project data, …). Projects do not need to clear the session themselves.

`clearUserData()` removes everything except the data of the session handler and the preferred language. It does nothing if sessions are disabled. Call it
directly to clear the session without a logout. Data that has to survive a logout (e.g. a message for the login page)
must be written to the session after `AuthSession::logOut()`.

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

## Templates

Templates are HTML files with `tst:` tags, rendered by the `TemplateEngine`. `Core` creates one engine per request; a
view gets it as `$this->context->templateEngine`, `HtmlDocument` renders the page with it. The engine compiles a
template to PHP once and keeps it in the cache directory (below `v<format version>/`, so an upgrade never runs old
compiled code).

- Inline tag: `{tst:text value='customer.name'}` (attribute values in single quotes).
- Element tag: `<tst:text value="customer.name"/>` or `<tst:for …>…</tst:for>` (attribute values in double quotes). A
  closing tag that does not match its opening tag is an error.
- Selector (attributes that name data): `a.b.c`. The first part is a key of the template data; each further part is an
  array key, a public property, a public getter (`getB()`, `isB()`, `hasB()`) or a public method without arguments.
- Text outside of tags is copied unchanged. PHP code in a template (`<?php`) is an error.

| Tag | Attributes | Output |
|:--|:--|:--|
| `text` | `value` | The value, escaped. |
| `if` / `else` | `compare`, `operator` (`eq` default, `ne`, `gt`, `ge`, `lt`, `le`, `in`), `against` | The block when the comparison is true, else the `else` block that directly follows `</tst:if>`. |
| `for` | `value`, `var` | The body for each item of an array or iterable; `var` is only visible inside. |
| `loadSubTpl` | `tplfile` (path or `{key}`) | Another template with the same data. |
| `lang` | `key`, `vars` (optional, an array) | The text of the `LocaleHandler`; `[NAME]` is replaced by `vars['name']` (escaped). |
| `snippet` | `name` | A file of the snippets directory: `.html` as template, other files as they are. |
| `print` | `var` | Debug output of a value, escaped. |
| `date` | `format` | The current date of the clock, in the `date()` format. |
| `options` | `options`, `selected` (optional) | `<option>` elements (`<optgroup>` for nested arrays). |

`if` compares explicitly: `against="null"` is true for `null`, `''`, `[]`, `false` and `0`; `against="true"` / `"false"`
test the truthiness; any other `against` is compared as string with strings, numbers and `Stringable` values;
`gt`/`ge`/`lt`/`le` need numbers and throw a `TemplateException` otherwise.

```html
<tst:if compare="user.isAdmin" operator="eq" against="true">
    <p>{tst:lang key='welcomeAdmin'}</p>
</tst:if>
<tst:else>
    <p>{tst:text value='user.name'}</p>
</tst:else>
<tst:for value="items" var="item">
    <li>{tst:text value='item.label'}</li>
</tst:for>
<tst:snippet name="menu.html"/>
```

### Escaping

`text`, `print`, `options` and the `vars` of `lang` escape every string, number and `Stringable` value with
`htmlspecialchars()`. The replacement API says what it does: `addText()` / `HtmlText::fromText()` take plain text (escaped
once), `addHtml()` / `HtmlText::fromHtml()` take HTML built by your own code and output it as it is. Never pass user data to
`addHtml()`. Language texts and non-`.html` snippets are output as they are. Escaping is for HTML text and quoted
attributes: values in `<script>` or `<style>` are not escaped for these contexts, use `data-*` attributes or JSON
prepared by the view. yuf ships no JavaScript.

### Snippets, pagination and tables

`HtmlSnippet` renders a snippet file with replacements; the engine is passed explicitly (there is no static accessor):

```php
$snippet = HtmlSnippet::createForCurrentView(route: $this->context->route, snippetName: 'menu');
$snippet->replacements->addText(identifier: 'title', text: $title);
$html = $snippet->render(templateEngine: $this->context->templateEngine);
```

`Pagination::render()`, `TablePaginationRenderer::render()` and `TableFilter::render()` take `templateEngine:` the same
way; a `DbResultTable` takes it in its constructor (`TableHelper::createDbResultTable(identifier:, db:, selectQuery:,
templateEngine:)`) and passes it to the pagination and the filter.

### Using the engine directly

In a view, `$this->context->templateEngine` is the engine of the request. Elsewhere, with the `Core` at hand:

```php
$engine = $core->createTemplateEngine(localeHandler: $localeHandler);
$html = $engine->render(
    templateFile: $templateFile,
    data: new TemplateData(values: ['name' => $name]), // plain strings are escaped by the template
);
```

`TemplateData::fromReplacements($replacements)` takes an `HtmlReplacementCollection`. Errors are `TemplateException`s with
the template file and line. Own tags (`TemplateTag`, `TemplateTagCollection`) are planned for the next release.

## Forms

A `Form` holds fields. Every field stores and returns its value with a precise type, so no casting is needed (PHPStan
level 10 friendly). The form texts it creates itself (e.g. "The invalid input was ignored.") are English;
`FormMessages::german()` has the German texts, your own texts are named arguments of `FormMessages`.

```php
$form = new Form(name: 'order', messages: FormMessages::german());
$name = new TextField(name: 'customer', label: HtmlText::fromHtml(html: 'Name'), requiredError: $requiredError);
$quantity = new IntegerField(name: 'quantity', label: HtmlText::fromHtml(html: 'Quantity'));
$form->addField(formField: $name);
$form->addField(formField: $quantity);

if ($form->validate()) { // reads the current request, only if the form was sent
    $customer = $name->getValueAsString();
    $amount = $quantity->getValueAsInt(); // ?int, null if empty (optional field)
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

#### Optional vs. required value

The getters of the numeric, date and time fields return `null` for an empty field. For a field with a required rule
(e.g. a `requiredError` in the constructor) the value is never empty after a successful `validate()`: use the
`getRequiredValueAs...()` getter, which returns the type without `null`, so no cast, `?? 0` or own wrapper is needed.

| Field                                  | Optional field (nullable) | Required field after `validate()`            |
|:---------------------------------------|:--------------------------|:---------------------------------------------|
| `IntegerField`, `NumericField`, `HiddenIntegerField` | `?int` `getValueAsInt()` | `int` `getRequiredValueAsInt()`          |
| `FloatField`                           | `?float` `getValueAsFloat()` | `float` `getRequiredValueAsFloat()`       |
| `DecimalField`                         | `?string` `getValueAsDecimal()` | `string` `getRequiredValueAsDecimal()` |
| `DateField`                            | `?DateTimeImmutable` `getValueAsDateTimeImmutable()` | `DateTimeImmutable` `getRequiredValueAsDateTimeImmutable()` |
| `TimeField`                            | `?TimeOfDay` `getValueAsTimeOfDay()` | `TimeOfDay` `getRequiredValueAsTimeOfDay()` |

An empty field throws a `FormFieldValueMissingException` (a `LogicException`, it is a programming error): the message
says whether the field is not required (use the nullable getter) or was not validated / is empty. Input that is not
valid still throws the `UnexpectedValueException` of the nullable getter. Never returns a default value.

```php
$date = new DateField(name: 'start', label: $label, value: null, invalidError: $invalid, requiredError: $required);
if ($form->validate()) {
    $start = $date->getRequiredValueAsDateTimeImmutable(); // DateTimeImmutable
}
```

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
    label: HtmlText::fromHtml(html: 'Password'),
    requiredError: $requiredError,
    purpose: PasswordPurposeEnum::NEW // CURRENT for a login: sets autocomplete="new-password" / "current-password"
);
$price = new DecimalField(name: 'price', label: HtmlText::fromHtml(html: 'Price'), scale: 2, initialValue: '12.50');
$agree = new BooleanField(name: 'agree', label: HtmlText::fromHtml(html: 'I agree'), isCheckedByDefault: false);
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

## Clock

Time-dependent code takes a `actra\yuf\clock\Clock` (`now(): DateTimeImmutable`, the same signature as PSR-20's
`ClockInterface`, without the `psr/clock` dependency) through its constructor. Production code uses `SystemClock` (the
default everywhere); tests pass a `FixedClock`. There is no static accessor.

```php
use actra\yuf\clock\FixedClock;
use actra\yuf\core\Logger;

$logger = new Logger(
    logEmailRecipient: '',
    logDirectory: $logDirectory,
    clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05'))
);
```

## Typed database rows

`FrameworkDb::select()` returns untyped `stdClass` rows. `selectRows()` and `selectRow()` return `DbRow` objects whose
getters narrow the value once and throw a `DbRowValueException` (naming column, expected and actual type) for a missing
column, `NULL` in a non-nullable getter or a wrong type. Nothing is cast silently.

```php
use actra\yuf\db\DbRow;
use actra\yuf\db\FrameworkDb;

enum UserStatusEnum: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}

final readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public ?DateTimeImmutable $lastLogin,
        public UserStatusEnum $status,
    ) {
    }

    public static function fromRow(DbRow $row): User
    {
        return new User(
            id: $row->getInt(column: 'id'),
            name: $row->getString(column: 'name'),
            lastLogin: $row->getNullableDateTimeImmutable(column: 'last_login'),
            status: $row->getEnum(column: 'status', enumClass: UserStatusEnum::class),
        );
    }
}

final readonly class UserRepository
{
    public function __construct(private FrameworkDb $db)
    {
    }

    public function findById(int $id): ?User
    {
        $row = $this->db->selectRow(sql: 'SELECT * FROM users WHERE id = ?', parameters: [$id]);

        return $row === null ? null : User::fromRow(row: $row);
    }

    /** @return list<User> */
    public function findAll(): array
    {
        return array_map(
            callback: User::fromRow(...),
            array: $this->db->selectRows(sql: 'SELECT * FROM users ORDER BY name')
        );
    }
}
```

Getters: `getString`, `getInt`, `getFloat`, `getDecimal` (canonical decimal string like `'12.50'`, as
`DecimalField::getValueAsDecimal()`), `getBool` (`0`/`1`), `getDateTimeImmutable` (DATE, DATETIME, TIMESTAMP), `getEnum`,
each with a `getNullable...` variant (except `getBool`), and `has()`. `selectRow()` returns `null` for no row and throws
`DbRowCountException` for more than one. The same methods exist on `DbSelectStmt` (`executeAndFetchRows()`,
`executeAndFetchRow()`). Date and time columns are parsed in the PHP default time zone, so the time zone of the database
session must match it.

### Typed values in table columns

Columns of a `DbResultTable` or `SmartTable` get each row as `TableItem`. `getRow()` returns the row as `DbRow` with
the same typed getters and exceptions. `renderValue()` returns the HTML-encoded value, `getRawValue()` the untyped one.

```php
use actra\yuf\html\HtmlEncoder;
use actra\yuf\table\column\CallbackColumn;
use actra\yuf\table\TableItem;

$dbResultTable->addColumn(abstractTableColumn: new CallbackColumn(
    identifier: 'path',
    label: 'Pfad',
    callbackFunction: fn(TableItem $tableItem): string => HtmlEncoder::encode(
        value: Category::getPath(id: $tableItem->getRow()->getInt(column: 'ID'))
    )
));
```

## Documentation

For more detailed examples, please refer to:

- `.env.example.php`: Configuration examples.
- `index.example.php`: Full usage example with manual autoloader initialization.
- [UPGRADE.md](UPGRADE.md): Guide for developers updating to or working with new versions.

## Contributing

Follow the [Actra coding standard](https://github.com/Actra-AG/coding-standard) (installed as development dependency
`actra/coding-standard`) and the project-specific rules in [AGENTS.md](AGENTS.md). Every change must pass the code style
check, the static analysis (PHPStan level 10, strict) and all tests:

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