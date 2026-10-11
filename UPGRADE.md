# Upgrade

Changes of yuf, newest first. ⚠️ marks breaking changes: read them before `composer update`. Older versions:
[v4](docs/upgrade/v4.md), [v3](docs/upgrade/v3.md), [v2](docs/upgrade/v2.md), [v1](docs/upgrade/v1.md),
[v0](docs/upgrade/v0.md).

## v6.2.0 (2026-10-11)

- `DbResultTable::exportCsv()` exports a `CallbackColumn` with the new argument `exportValueFunction:`
  (`fn(DbRow $row): bool|float|int|string|null`), e.g. the name of a linked name; a second table variant for the
  export is no longer needed. Without it the column is left out as before (also `TableHelper::createCallbackColumn()`).

## v6.1.0 (2026-10-11)

- New `EnvironmentSettings::getEnum(key:, enumClass:)` reads a backed enum from `.env.php` (`UnexpectedValueException`
  for a missing key, a wrong type or an unknown value). `SmtpEncryptionEnum` is backed by strings (`'starttls'`,
  `'implicit_tls'`, `'none'`): configure the mailer with
  `encryption: $core->environmentSettings->getEnum(key: 'mailer.encryption', enumClass: SmtpEncryptionEnum::class)`
  and `'mailer.encryption' => 'starttls'` in `.env.php`, instead of an own mapping ([docs/mail.md](docs/mail.md)).
- Fix: the table filter renders no line break after its closing `</div>` again (added by v6.0.1).

## v6.0.1 (2026-10-11)

- The search help of the table filter (`tableFilter.html`) no longer lists `%` as a wildcard: `%` is searched
  literally, only `*` is a wildcard.

## v6.0.0 (2026-10-10)

Security release: fixes in routing, login, Microsoft login, forms, uploads, file responses, mail, tables and logging.
Several defaults change and are on without opt-out switches; read every ⚠️ entry.

### ⚠️ Microsoft login: identity by object ID and tenant ID, nonce handled by yuf

The user is identified by the claims `oid` and `tid` of the token, never by the email address.
`MicrosoftIdToken::getUserName()`, `AuthWebToken::getUserName()` and `Authenticator::authWebTokenLogin()` are removed;
new are `MicrosoftIdToken::getObjectId()`, `getTenantId()` (lower-case GUIDs) and `getEmail(): ?string` (display only).
yuf creates the nonce and keeps it in its own short-lived cookie (`__Secure-yuf-sso-nonce`: `Secure`, `HttpOnly`,
`SameSite=None`, path of the redirect URI, 10 minutes, used once); the session cookie is never downgraded
(`AbstractSessionHandler::changeCookieSameSiteToNone()` is removed).

- Implement `findAuthUserByMicrosoftAccount(string $objectId, string $tenantId): ?AuthUser` and store both GUIDs per
  user. Link existing users explicitly (by an administrator or a migration script), never by matching the email
  address ([docs/session-and-login.md](docs/session-and-login.md)).
- `redirectToMicrosoftLogin(tenantId:, clientId:, redirectUri:)` and
  `microsoftIdTokenLogin(tenantId:, clientId:, redirectUri:, microsoftIdToken:)` take no `ssoNonce` any more (pass the
  same `redirectUri` to both); remove the nonce from the session of the project.
- The constructor of `MicrosoftAuthenticator` has no `sessionHandler:` any more.
- Start the login with a link (GET): the default CSP has `form-action 'self'` (see below), which blocks a form POST
  that redirects to Microsoft.

```php
// Before
$this->redirectToMicrosoftLogin(tenantId: $t, clientId: $c, redirectUri: $uri, ssoNonce: $nonce);
$this->microsoftIdTokenLogin(tenantId: $t, clientId: $c, ssoNonce: $nonce, microsoftIdToken: $token);

// After
$this->redirectToMicrosoftLogin(tenantId: $t, clientId: $c, redirectUri: $uri);
$this->microsoftIdTokenLogin(tenantId: $t, clientId: $c, redirectUri: $uri, microsoftIdToken: $token);
```

### ⚠️ Wrong passwords are counted before the check, a login does not reset the counter

`AuthUser::increaseWrongPasswordAttempts()` and `dbIncreaseWrongPasswordAttempts()` are removed. `Authenticator` counts
the attempt in the database before it checks the password and gives it back after the right one, so parallel requests
cannot try more passwords than allowed. Implement the two new abstract methods of `AuthUser`, each as one statement:

```php
// true if one row changed; false: the limit is reached (the password is not checked)
protected function dbRegisterWrongPasswordAttempt(int $maxAllowedWrongPasswordAttempts): bool
// UPDATE user SET wrong_login_attempts = wrong_login_attempts + 1 WHERE id = ? AND wrong_login_attempts < ?

protected function dbReleaseWrongPasswordAttempt(): void
// UPDATE user SET wrong_login_attempts = wrong_login_attempts - 1 WHERE id = ? AND wrong_login_attempts > 0
```

`AuthUser::confirmSuccessfulLogin()` no longer resets the counter: remove `wrong_login_attempts = 0` from
`dbConfirmSuccessfulLogin()`. The lock stays until the project sets a new password and resets the counter there. A
second factor counts with `Authenticator::registerWrongPasswordAttempt()` / `releaseWrongPasswordAttempt()`.

### ⚠️ Forms: CSRF checked first, required for POST forms

- `Form::validate()` checks the CSRF token first: with a missing or invalid token the form gets only the CSRF error;
  no field, listener, upload or spam check runs. The form is rendered with its initial values then.
- A POST form without CSRF token source in its `FormContext` (no session) throws a `LogicException`. Pass
  `csrfProtection: false` (new last argument of `Form`) if the form needs no protection. `Form::removeCsrfProtection()`
  is removed. GET forms are not affected. Tests that post a form must send the token or use `csrfProtection: false`.

```php
// Before
$form = new Form(context: $context, name: 'search');
$form->removeCsrfProtection();

// After
$form = new Form(context: $context, name: 'search', csrfProtection: false);
```

### ⚠️ Forms: `maxLength` is validated, uploads need a directory, spam protection bound to the session

- `maxLength:` of input fields is validated on the server (error text `FormMessages::$tooLong`, new last argument of
  `FormMessages`, `[max]` is replaced). A `MaxLengthRule` of the project with the same or a smaller limit replaces the
  built-in check. `MaxLengthRule::$maxLength` is public.
- `SessionFileUploadStorage::forHttpRequest()` is removed; there is no default directory. Pass a directory outside
  the document root: `new SessionFileUploadStorage(session: $session, rootDirectory: $directory)`. It is created with
  mode 0700, stored files get 0600; a symbolic link or a directory of another user throws a `RuntimeException`.
- `FileUploadStorage` has the new methods `issue(string $pointer): void` and `isIssued(string $pointer): bool`
  (custom implementations and test doubles add them; `store()` and `clear()` ignore pointers that were not issued). A
  `FileField` takes over a posted upload pointer only if it was issued in the same session (new session section
  `uploadPointers`, `SessionSectionEnum::UPLOAD_POINTERS`).
- The signed render time of the spam protection is bound to the session: `SpamCheck::signRenderTime()`, `check()` and
  `isValidRenderTime()` have the new required argument `sessionBinding: ?string` (the CSRF token; `null` only without
  session), `SpamRenderTimeField` the new argument `sessionSource:`. No change for forms that use
  `Form::addSpamProtection()`; forms rendered before the update show the "try again" text once.

### ⚠️ Routing, host and language files

- `RequestHandler::resolveRoute()` answers 404 if the file title (the file name before the first `-` and the
  extension) has other characters than letters, digits and `_`, or the file group (`${fileGroup}`) has other names
  than letters, digits, `_` and `-` separated by `/`. Both name view classes, templates and language files.
- `LocaleHandler::loadLanguageFile()` has the new required argument `languageDirectory:`; a file outside of it (after
  resolving symbolic links) throws a `RuntimeException`.
- A request over HTTP for a host that is not in `allowedDomains` gets a 404 instead of the redirect to HTTPS.
  `HttpRequest::getHost()` is only trustworthy after this check ([docs/setup.md](docs/setup.md)).

```php
// Before
$localeHandler->loadLanguageFile(filePath: $file);
// After
$localeHandler->loadLanguageFile(filePath: $file, languageDirectory: $directory);
```

### ⚠️ File responses and Content-Security-Policy

- `HttpResponse::createResponseFromFilePath()`, `FileHandler::output()` and `CsvFile`: HTML, XHTML, XML, SVG and
  JavaScript files (by extension or content type) are always sent as download, also with `forceDownload: false`. Every
  file response has `Content-Security-Policy: default-src 'none'; sandbox`. `Content-Disposition` is
  `attachment; filename="…"; filename*=UTF-8''…` (ASCII fallback, escaped). Serve such files with the web server if
  they must be shown in the browser; tests that compare the header need the new format.
- `FileHandler::output()` without `forceDownload:` lets the content type decide (`?bool $forceDownload = null`, e.g.
  CSV is a download, PNG is shown; before: always shown). Pass `forceDownload: false` to keep showing such a file.
- The default Content-Security-Policy has `form-action 'self'` (new argument `formAction:` of `CspPolicySettings`).
  Forms that post to another origin (or a POST that redirects to one) need `formAction:`.

### ⚠️ Mail

- `SmtpMailer`: `useTls: bool` is replaced by `encryption: SmtpEncryptionEnum` (`STARTTLS`, the default;
  `IMPLICIT_TLS` for port 465; `NONE`). `port:` is optional (default by the encryption: 587, 465 or 25). A user name
  with `NONE` throws an `InvalidArgumentException` unless the host is `localhost`, `127.0.0.0/8` or `::1`.
- `SmtpTransport::open()` has the new argument `bool $implicitTls` (own transports and test doubles); `enableTls()`
  may throw a `MailerException`.
- `MailerAddress` and `ValidatedEmailAddress` (the email field of forms) reject control characters and quoted local
  parts (new `EmailAddressErrorEnum::CONTROL_CHARACTERS`).

```php
// Before
new SmtpMailer(…, useTls: false);
// After: STARTTLS is the default; without encryption only on the same host
new SmtpMailer(…, encryption: SmtpEncryptionEnum::NONE);
new SmtpMailer(…, encryption: SmtpEncryptionEnum::IMPLICIT_TLS); // port 465
```

### ⚠️ Tables and navigation

- Column labels and navigation titles are `HtmlText`: `AbstractTableColumn` and its subclasses (`DefaultColumn`,
  `DateColumn`, `BooleanColumn`, `FileSizeColumn`, `StripHtmlTagsColumn`, `CallbackColumn`, `OptionsColumn`),
  `TableHelper::create*Column()` (`label:`) and `NavigationItem` (`title:`). Plain text: `HtmlText::fromText()`, HTML
  of the application: `HtmlText::fromHtml()` (same output as before). The `label` and `title` properties are
  `HtmlText`. `ActionsColumn` and `TableHelper::createActionsColumn()` take `?HtmlText $label = null`.
- `ActionsColumn::addEditActionLink()` / `addDeleteLink()`: a link target must not start with a placeholder
  (`InvalidArgumentException`); `[column]` values in these targets are URL-encoded (before: HTML-encoded only).
- `SearchQueryBuilder::createSqlFilters()` and `TextFilterField` accept column names only (`name`, `table.name`,
  `` `table`.`name` ``); an SQL expression throws an `InvalidArgumentException`. Search an expression through a column
  of the query (`SELECT …, CONCAT(a, b) AS search`).
- `TableItem::renderValue()` encodes quotes (`&quot;`, `&#039;`).

```php
// Before
new DefaultColumn(identifier: 'name', label: 'Name');
new NavigationItem(…, title: 'Users', …);
// After
new DefaultColumn(identifier: 'name', label: HtmlText::fromText(text: 'Name'));
new NavigationItem(…, title: HtmlText::fromText(text: 'Users'), …);
```

### Changed defaults and other fixes

- ⚠️ Default of `SessionSettings::$gcDivisor` is 1000 (garbage collection 1/1000, was 1/100000).
- `.env.example.php` ships `'debug' => false`. In production keep `debug` false and set
  `zend.exception_ignore_args=On`. The client IP is `REMOTE_ADDR` only: behind a reverse proxy, let the web server
  set it (e.g. `mod_remoteip`) ([docs/setup.md](docs/setup.md)).
- Security fixes without action: `AuthSession::logIn()` (and impersonation) removes the CSRF token of the session;
  legacy SHA-256 passwords and unknown users take the time of a real verification; STARTTLS is refused if the server
  sent data before the handshake; `LoginRedirect::isLocalPath()` also requires a URI that parses without scheme and
  host; `DbResultTable` uses a stored sort column only if it is sortable in the table; `PhoneNumberField` encodes its
  rendered value; logged request values and messages are escaped to one line, and the ticket of an exception no
  longer depends on its message; `FileSessionHandler` understands `N;path` save paths; `CachedKeySet` throttles key
  downloads also without cache.

## v5.7.0 (2026-10-10)

- The spam protection (`Form::addSpamProtection()`) is checked only if the fields are valid: a submission with field
  errors shows the field errors and `globalErrorMessage` instead of a spam text. A form rendered again after errors
  keeps a validly signed, not expired render time, so the 3-second minimum does not start again. New
  `SpamCheck::isValidRenderTime(formName:, signedRenderTime:)`. No change for projects; the markup stays the same.
- `MailMimeHeader` and `MailMimeBody` are public API of `AbstractMailer::sendMail()` (only their constructors are
  `@internal`): an own mailer, e.g. a test double that records the `AbstractMail`, passes PHPStan. New
  `AbstractMail::getSubject()`, `getBody()` and `FixedServerNameResolver` for such doubles
  ([docs/mail.md](docs/mail.md#tests)). Projects may replace `MailFunction` doubles with it.

## v5.6.0 (2026-10-10)

- New `Form::addSpamProtection(spamProtection: new SpamProtection(clock:, secret:, emailField:,
  rejectedEmailAddresses:))`: honeypot, signed render time (3–3600 seconds) and rejected e-mail addresses for public
  forms, checked in `validate()`; texts in `FormMessages` (`spamHoneypotLabel`, `spamHoneypotFilled`, `spamRetry`,
  `spamRejectedEmailAddress`). Replace own honeypot and timestamp fields with it; the markup stays the same, only the
  value of the hidden field is signed ([docs/forms.md](docs/forms.md)).

## v5.5.0 (2026-10-10)

- New `ViewContext::$documentRoot`: the document root of the web server (with a trailing slash), filled by `Core`, for
  views and the libraries they use (e.g. uploads below the document root) without static configuration
  ([docs/views.md](docs/views.md)).
- ⚠️ Code that creates a `ViewContext` itself (test doubles) passes `documentRoot:`; code that calls
  `ContentHandler::processRequest()` itself passes `documentRoot:` too.

## v5.4.0 (2026-10-10)

- New `HtmlDocument::useContentFile(filePath:)`: a view renders a content file with a fixed path, also on routes with
  file groups (the group of the request is not added). For templates shipped with a library
  ([docs/views.md](docs/views.md)).

## v5.3.0 (2026-10-10)

- New `HtmlText::fromSanitizedHtml(html:, allowedTags:)` and `HtmlSanitizer::sanitize()`: HTML from an external source
  reduced to an allowlist of tags and attributes, without scripts, event handlers, styles and (by default) URLs
  ([docs/templates.md](docs/templates.md)).

## v5.2.1 (2026-10-10)

- Fix: a search filter can be reset to "all" again. `SearchState::checkOptionsFilter()` and
  `checkIntOptionsFilter()` accept a posted `''` (the empty option of the field, e.g. `individualEmptyValueLabel`)
  as no filter (`''`, `null`) instead of keeping the remembered key (broken since v5.0.0).

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
