# Upgrade

Changes of yuf, newest first. ⚠️ marks breaking changes: read them before `composer update`. Older versions:
[v4](docs/upgrade/v4.md), [v3](docs/upgrade/v3.md), [v2](docs/upgrade/v2.md), [v1](docs/upgrade/v1.md),
[v0](docs/upgrade/v0.md).

## v5.2.0 (2026-10-10)

- New `HtmlText::fromTextWithLineBreaks(text:)`: plain text escaped, line breaks as `<br>`; instead of
  `nl2br(htmlspecialchars(…))` with `addHtml()` ([docs/templates.md](docs/templates.md)).
- New `HtmlDataObject::addHtmlText(propertyName:, htmlText:)`, like `HtmlReplacementCollection::addHtmlText()`.

## v5.1.1 (2026-10-10)

- `CompactFieldRenderer` renders a plain `<div>` (with `class="has-error"` only for a field with errors) instead of
  `<div class="form-compact-field">`, the markup that search fields had before (coding standard v1.22.0: library
  updates keep the look of existing output).

## v5.1.0 (2026-10-10)

### ⚠️ Composer loads all classes, `actra/autoloader` is no longer used

yuf no longer depends on `actra/autoloader`, and `Core::fromEnvironment()` registers no autoloader. Composer is needed
to build the application, not on the server. Remove `autoloaderPath:`, delete `app/cache/autoloader.php` and deploy
with `composer install --no-dev --optimize-autoloader`.

```php
// Before: composer.json without autoload for app\, entry point
require __DIR__ . '/../vendor/actra/yuf/src/Core.php';
$core = Core::fromEnvironment(envFilePath: …, copyrightYear: 2026, autoloaderPath: …);

// After: composer.json "autoload": {"psr-4": {"app\\": "app/"}}, then composer dump-autoload; entry point
require __DIR__ . '/../vendor/autoload.php';
$core = Core::fromEnvironment(envFilePath: …, copyrightYear: 2026);
```

CLI scripts include `vendor/autoload.php` the same way. The test bootstrap is `require __DIR__ .
'/../vendor/autoload.php';` only (no `Autoloader::register()`). Remove `actra/autoloader` from the project's
`composer.json` unless the project uses it itself. `Core::AUTOLOADER_CACHE_FILE_NAME` is removed.

## v5.0.2 (2026-10-10)

- Docs: the v5.0.0 entry "Applications are unchanged" was wrong for applications that include `vendor/autoload.php`
  before `Core::fromEnvironment()`: Composer loads yuf there, `actra/autoloader` only the classes of `app/`. It works
  as is; deploy with `composer install --no-dev --optimize-autoloader` ([docs/setup.md](docs/setup.md)).

## v5.0.1 (2026-10-10)

- Security: a password login refused for the IP whitelist, `checkLoginCredentials()`, an inactive or a locked-out
  user now costs the time of a password verification too, so the answer time no longer tells that the user exists.
  No API change.

## v5.0.0 (2026-10-10)

### ⚠️ `AuthUser::$password` is nullable, `ACCESS_DO_PASSWORD_LOGIN` is removed

`AuthUser::$password` is `?Password`: `null` is a user without password. A password login of such a user gives
`ERROR_NO_PASSWORD_LOGIN_ACTIVE`, is not counted as wrong attempt and is not rehashed (it costs the time of a
verification, like an unknown user). Web token login, `verifyCredentials()` with `null`, `precheck()` and
`logInVerifiedUser()` work without a password. Before: a fake password and the right added when a password exists.

```php
// Before
$accessRights = AccessRightCollection::createFromStringArray(input: $rights);
if ($hasPassword) {
    $accessRights->add(accessRight: AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN);
}
new MyAuthUser(
    // …
    accessRightCollection: $accessRights,
    password: $hasPassword ? new Password(salt: $salt, hash: $hash) : new Password(salt: '', hash: '!'),
);

// After
new MyAuthUser(
    // …
    accessRightCollection: AccessRightCollection::createFromStringArray(input: $rights),
    password: $hasPassword ? new Password(salt: $salt, hash: $hash) : null,
);
```

The constant `AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN` is removed: a password login is allowed exactly when the
user has a password. Users with a password but without the right could not log in with it before and now can: projects
that used the missing right to block the password login must set the password to `null` (e.g. in a migration).
Code that reads `$authUser->password` must handle `null`.

### ⚠️ `AccessRightCollection::createFromStringArray()` skips empty strings

A user without rights from an empty column (`explode(',', '')` gives `['']`) now gets an empty collection, so
`isEmpty()` and "no rights" checks work. Read comma-separated columns with `DbRow::getStringList()`.

### ⚠️ `ResponseSender` has a new method `afterResponse()`

Own implementations of `ResponseSender` (usually a test double) must implement
`afterResponse(Closure $callback): void`. Before: only `send(HttpResponse): never`. After: add

```php
public function afterResponse(Closure $callback): void
{
    register_shutdown_function(callback: $callback); // a test double stores the callback and runs it on demand
}
```

`NativeResponseSender` registers a shutdown function (runs after the response with PHP-FPM). `FileLogger` has a new
last argument `responseSender:` (default `NativeResponseSender`; `Core` passes its own).

### ⚠️ `CsrfTokenField::valueHasChanged()` returns `false`

The CSRF field has no value of the user. `valueHasChanged()` returned `true` as soon as the token was posted, so a
change check over all fields was always `true`. Before: own loops over `getAllFields()` that skip `CsrfTokenField`.
After: `$form->hasChanges()`.

### ⚠️ `DetailDataObject` takes `HtmlText`

The label was trusted HTML and never escaped. Name and value are `HtmlText` now; `isHtml:` is removed.

```php
// before
new DetailDataObject(name: 'Customer', value: $userInput, isHtml: false);
// after
new DetailDataObject(name: HtmlText::fromText(text: 'Customer'), value: HtmlText::fromText(text: $userInput));
```

### ⚠️ `TableItem` holds scalars and `NULL` only

`TableItem::$data` is `array<string, bool|float|int|string|null>`. A row with an array or an object throws an
`UnexpectedValueException` when it is created (before: when a cell was rendered). `getRawValue()` returns
`bool|float|int|string|null` instead of `mixed`. Keep arrays in your own data and render them with a `CallbackColumn`.

### ⚠️ `SearchState`: `checkFilter()` and `checkMultiFilter()` are removed

Use the methods for `FormOptions`. A filter needs its options as `FormOptions` (the same object as the form field).
The `<fieldName>ID` input of `checkMultiFilter()` is gone; add such a key to the options or read it yourself.

```php
// before
$level = $searchState->checkFilter(array: ['a' => 'A'], fieldName: 'level', default: 'a');
$groups = $searchState->checkMultiFilter(array: [1 => 'One'], fieldName: 'groups'); // list<int|string>
// after
$level = $searchState->checkOptionsFilter(formOptions: $levels, fieldName: 'level', default: 'a');
$groups = $searchState->checkIntMultiOptionsFilter(formOptions: $groupOptions, fieldName: 'groups'); // list<int>
```

### ⚠️ `FormOptions::$data` is private

Before: `array_keys($formOptions->data)`. After: `$formOptions->getKeys()` (strings) or `getItems()`.

### ⚠️ `AuthResultEnum::render()` is removed

```php
// before
$authResult->render();
// after (German; AuthResultMessages::english() for English)
$authResult->label(messages: new AuthResultMessages())->render();
```

### ⚠️ `SmartTable`: the text properties are removed

`noDataHtml`, `totalAmountMessageOneResult` and `totalAmountMessageNumResults` are no longer public properties. Set the
texts with `TableMessages`. A subclass that needs another no-data HTML overrides `getNoDataHtml()`.

```php
// before
$table->noDataHtml = '<p>Nothing here.</p>';
// after
$table = new SmartTable(..., messages: new TableMessages(noData: 'Nothing here.'));
```

### ⚠️ `TableItem::getScalarValue()` is removed

Use `getRawValue()` (it returned the same).

### ⚠️ `HttpResponse::createHtmlResponse()` and `createResponseFromString()` take no `clock:`

The argument was unused since v4.59.0. Remove `clock:` from these calls (`createFileResponse()` keeps it).

### New

- Composer autoload (PSR-4) for yuf: PHPStan and PHPUnit of projects no longer need `scanDirectories` or a yuf path in
  the test bootstrap; remove them ([docs/testing.md](docs/testing.md)). Applications are unchanged:
  `Core::fromEnvironment()` still loads yuf with `actra/autoloader`.
- `DbRow::getStringList(column:, separator: ',')`: `list<string>`, `[]` for `NULL` and `''`, empty parts skipped, not
  trimmed ([docs/database.md](docs/database.md)).
- `DbQuery::selectRowsFromDb(db:, offset:, rowCount:)`: `selectFromDb()` with `list<DbRow>`.
- `PathVars::list()` (all trimmed values in order) and `count()` ([docs/views.md](docs/views.md)).
- `SecretTokenHash::tryFrom(hash:)`: `null` instead of an exception for another format.
- `StringUtils::randomFromAlphabet(length:, alphabet:)`: secure random string of the given (multibyte) characters.
- `Session::getStringList()`, `getStringMap()` and `getStruct(key:, map:)` (maps an array to a value object); `null`
  for a missing key or another shape ([docs/session-and-login.md](docs/session-and-login.md)).
- `Authenticator::verifyPassword()` and `precheck()` (public) and `verifyCredentials()` / `logInVerifiedUser()`
  (protected): the checks of a login without logging in, for a password form with a second step, and for token
  requests. `doLogin()` behaves as before; `logAuthResult()` stays protected
  ([docs/session-and-login.md](docs/session-and-login.md)).
- `RouteCollection(loginPath:)`: a request of a page that needs a login is redirected to the login page with
  `?returnTo=<requested URI>`; `LoginRedirect::findReturnPath()` reads the validated target (local paths only).
  `UnauthorizedAccessRightException::$isNotLoggedIn` tells "no user" from "no right". Without a login path nothing
  changes ([docs/views.md](docs/views.md)).
- `ResponseSender::afterResponse(callback:)`, see above ([docs/views.md](docs/views.md)).
- `FormOptions::addIntItem(key:, htmlText:)` for integer keys (ids); `getKeys()` / `getItems()` (`list<FormOption>`)
  give the keys as strings, `FormOptions::toIntKey()`.
- `SingleOptionsField::getValueAsInt()`, `MultiOptionsField::getIntValues()` / `getAddedIntValues()` /
  `getRemovedIntValues()`; an `UnexpectedValueException` for a key that is no integer ([docs/forms.md](docs/forms.md)).
- `SearchState::checkOptionsFilter()`, `checkIntOptionsFilter()`, `checkMultiOptionsFilter()`,
  `checkIntMultiOptionsFilter()` take the `FormOptions` of the field (keys as strings or integers, never mixed).
- `Form::hasChanges()`, `PasswordField::setMinLength()` (text `FormMessages::$passwordTooShort`), `EqualsFieldRule`
  (password confirmation).
- `CompactFieldRenderer` and `Form::useCompactFieldRenderer()`: label and control in
  `<div class="form-compact-field">` for search and filter forms (style it in the project's CSS).
- `DbResultTable::exportCsv(fileName:)`: CSV download of all rows with the current filter and sorting, `NULL` as `''`.
- `TableMessages` (`messages:` of `SmartTable`, `DbResultTable`, `TableHelper`): German default, `english()`.
- `DateColumn::useLocale(language:, dateStyle:, timeStyle:)` with `DateStyleEnum` (`IntlDateFormatter`).
- `AuthResultEnum::label(messages:)` with `AuthResultMessages` (German default, `english()`).
- `NavigationItemCollection::has(navKey:)`; `Core::prepareHttpResponse(navigationProvider:)` and
  `ViewContext::getNavigation()` build the navigation per request ([docs/views.md](docs/views.md)).
- `DbRow::getScalar(column:)`: the value as fetched.
- `CsvFile::pushDownloadAndExit()` removes its temporary file with `ResponseSender::afterResponse()`.
