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
  `ctype`, `fileinfo`

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

1. Create a `.env.php` file based on `.env.example.php` (see "Environment settings" below).
2. Create an `index.php` in your document root based on `index.example.php`.
3. Create the application with `Core::fromEnvironment()` (the arguments `envFilePath:` and `copyrightYear:` are
   required; `autoloaderPath:` and the directories have defaults) and send the response of
   `prepareHttpResponse()`:

```php
require __DIR__ . '/../vendor/actra/yuf/src/Core.php';
$core = Core::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026);
$core->prepareHttpResponse(routeCollection: $routes)->sendAndExit();
```

`fromEnvironment()` does everything global, once per process: it registers the autoloader and the error handler, reads
the environment file, sets `error_reporting()` and the time zone, creates the directories and the request from the PHP
globals. Tests build `Core` directly with `new Core(settings: new CoreSettings(…), httpRequest: …, responseSender: …)`,
which touches no globals. A request without HTTPS gets the redirect to HTTPS as response of `prepareHttpResponse()`.

## Environment settings

`.env.php` returns an array; `Core` checks it once when it starts and throws an `UnexpectedValueException` that names
the key if a setting is missing or has the wrong type. `Core` reads these keys:

| Key                     | Type           | Meaning                                               |
|:------------------------|:---------------|:------------------------------------------------------|
| `defaultErrorReporting` | `int`          | Optional, default `E_ALL`: `error_reporting()` level  |
| `defaultTimeZone`       | `string`       | PHP time zone, e.g. `Europe/Zurich`                   |
| `allowedDomains`        | `list<string>` | Host names the application answers to (else 404)      |
| `logEmailRecipient`     | `string`       | Mail address of new errors, empty for no mails        |
| `debug`                 | `bool`         | Shows the debug page for errors                       |
| `robots`                | `string`       | Content of the `robots` meta tag                      |

Own keys of a project (flat, used as given, e.g. `'mailer.hostname'`) are read from `$core->environmentSettings` with
`getString()`, `getInt()`, `getBool()` and `getStringList()` (`has()` tells if a key exists); a missing key or a wrong
type throws an `UnexpectedValueException` naming the key and the expected type. Pass the settings to your own settings
class instead of reading them statically.

## Production settings

- `opcache.validate_timestamps=0`: PHP does not check the files for changes on every request. Reset the opcache on every
  deployment (restart PHP-FPM or call `opcache_reset()`), else the old code keeps running. This includes the compiled
  templates in `app/cache/`: a changed template is compiled again, but PHP keeps running the old compiled file until
  the reset.
- Optional: `opcache.preload` with a script that loads the classes of yuf and your application.
- With PHP-FPM, yuf calls `fastcgi_finish_request()` after the response is sent: the client has the response before
  the destructors and the shutdown functions run (the session is already written and closed after the view, see
  [Session](#session)). Nothing can be output after the response.

## Error log

`Core` logs every exception with `FileLogger` (`app/logs/ticket_<hash>.txt`, one file per distinct issue; a new issue is
mailed to `logEmailRecipient`). The entry describes the request without secrets: request line (method and path, no query
string), host, IP address, user agent, referrer (without query string), a fixed list of server variables, the query and
post parameters, the uploaded files (name, type, size) and the cookie *names*. Values of parameters whose name contains
`password`, `token`, `secret`, `csrf`, `key` or `auth` (case-insensitive, at any depth) are replaced by `***`. A project
that wants another destination implements the interface `actra\yuf\core\Logger` and passes it as
`prepareHttpResponse(logger: …)`.

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

`SearchQueryBuilder::createBooleanQuery()` turns a search text into a `WHERE` condition with bound parameters. Pass its
`DbQueryData` to `addWherePart()`:

```php
$data = SearchQueryBuilder::createBooleanQuery(
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
(`$this->context->templateEngine`), the `HttpRequest` (`$this->context->httpRequest`), the `Session`
(`$this->context->session`, `null` without sessions), the `AuthSession` (`$this->context->authSession`) and the
`FormContext` for forms (`$this->context->formContext`); `BaseView::getHtmlDocument()`
and `getJsonRequestBody()` give the HTML document and the JSON request body.

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

## The request

`HttpRequest` is an immutable snapshot of the request, created once per request by `Core` from the superglobals
(`HttpRequest::fromGlobals()`) and available as `$core->httpRequest` and `$this->context->httpRequest`. Nothing in yuf
reads a superglobal afterwards; everything that needs the request gets it as argument or constructor dependency. In
tests, build a request with the constructor (`new HttpRequest(host: 'example.com', method: …, queryParameters: …)`).
`fromGlobals()` is meant for `Core`, bootstrap files and CLI scripts, not for logic classes.

```php
$httpRequest = $this->context->httpRequest;
$httpRequest->getMethod();              // RequestMethodEnum (GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS)
$httpRequest->getPath();                // '/de/page.html', without the query string
$httpRequest->getUrl();                 // 'https://www.example.com/de/page.html?a=1'
$httpRequest->getProtocol();            // ProtocolEnum::HTTPS
$httpRequest->getRemoteAddress();       // client IP address
$httpRequest->getHeader(name: 'Accept'); // ?string, header names are case-insensitive
$httpRequest->getBearerToken();         // ?string
$httpRequest->getCookie(name: 'theme'); // ?string
$httpRequest->getBody();                // raw body, e.g. for JSON
```

Input is never merged: `getQueryString()`, `getQueryInteger()`, `getQueryFloat()`, `getQueryArray()` and
`hasQueryValue()` read the query string, the `getPost…()` methods the posted form data. Strings are trimmed; a missing
value is `null`. Integers and floats must be complete numbers (`'12'`, `'-1.5'`, `'1e3'`): `'12abc'`, `''` and `'1.5'`
(as integer) give `null`. Uploads: `getFile()` for a field with one file, `getFiles()` for any field (a list of
normalized entries).

A view declares the source of each input parameter; `getInputString()`, `getInputInteger()`, `getInputFloat()` and
`getInputArray()` read from it, and a required parameter is checked there:

```php
$inputParameters = new InputParameterCollection();
$inputParameters->add(inputParameter: new InputParameter(name: 'page', source: InputSourceEnum::QUERY, isRequired: false));
$inputParameters->add(inputParameter: new InputParameter(name: 'title', source: InputSourceEnum::POST, isRequired: true));
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

## Session

`Core::prepareHttpResponse()` creates the session handler and one `Session` per request: `$core->session` and
`$this->context->session` in a view (`null` with `individualSessionHandler: false`, the example app runs without
sessions). The `Session` is the only way to read and write session data: **projects must not use `$_SESSION`**, also
not for their own data (cart, order data, flash messages, `requestedPageAfterLogin`). Only `NativeSessionStorage` and
the session handler touch `$_SESSION`.

The PHP session starts lazily, on the first access of the `Session` (read or write, also through a form with CSRF
protection or `AuthSession`). A request that never uses it takes no lock, sends no session cookie and creates no
session file. A route with a language remembers it as the preferred language only for visitors who have a session
(`Session::isActive()`: started in this request, or the request carries a session cookie with a valid ID); the request
of `/` reads it only from such a session, else it uses the browser language. A visitor without a session cookie gets no
session for the language, until something else starts one (e.g. a login or a form with CSRF protection). `Core::prepareHttpResponse()` writes and closes a started
session after the view, before the response is built: the lock is released early, so parallel requests of the user do
not wait for each other. Afterwards the session can still be read, but every write (`set()`, `remove()`,
`regenerateId()`, `clearUserData()`, `AuthSession::logIn()`, a new CSRF token, …) throws a `LogicException`, as does a
first access after the response was sent: write the session while the view runs, not in a destructor or a shutdown
function. A view that runs long (an export, a report) calls `$this->context->session?->close()` after its last write
to release the lock earlier.

```php
$session = $this->context->session;                    // ?Session
$session?->set(key: 'cart', value: ['items' => 3]);    // string|int|float|bool|array|null, arrays recursively
$items = $session?->getArray(key: 'cart');             // getString(), getInt(), getFloat(), getBool()
$session?->has(key: 'cart');
$session?->remove(key: 'cart');
```

The typed getters return `null` for a missing key or a value of another type and never write. Objects are rejected (no
serialization surprises). The key `yuf` is reserved for yuf. `getId()`, `regenerateId()`, `close()` and `export()` (all
data, for the debug page) complete the API; tests and scripts build a `Session` on an `ArraySessionStorage` (`new
Session(storage: new ArraySessionStorage())`).

All data of yuf lives below `$_SESSION['yuf']` in documented sections (`SessionSectionEnum`): `handler` (session
handler, preferred language), `auth` (login), `csrf` (token), `tables` (sorting and page), `tableFilters`, `search`
and `uploads`. Own data is stored next to it and cannot collide with it.

`AuthSession` (`$this->context->authSession`) holds the login: `logIn()`, `logOut()`, `isLoggedIn()` and
`getAuthSessionId()` (throws a `LogicException` when nobody is logged in). `AuthSession::logOut()` resets the login
and calls `Session::clearUserData()`, so the next user of the same browser does not see the data of the previous one
(breadcrumb, table and search state, uploads, CSRF token, own project data, …). Projects do not need to clear the
session themselves. `clearUserData()` removes everything except the section `handler` (data of the session handler and
the preferred language). Call it directly to clear the session without a logout. Data that has to survive a logout
(e.g. a message for the login page) must be written to the session after `AuthSession::logOut()`.

`AuthSession::logIn()` gives the session a new ID too (session fixation), so a session ID the visitor had before the login
is worthless. The session handler only accepts session IDs it issued itself (`validateId()`), binds a session to the
address and user agent of its client and replaces it, empty, if one of them changes or the session is expired.

### Passwords

`Password::generateNew()` hashes with `password_hash()` (Argon2id; `PASSWORD_DEFAULT` without Argon2): store `salt`
(empty) and `hash` (at least 255 characters). Passwords of the earlier salt-and-SHA-256 format are still verified; the
`Authenticator` replaces them (and hashes with weaker costs) after a successful password login through
`AuthUser::rehashPassword()` / `dbUpdatePassword()`. Show one message for every failed login, whether the user name is
unknown or the password wrong (`Authenticator::$authResult` is for the log, not for the user).

`Password` is for passwords that humans choose. For random secrets the application generates (API keys, reset links,
remember-me tokens) use `SecretTokenHash`: `SecretTokenHash::generate()` returns the secret (32 random bytes, base64url,
show it once) and its hash (SHA-256, 64 hex characters, store it); `new SecretTokenHash(hash: $stored)->isValid(secret:
$given)` checks it with `hash_equals()`. A fast hash is safe because a 256 bit secret cannot be guessed, and an Argon2id
check on every API request would cost 50 ms and 64 MB; for a password with little entropy a fast hash would be cracked
quickly. Never use it for something a human types.

Identifiers are session keys: **form names, table identifiers, filter identifiers and the instance names of
`SearchState` must be unique per page.** Two tables with the same identifier share their sorting and page, two forms
with the same name share the sent indicator. yuf does not check this.

Without a session (`$context->session === null`) forms and the table filter do without CSRF protection: CSRF needs a
session cookie that the browser sends along on its own, without a session there is nothing to abuse. The `FormContext`
has no `CsrfTokenSource` then, so a form has no CSRF field and checks no token, and a table filter accepts its posted
input without a token. Tables, `SearchState` and upload storage need a `Session` (build one on an
`ArraySessionStorage` if they are used without sessions, the state then lives for one request).

## REST/API Endpoints

`yuf` includes lightweight helpers for building REST-style endpoints without adding external dependencies.

Useful backend/API features include:

- `$this->context->httpRequest->getMethod()` returns a typed `RequestMethodEnum`.
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

`Pagination::render()`, `TablePaginationRenderer::render()` and `TableFilter::render()` take `templateEngine:` the
same way; a `DbResultTable` takes it in its constructor (`TableHelper::createDbResultTable(identifier:, db:,
selectQuery:, templateEngine:, httpRequest:, session:)`) and passes it to the pagination and the filter. The table
reads sorting and page from the query string of the request and remembers them in the session; a `TableFilter` (`new
TableFilter(identifier:, httpRequest:, session:, csrfTokenSource:)`, the token source is
`$this->context->formContext->csrfTokenSource`) takes the filter values from the posted data (with a valid CSRF token,
if there is a token source) and remembers them in the session.

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
the template file and line.

### Own template tags

A project adds its own tags by implementing `TemplateTag` (the extension point of the engine) and passing them to
`Core::prepareHttpResponse()`. Views, snippets, tables and the error pages know them.

```php
final readonly class PriceTag implements TemplateTag
{
    public function getName(): string
    {
        return 'price';
    }

    public function render(TemplateTagContext $context, array $attributes, ?Closure $body): string
    {
        $amount = $context->resolve(selector: $context->requireAttribute(attributes: $attributes, name: 'value'));
        $text = number_format(num: (float) $context->text(value: $amount), decimals: 2, thousands_separator: "'");
        $html = $context->escape(value: $text . ' CHF');

        return $body === null ? $html : '<span class="price">' . $html . $body() . '</span>';
    }
}

$core->prepareHttpResponse(
    routeCollection: $routes,
    templateTags: [new PriceTag()],
);
```

```html
{tst:price value='article.price'}
<tst:price value="article.price">(incl. VAT)</tst:price>
```

- `render()` returns HTML which is output as it is: the tag is responsible for escaping. Use `$context->escape()` for
  every value that is not HTML; `$context->text()` gives a value as plain text for calculations and paths.
- `$attributes` are the strings as written in the template. A selector is resolved with `$context->resolve()`;
  `$context->requireAttribute()` throws a `TemplateException` for a missing attribute.
- `$body` renders the children of an element tag, `null` for inline tags and `<tst:price/>`.
- Dependencies come through the constructor; a tag must not use static state.
- A name of a built-in tag, of another own tag, `if`, `else` or `for` throws an `InvalidArgumentException` in
  `prepareHttpResponse()`.

## Forms

A `Form` holds fields. Every field stores and returns its value with a precise type, so no casting is needed (PHPStan
level 10 friendly). The form texts it creates itself (e.g. "The invalid input was ignored.") are English;
`FormMessages::german()` has the German texts, your own texts are named arguments of `FormMessages`.

```php
$form = new Form(context: $this->context->formContext, name: 'order', messages: FormMessages::german());
$name = new TextField(name: 'customer', label: HtmlText::fromHtml(html: 'Name'), requiredError: $requiredError);
$quantity = new IntegerField(name: 'quantity', label: HtmlText::fromHtml(html: 'Quantity'));
$form->addField(formField: $name);
$form->addField(formField: $quantity);

if ($form->validate()) { // reads the request of the context, only if the form was sent
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

### Extending the form classes

The classes a project builds on are documented extension points: `Form` (one subclass per form), `TextField`,
`TextAreaField`, `SelectOptionsField`, `CheckboxOptionsField`, `RadioOptionsField`, `BooleanField`, `IntegerField` and
`FormControl` for fields with a fixed meaning (name, search query, ...), and the abstract bases `FormComponent`,
`FormField`, `FormRenderer`, `FormFieldListener` and the rule bases (`StringRule`, ...). Every other class of the form
code is `final`: customize it through its constructor, setters, rules, listeners and `setRenderer()`.

Renderers build their tags with `HtmlTagAttribute::fromText()` (plain text, escaped), `fromHtml()` (encoded or trusted
HTML, as it is) and `fromName()` (no value, `required`):

```php
new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: [
    HtmlTagAttribute::fromText(name: 'placeholder', text: 'Name & "Vorname"'),
    HtmlTagAttribute::fromName(name: 'required'),
]);
```

### Request data

`validate()` and `isSent()` take the request data as `FormInput`. In a view, build it from the request of the view
(`methodPost` is the method of the form); to validate other data, e.g. in a test, use `FormInput::fromArray()`. The sent
indicator (`?order`) is part of the query:

```php
$input = FormInput::fromHttpRequest(httpRequest: $this->context->httpRequest, methodPost: true);
$isValid = $form->validate(input: $input);

$input = FormInput::fromArray(data: ['customer' => 'Ann', 'quantity' => '2'], query: ['order' => '']);
$isValid = $form->validate(input: $input);
```

A form gets the request and the CSRF token source from its `FormContext` (`$this->context->formContext`; a project that
needs another source builds its own `FormContext`). `validate()` and `isSent()` read the input from the request of the
context (the posted data, for a GET form the query string); the `input:` argument is optional. The form names of a page
must be unique (see [Session](#session)).

`FileField` keeps uploaded files in a `FileUploadStorage` (the production one is
`SessionFileUploadStorage::forHttpRequest(session:, httpRequest:)`: session and temp directory); the CSRF field gets its
token from the `CsrfTokenSource` of the `FormContext` (`SessionCsrfTokenSource` on the session, none without session).
The storage is a required argument of the field. So is the allow-list `allowedFileTypes:` (`UploadFileType::pdf()`,
`jpeg()`, `png()`, `gif()`, `webp()`, `plainText()`, `csv()`, `docx()`, `xlsx()`, `pptx()`, `zip()`, or
`new UploadFileType(mimeTypes:, extensions:)`; no SVG, it can carry scripts). Every upload is checked before it is
stored: `maxFileSize:` (bytes, 10 MB by default) and the type, which is detected from the file content (`finfo`, never
taken from the client) and has to fit the extension of the file name. `UploadedFile::$type` is the detected type.
`FileFieldRenderer` renders an `accept` attribute with the allowed extensions. Code that upgrades from v3 finds the
changes in [UPGRADE.md](UPGRADE.md).

### Rules for forms

- Every form gets the `FormMessages` of the route language; without `messages:` the texts of yuf are English.
- Initial values go into the constructor (`value:`, `initialValue:`, `initialValues:`, `isCheckedByDefault:`) or, in a
  field subclass, into the protected `setInitialValue()` (`setInitialValues()`, `setInitiallyChecked()`). The public
  setters change the current value only; `valueHasChanged()` compares with the initial value.
- `PasswordField` gets the purpose of the input: `CURRENT` for a login and for confirming the current password, `NEW`
  for setting a password.
- Checks of a field are rules (see [Rules](#rules)), never overrides of `checkRules()` or `validateCurrentValue()`.
- An error message with user input is `HtmlText::fromText()` (escaped); `HtmlText::fromHtml()` is for trusted HTML only.

```php
$form = new Form(context: $this->context->formContext, name: 'login', messages: FormMessages::german());
$quantity = new IntegerField(name: 'quantity', label: $label, initialValue: $order->quantity);
$password = new PasswordField(
    name: 'password',
    label: $label,
    requiredError: $required,
    purpose: PasswordPurposeEnum::CURRENT,
);
$recipients->addEachRule(formRule: new ValidEmailAddressRule(errorMessage: $invalidAddress)); // TextAreaField
$name->addError(errorMessage: HtmlText::fromText(text: 'The user "' . $userName . '" already exists.'));
```

## Phone numbers

`PhoneNumber::createFromString(input:, defaultCountryCode:)` parses a number in national (`044 123 45 67`, with the
region, e.g. `'CH'`) or international notation (`+41 44 123 45 67`, with an extension) and throws a `PhoneParseException`
if its length is not possible. The metadata is a port of [libphonenumber](https://github.com/google/libphonenumber)
(Apache 2.0).

```php
$phoneNumber = PhoneNumber::createFromString(input: '044 123 45 67', defaultCountryCode: 'CH');

PhoneRenderer::renderE164Format(phoneNumber: $phoneNumber);          // +41441234567 (no extension)
PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber); // +41 44 123 45 67
PhoneRenderer::renderNationalFormat(phoneNumber: $phoneNumber);      // 044 123 45 67
PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber);      // +41.441234567 (for storing)
```

The national format has the national prefix as the country writes it (`030 123456` in Germany, `(650) 253-0000` in the
US). International and national format end with the extension (` ext. 12`).

A possible number has a length that exists in its country; a valid number also matches the numbers of the country.
`$phoneNumber->isValid()` tells it, `$phoneNumber->getType()` returns the `PhoneNumberTypeEnum` (`FIXED_LINE`, `MOBILE`,
`TOLL_FREE`, `PREMIUM_RATE`, `SHARED_COST`, `VOIP`, `PERSONAL_NUMBER`, `PAGER`, `UAN`, `VOICEMAIL`) or `null` for a number
that is not valid. `FIXED_LINE_OR_MOBILE` is returned where both cannot be told apart (e.g. in the US);
`isValidForType(numberType:)` accepts such a number as `FIXED_LINE` and as `MOBILE`.

`PhoneNumberField` accepts valid numbers only (`isValid()`), not numbers that are merely possible; otherwise it adds
`invalidErrorMessage:`. With `allowedNumberTypes:` (list of `PhoneNumberTypeEnum`) the number also has to be of one of
the types, otherwise the field adds `numberTypeErrorMessage:` (default: the
`invalidErrorMessage:`):

```php
new PhoneNumberField(
    name: 'mobile',
    label: HtmlText::fromText(text: 'Mobile'),
    value: null,
    invalidErrorMessage: HtmlText::fromText(text: 'Invalid number'),
    allowedNumberTypes: [PhoneNumberTypeEnum::MOBILE],
    numberTypeErrorMessage: HtmlText::fromText(text: 'Please enter a mobile number'),
);
```

## Sending mail with SMTP

`SmtpMailer` sends through an SMTP server. With `useTls: true` (default) the connection is encrypted with STARTTLS
before the credentials are sent; a server without STARTTLS aborts the delivery. With a user name the mailer
authenticates, with the method that the server announces in the `AUTH` line of its answer to `EHLO`:

- `PLAIN`, otherwise `LOGIN` (user name and `smtpPassword:`); a server that announces no method gets `LOGIN`.
- `XOAUTH2` (Microsoft 365, Gmail) when you pass an `OAuthTokenProvider`: its `getAccessToken()` returns the OAuth 2.0
  access token, `smtpUserName:` is the mailbox. `smtpPassword:` is not used then (pass `''`). There is no `CRAM-MD5`.

`authMethod:` (`SmtpAuthMethodEnum::LOGIN`, `PLAIN`, `XOAUTH2`) fixes the method; the delivery aborts with a
`MailerException` if the server does not announce it.

```php
$mailer = new SmtpMailer(
    serverAddress: '192.0.2.1',
    hostName: 'smtp.office365.com',
    smtpUserName: 'noreply@example.com',
    smtpPassword: '',
    oAuthTokenProvider: $tokenProvider, // your implementation of OAuthTokenProvider
);
```

Passwords and tokens are neither written to `$mailer->log` (`AUTH ... (hidden)`) nor put into exception messages.

## Sending mail with Microsoft 365 (Graph API)

Microsoft ends basic authentication (user name and password) for SMTP. `GraphMailer` sends through the Microsoft Graph
API instead (`POST /users/{mailbox}/sendMail` with the MIME message that yuf builds, so HTML, attachments and headers
work as with `SmtpMailer`). `MicrosoftClientCredentialsTokenProvider` gets the access token with the OAuth 2.0 client
credentials flow (no user) and keeps it until one minute before it expires, so use one instance for all mails.

Register an app in Microsoft Entra ID with a client secret and the **application** permission `Mail.Send` for Microsoft
Graph (admin consent). Without further steps this allows the app to send as every mailbox of the tenant: restrict it to
the mailboxes it needs with an application access policy in Exchange Online. The address of the `From` header of a mail
must be the mailbox of the mailer or an address that this mailbox may send as.

```php
$tokenProvider = new MicrosoftClientCredentialsTokenProvider(
    tenantId: $core->environmentSettings->getString(key: 'mailer.tenantId'),
    clientId: $core->environmentSettings->getString(key: 'mailer.clientId'),
    clientSecret: $core->environmentSettings->getString(key: 'mailer.clientSecret'),
);
$mailer = new GraphMailer(
    serverAddress: '192.0.2.1',
    senderMailbox: 'noreply@example.com', // user ID or user principal name
    oAuthTokenProvider: $tokenProvider,
);
```

- Graph answers `202 Accepted` before the delivery: a later bounce is not reported. Any other answer throws a
  `MailerException` with the HTTP status and the `error.code` / `error.message` of Graph; exception messages never
  contain the client secret or a token.
- The request is limited to 4 MB by Graph. The message is sent as Base64 text and its attachments are Base64 encoded in
  the message already, so attachments of about 2 MB are the maximum. A larger message throws a `MailerException`
  before anything is sent; larger attachments (upload sessions) are not supported.
- The `Bcc` recipients are read from the `Bcc` header of the message (Exchange removes it from the delivered mail).

The same token provider serves `SmtpMailer` with `XOAUTH2` (Microsoft 365 SMTP, `smtp.office365.com`). The scope
differs, the app needs the permission `SMTP.SendAsApp` of Office 365 Exchange Online, and its service principal must be
registered in Exchange Online (`New-ServicePrincipal`) and have full access to the mailbox (`Add-MailboxPermission`):

```php
$tokenProvider = new MicrosoftClientCredentialsTokenProvider(
    tenantId: $core->environmentSettings->getString(key: 'mailer.tenantId'),
    clientId: $core->environmentSettings->getString(key: 'mailer.clientId'),
    clientSecret: $core->environmentSettings->getString(key: 'mailer.clientSecret'),
    scope: 'https://outlook.office365.com/.default',
);
$mailer = new SmtpMailer(
    serverAddress: '192.0.2.1',
    hostName: 'smtp.office365.com',
    smtpUserName: 'noreply@example.com',
    smtpPassword: '',
    oAuthTokenProvider: $tokenProvider,
);
```

## Clock

Time-dependent code takes a `actra\yuf\clock\Clock` (`now(): DateTimeImmutable`, the same signature as PSR-20's
`ClockInterface`, without the `psr/clock` dependency) through its constructor. Production code uses `SystemClock` (the
default everywhere); tests pass a `FixedClock`. There is no static accessor.

```php
use actra\yuf\clock\FixedClock;
use actra\yuf\core\FileLogger;

$logger = new FileLogger(
    logEmailRecipient: '',
    logDirectory: $logDirectory,
    httpRequest: $core->httpRequest,
    clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05'))
);
```

## Database connection

`FrameworkDb` is a PDO connection that throws on every error, uses native prepared statements and returns native types.
It holds no static state: one object is one connection, which your project creates once and passes on (or keeps in an
accessor of its own, as `actra/backend` does).

```php
use actra\yuf\db\DbConnectionParameters;
use actra\yuf\db\DbSettings;
use actra\yuf\db\FrameworkDb;

$db = new FrameworkDb(
    connectionParameters: DbConnectionParameters::forMysql(
        dbSettings: new DbSettings(
            hostName: 'db.example.com',
            databaseName: 'app',
            userName: 'app_user',
            password: $password,
        ),
    ),
);
```

`DbSettings` validates its values, because host and database name end up in the DSN and the charset and time names
language in the init command. `sqlSafeUpdates` is on by default (MySQL refuses `UPDATE` and `DELETE` without a key). Use
`?` placeholders for every value (`select()`, `selectRows()`, `selectRow()`, `execute()`, `prepareSelect()`); the values
are `float|int|string|null`, so convert booleans to `0` / `1`. `createInQuery()` creates the placeholders of an `IN (...)`
list, `getLastInsertId()` returns the generated ID as `int`, and `getQueryLog()` the queries run with `logQuery: true`
(one log per connection). A `DbRuntimeException` carries the SQL string and the number of bound values, but never the
values, because they may be personal data.

Tests can connect to an in-memory SQLite database: `new FrameworkDb(connectionParameters: new DbConnectionParameters(dsn:
'sqlite::memory:'))`.

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