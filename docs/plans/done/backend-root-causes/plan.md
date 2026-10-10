# Plan: root causes of workarounds in consuming projects

A consuming project built on yuf ~4.67.3 has local workarounds because yuf lacks typed APIs or has small bugs. Under the
coding standard's rule "Fix problems at their cause" they are fixed here; afterwards the project raises its
constraint and removes the workarounds. The items keep the IDs of the request (A1–A15).

All items go into one release (v5.0.0, user decision 2026-10-10: as few releases as possible; A5 makes it a major); the steps stay small and reviewable, but
are not tagged one by one. Characterization tests before behaviour changes, unit tests for every new method, regression
tests for bug fixes, `composer check` green with an empty baseline, docs in the matching topic file. Additive unless
marked ⚠️; every ⚠️ gets an `UPGRADE.md` entry with before/after. Every step ends with a handover note (below): what changed and
which workaround of the consuming project the new API replaces; the last step adds the commit message and the tag.

Status: done (2026-10-10, v5.0.0).

## Decisions

1. Typed session getters return `null` for a missing key or a value of another shape, like the existing typed getters
   of `Session` (no exception).
2. Composer autoload is added in addition to `actra/autoloader`. PHP calls an autoloader only for classes that are not
   loaded yet, so no class is loaded twice; whichever loader is registered first loads yuf's classes.
3. A9 changes the `DetailDataObject` constructor directly (major release), with before/after in `UPGRADE.md`.
4. Optional items: decided per item in the steps below (`DateColumn`, `afterResponse`, `randomFromAlphabet`, password
   rules, compact renderer: yes; `ViewContext` test factory: decided in step 2.3).
5. One release v5.0.0 for parts 1–4, one commit per part (user, 2026-10-10).
6. A5 is done (user, 2026-10-10).
7. A13: login path on the route collection, redirect with the current URI as return target (user, 2026-10-10).

## Part 1 – typed APIs

### Step 1.1 – Composer autoload (A1)

- `composer.json`: `"autoload": {"psr-4": {"actra\\yuf\\": "src/"}}`. Check PSR-4 compliance of `src/`
  (`composer dump-autoload --optimize --strict-psr`), including the generated `src/phone/data`.
- Tests and `Core::fromEnvironment()` keep `actra/autoloader` (no change for existing projects).
- `docs/testing.md`: PHPStan needs no `scanDirectories`, the PHPUnit bootstrap no yuf path; keep the bootstrap for the
  project's own classes.

### Step 1.2 – small typed APIs (A15, A3, A6, A14, `randomFromAlphabet`)

- A15: `DbRow::getStringList(string $column, string $separator = ','): list<string>`, `[]` for `NULL` and `''`, empty
  parts skipped. Bug fix: `AccessRightCollection::createFromStringArray()` skips empty strings (regression test:
  `['']` gives an empty collection). ⚠️ `UPGRADE.md` entry (security relevant: "no rights" checks become reliable).
- A3: `PathVars::list(): list<string>` (trimmed values in order) and `count(): int`.
- A6: `DbQuery::selectRowsFromDb(FrameworkDb $db, int $offset, int $rowCount): list<DbRow>` (uses
  `FrameworkDb::selectRows()`). No iterating variant (yuf has no iterating select).
- A14: `SecretTokenHash::tryFrom(string $hash): ?self`; the constructor uses the same check.
- `StringUtils::randomFromAlphabet(int $length, string $alphabet): string` with `random_int()`; at least two distinct
  characters, multibyte-safe, throws for invalid arguments.

### Step 1.3 – typed session values (A2)

- `Session::getStringList(string $key): ?list<string>`, `getStringMap(string $key): ?array<string, string>`.
- `Session::getStruct(string $key, Closure $map): mixed` with `@template T`, `@param Closure(array<array-key, mixed>):
  ?T $map`, `@return ?T`: `null` when the value is missing or no array, otherwise the result of the mapper (`null` for
  an invalid shape). Document the pattern (a value object with a static `fromSessionArray()`) in
  `docs/session-and-login.md`.

## Part 2 – login

### Step 2.1 – credential check without login (A4)

- Characterization tests for `doLogin()` first (every `AuthResultEnum` path, counting, rehash, logging, single use).
- `verifyCredentials(AuthMethodEnum $authMethod, string $userName, ?string $password): AuthResultEnum|AuthUser`: every
  check, wrong-attempt counting, `spendVerificationTime`, rehash and logging of rejections, without logging in.
- `precheck(string $userName): AuthResultEnum|AuthUser`: "may this user get a token" (unknown user, IP whitelist,
  inactive, out tried, project check), without password and without counting.
- `doLogin()` = `verifyCredentials()` + log success + `logIn()`; its behaviour and log entries stay unchanged.
  `logAuthResult()` stays protected. Document the two-factor flow in `docs/session-and-login.md`.

### Step 2.2 – missing login in views (A13)

- Characterization test of the current exception. A login path on the route collection (decision 7): with it, a
  missing login redirects there (current URI as return target, only local paths); without it, the exception stays.

### Step 2.3 – after the response, test context (optional items)

- `ResponseSender::afterResponse(Closure $callback): void` as public API, used by `NativeResponseSender` and
  `FileLogger` internally; the test sender runs callbacks on demand.
- `ViewContext` in tests: check which collaborators have defaults; add a factory only if it fits into `src/` without
  test code in production (decision recorded here).

## Part 3 – forms and tables

### Step 3.1 – forms (A10, A11, password rules, compact renderer)

- A10: `FormOptions::addIntItem(int $key, HtmlText $htmlText)`, `SingleOptionsField::getValueAsInt(): ?int`,
  `MultiOptionsField::getIntValues()` / `getAddedIntValues()` / `getRemovedIntValues(): list<int>`, `SearchState`
  method taking `FormOptions`. Check how numeric string keys turn into integers and fix it at the cause.
- A11: `CsrfTokenField::valueHasChanged()` returns `false` (bug fix, regression test, `UPGRADE.md`),
  `Form::hasChanges(): bool`.
- `PasswordField` minimum length; a rule comparing two fields (password confirmation).
- A compact field renderer (label + control) for search and filter forms. HTML output: check in `example/`.

### Step 3.2 – tables (A8, A7 tables, `DateColumn`)

- A8: `TableItem::$data` as `array<string, scalar|null>` (check callers), `DbResultTable::exportCsv(string $fileName):
  never` with `CsvFile`, `NULL` → `''`.
- A7: `TableMessages` value object (like `FormMessages`) for `SmartTable`/`DbResultTable`; German texts stay default.
- `CsvFile::pushDownloadAndExit()` cleans its temp file with `register_shutdown_function()`: use
  `ResponseSender::afterResponse()` if `CsvFile` can get the sender without a breaking detour.
- `DateColumn` with `IntlDateFormatter` and `Language::$locale` (additive, fixed format stays default).

### Step 3.3 – HTML, login texts, navigation (A9, A7 auth, A12)

- A9: ⚠️ `DetailDataObject` constructor takes `HtmlText` for name and value (decision 3).
- A7: `AuthResultEnum::label(AuthResultMessages $messages): HtmlText`; `render()` keeps the German texts.
- A12: `NavigationItemCollection::has(string $navKey): bool`; navigation built per request (provider
  `Closure(ViewContext): NavigationItemCollection`), current behaviour as default.

## Part 4 – nullable password (⚠️)

- A5: `AuthUser::$password` as `?Password`; `null` gives `ERROR_NO_PASSWORD_LOGIN_ACTIVE` without counting a wrong
  attempt; derive or drop `ACCESS_DO_PASSWORD_LOGIN`. ⚠️ `UPGRADE.md` with before/after.

## Handover notes

### Steps 1.1–1.3 (2026-10-10)

- Composer PSR-4 `autoload` and `autoload-dev`; yuf's tests load through Composer, `actra/autoloader` stays in
  `Core::fromEnvironment()`. Strict-types guard test (coding standard v1.18.1).
- New APIs as listed in `UPGRADE.md` (v5.0.0). Decisions: `getStringList()` does not trim (like `getString()`);
  `getStringMap()` rejects numeric keys; `randomFromAlphabet()` allows duplicate characters (they weigh the draw).
- Consuming project: A1 → remove `scanDirectories`, the yuf path in the test bootstrap and `addPath()` for yuf;
  A15 → `DbRow::getStringList()` (and the fixed `createFromStringArray()`); A3 → `PathVars::list()` / `count()`;
  A6 → `selectRowsFromDb()`; A14 → `SecretTokenHash::tryFrom()`; A2 → `Session::getStringList()` /
  `getStringMap()` / `getStruct()`.

### Steps 2.1–2.3 (2026-10-10)

- 2.1: characterization tests of `doLogin()` first (green on the old code), then `Authenticator` split: `precheck()` and
  `verifyPassword()` are public, `verifyCredentials(authMethod, userName, ?password)` and
  `logInVerifiedUser(authMethod, authUser, userName)` are protected. Reason: `null` as password skips the password
  check and a public login of a given user is a bypass, so a view could do it by mistake; projects expose public
  methods that carry the second factor (like `passwordLogin()` before). `logInVerifiedUser()` checks the user again
  (IP whitelist, `checkLoginCredentials()`, inactive, lock-out; a rejection is logged) because the state can change
  between two requests; it cannot know about the password/code check (documented). `precheck()` logs rejections like a
  login, not successes, and leaves `$authResult` unchanged on success. All three throw after a rejection/login of the
  same instance and while logged in (same messages as before). `doLogin()` = verify + `completeLogin()`; log entries
  unchanged. Two-factor flow: docs/session-and-login.md. Added `userName:` to `logInVerifiedUser()` (the log needs it).
- 2.2: `RouteCollection(loginPath:)` (validated local path), `RequestHandler::$loginPath`, `LoginRedirect`
  (`isLocalPath()`, `createLoginUri()`, `findReturnPath()`, parameter `returnTo`). The exception stays in the `BaseView`
  constructor; `UnauthorizedAccessRightException::$isNotLoggedIn` (set when the view got no user) is evaluated in
  `ExceptionHandler::createResponse()` (the half-built view is gone): GET, HTML, not the login page itself, then 303 to
  the login with the requested URI. Everything else (user without right, POST, JSON, no login path) is unchanged.
- 2.3: `ResponseSender::afterResponse(Closure)`: `NativeResponseSender` registers a shutdown function,
  `RecordingResponseSender` stores the callbacks (`runAfterResponseCallbacks()`); `FileLogger` got a last argument
  `responseSender:` for its mail (`Core` passes its own). Breaking for own `ResponseSender` implementations: UPGRADE.md.
  `ViewContext` test factory: not added. The constructor needs a `LocaleHandler`, `TemplateEngine` (cache directory,
  tags), `FormContext`, `ContentHandler`, `Route`, `PathVars` and `HttpRequest`; any factory in `src/` would have to
  choose test defaults (array session, temp directories). `tests/Double/core/ViewContextFactory` stays the pattern.
- Consuming project: A4 → delete the copy of `doLogin()`/`findRejection()` in the password form and the repeated
  rejection checks of the login form: `verifyPassword()` / `precheck()`, and a public `logAuthResult()` is no longer
  needed (the methods log); a second step calls `logInVerifiedUser()` from its own method. A13 → delete catching the
  exception on a half-built view to redirect: pass `loginPath:` to `RouteCollection` and read the target with
  `LoginRedirect::findReturnPath()` after the login. Own after-response code (shutdown function after
  `fastcgi_finish_request()`) → `$this->context->responseSender->afterResponse()`.

### Step 3.1 (2026-10-10)

- A10: PHP turns numeric string keys into integers, so `FormOptions::$data` has `int|string` keys and every consumer
  cast them. `getKeys()` / `getItems()` give strings, the renderers use them (HTML unchanged). Integer getters parse
  strictly (`FormOptions::toIntKey()`), a non-integer key throws (programming error: validation restricts input to
  known keys). `SearchState`: four methods with `FormOptions`; the old ones are unchanged.
- A11: `CsrfTokenField::valueHasChanged()` is `false`; `Form::hasChanges()` includes child fields of toggle fields.
- `PasswordField::setMinLength()` builds a `MinLengthRule` in `checkRules()` (the text comes from the form's
  `FormMessages`); `EqualsFieldRule` takes an explicit message, the compared field must be added first.
- `CompactFieldRenderer` per form or field; `DefinitionListRenderer` output pinned by tests.
- Consuming project: A10 → remove `(string)`/`(int)` casts of option keys and the `'option_'` prefix; A11 → remove own
  `hasChanges()` copies; own password checks → `setMinLength()` / `EqualsFieldRule`; own compact search-form markup →
  `useCompactFieldRenderer()` (CSS for `.form-compact-field`).

### Steps 3.2–3.3 (2026-10-10)

- `TableItem` holds scalars only (⚠️); `DbRow::getScalar()` for the export. `exportCsv()` streams rows through
  `iterateRows()` with filter and sorting, without paging; `ActionsColumn`/`CallbackColumn` skipped; CSV injection
  protection of `CsvFile` applies. `CsvFile` uses `afterResponse()` for its cleanup. `TableMessages`,
  `DateColumn::useLocale()`.
- ⚠️ `DetailDataObject(name: HtmlText, value: HtmlText)`. `AuthResultEnum::label()` with `AuthResultMessages`.
  `NavigationItemCollection::has()`; navigation provider `Closure(ViewContext): NavigationItemCollection` via
  `Core::prepareHttpResponse(navigationProvider:)`, lazily once per request (not on `viewCallback` routes).
- Consuming project: A8 → own CSV export: `exportCsv()`; A7 → overwritten German table and login texts:
  `TableMessages`, `AuthResultEnum::label()`; A9 → escaping labels by hand: `HtmlText::fromText()`; A12 → navigation
  added once per process: `navigationProvider`, `has()`.

### Part 4 (date 2026-10-10)

- A5: `AuthUser::$password` is `?Password`. `null` = no password: a password login gives
  `ERROR_NO_PASSWORD_LOGIN_ACTIVE` (checked after the state checks, so inactive/lock-out/IP still come first), is not
  counted and not rehashed, and costs `Password::spendVerificationTime()` so the answer time does not reveal it. Other
  login methods are unchanged.
- Decision: `ACCESS_DO_PASSWORD_LOGIN` is dropped (no derived right): the password login is allowed exactly when the
  user has a password. The login of users with a password but without the right now succeeds (⚠️ in `UPGRADE.md`).
- The existing tests were the characterization (password user with/without the right, counting); they changed with the
  behaviour. `TestAuthUser::create(hasPassword: false)` builds a user without password.
- Consuming project: remove the fake `Password(salt: '', hash: '!')` for users without password (pass `null`) and the
  code that adds the right whenever a password exists; block a password login with `null` (migration for users that
  relied on the missing right).

### Release (2026-10-10)

- All parts in v5.0.0; `UPGRADE.md` has every ⚠️ change with before/after, the v4 notes moved to
  `docs/upgrade/v4.md`. Libraries require yuf with `~5.0.0`.
- Skeleton: raise `actra/yuf` to `^5.0.0`, remove `scanDirectories` for yuf (`phpstan.neon`) and the yuf path of the
  test bootstrap; no other affected API is used there.
- Not checked in a browser (the example app has none of them): `CompactFieldRenderer`, navigation provider, login
  redirect. Check them in the consuming project after its migration.

### Removed compatibility code (2026-10-10)

New maintainer rule: no backwards compatibility layers (no old and new API side by side); consumers migrate with
`UPGRADE.md`. Removed from v5.0.0 (each with a "⚠️" section in `UPGRADE.md`):

- `SearchState::checkFilter()` / `checkMultiFilter()`: only the four `*OptionsFilter()` methods stay. No replacement
  for the `<fieldName>ID` input of `checkMultiFilter()` (an extra posted value was added unchecked) and for filters
  without `FormOptions`; both were dropped on purpose.
- `FormOptions::$data` is private; `getKeys()` / `getItems()` only.
- `AuthResultEnum::render()`: `label(messages:)` only.
- `SmartTable::$noDataHtml`, `$totalAmountMessageOneResult`, `$totalAmountMessageNumResults`: `TableMessages` only
  (`SmartTable::getNoDataHtml()` is the protected extension point, `DbResultTable` prepends its filter there).
- `TableItem::getScalarValue()`: `getRawValue()` only.

Left (not removed, decide separately): `HttpResponse::createHtmlResponse()` / `createResponseFromString()` take an unused
`Clock $clock`; `FrameworkDb::lastInsertId()` throws to guard old calls; `Password` verifies the legacy SHA-256 format
(stored data, not API); `EmailAddressErrorEnum` values are "the codes of earlier versions" (stored/compared values).
- Also removed: the unused `clock:` of `HttpResponse::createHtmlResponse()` / `createResponseFromString()`. Kept:
  `FrameworkDb::lastInsertId()` throwing (a guard against the untyped PDO method, not compatibility), `Password`
  legacy hash verification (stored data), `EmailAddressErrorEnum` values (compared by callers).
