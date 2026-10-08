# Plan: finish the coding standard in yuf

Everything that is still open after the template engine rewrite (v4.27.0), see
[docs/standard-migration/remaining.md](../standard-migration/remaining.md). `actra/backend` follows when yuf is done.

## Decisions (user)

- Order: the redesigns first (static request and session state), then the areas one by one.
- `final` per area: each area release makes its classes `final` or documents them as extension point (abstract base
  class or PHPDoc "Extension point: …"). Classes that `actra/backend` extends are extension points unless the area
  release offers a better way (decided in the release): `BaseView`, `AuthUser`, `Authenticator`, `FrameworkDb`,
  `DbResultTable`, `Form`, `TextField`, `SelectOptionsField`, `TextAreaField`, `StringRule`.
- Enums first per area: fixed sets of string/int constants become backed enums (⚠️ with before/after); constants that
  are no fixed set (placeholders, defaults, limits) stay typed constants.
- `LogFile` stays as instance class (used by other projects as `new LogFile(…)` + `write()`; `core/Logger` has another
  purpose): the static facade `info()` / `debug()` / `error()` and the static registry of open files are removed.
- `src/phone/` and `src/mailer/` get the full standard (characterization tests first, license notices of the mailer
  kept).
- No backwards compatibility: renames and removals without aliases, every breaking change ⚠️ with before/after in
  `UPGRADE.md` (as in docs/standard-migration/plan.md).

## Definition of done for an area

Every area release does all of this for the classes of its area (and nothing outside it):

1. Characterization tests first where behaviour is not covered yet (hand-written doubles in `tests/Double/`).
2. PHPStan baseline entries of the area removed (types, array shapes, `mixed`, offsets).
3. Explicit comparisons (`isset()`, `empty()`, `==`, `?:`), `match` instead of `switch`, no `@`.
4. SPL or yuf exceptions instead of `new Exception(…)`; messages that say what is wrong.
5. `final` or documented extension point; `readonly` where possible; `@internal` for classes consumers should not use.
6. Enums for fixed sets; names per `naming.md`; lines ≤ 120 characters.
7. No new static state; static state of the area removed where the redesigns made it possible.
8. `UPGRADE.md` section, README if affected, handover note here, `composer check` green, `example/` checked.

## Steps

### Redesigns

1. **v4.28.0 – `LogFile` instance only (⚠️):** remove `info()`, `debug()`, `error()` and the static registry; `final`;
   constructor and `write()` stay (`logDirectory` since v4.26.0). Tests with a temporary log directory and a fixed
   clock. Small, so it goes first.
2. **`HttpRequest` as instance** (design first in `docs/http-request/design.md`, approved by the user): one object per
   request created by `Core` from the superglobals (`HttpRequest::fromGlobals()`), passed to `RequestHandler`, views
   (`ViewContext`), forms (`FormInput::fromGlobals()`), tables (`DbResultTable`, `TableFilter`), `SearchHelper`,
   `CspPolicySettings`, `HttpResponse`, `Logger` and `ExceptionHandler`; the static caches (`$inputData`, `$host`,
   `$protocol`, `$languages`) and `RequestBody::getData()` go away; fixed sets (`PROTOCOL_*`, request methods) as enums.
   Makes the request pipeline of `Core` testable. Several releases (decided in the design).
3. **Session object** (design first in `docs/session/design.md`): one session object per request instead of the static
   `AbstractSessionHandler::getSessionHandler()` / `enabled()` / `$GLOBALS`, `AuthSession`, `CsrfToken`,
   `FormNameRegistry`, `SessionFileUploadStorage::forCurrentRequest()` and the session state of `DbResultTable` /
   `TableFilter` / `SearchHelper`; removes the reflection in `AuthSessionTest`, `AuthenticatorTest`; the single-instance
   guards of `AuthUser` / `Authenticator`. Several releases (decided in the design).

### Areas (largest first; after the redesigns)

Decision of the user: the large areas first; small areas that sit next to a large one go into its release. Baseline
entries in brackets (state after the superglobals rule, 447 in total). Each line is one release unless it turns out
too large.

4. `core` (47), `Core.php` (18), `request` (5), `response` (2), incl. the test gaps of the request pipeline. `Logger`
   masks secrets (decision of the user): request line, host, IP address, user agent, referrer; GET/POST values masked
   for keys like `password`, `token`, `secret`, `csrf`, `key`, `auth`; cookie names only; server variables from an
   allow-list (no environment secrets). The debug page (debug mode only) stays as is.
5. `common` (68).
6. `phone` (78, full standard; characterization tests first).
7. `db` (51).
8. `table` (39) and `pagination` (3).
9. `mailer` (33, full standard; characterization tests of the MIME output first).
10. `auth` (26), `security` (0) and the rest of `session` (2).
11. `api` (27).
12. `html` (25) and `layout` (0, `final` only).
13. `exception` (10) and `datacheck` (13).
14. `form` (0 baseline; `final` / extension points of its classes).

## Follow-up in other projects

- `actra/backend`: after each release as listed in its `UPGRADE.md` entry; the extension points it uses are decided in
  the area releases.
- Projects that use `LogFile` (`new LogFile(…)` + `write()`): add `logDirectory:` (v4.26.0); nothing else changes.

## Handover notes

### Step 1 (v4.28.0) – done

- `LogFile` is `final` and an instance class: `info()`, `debug()`, `error()`, `log()` and `$openLogFiles` removed; the
  class has no static state anymore (only private static helpers).
- Characterization tests in `tests/Unit/common/LogFileTest.php` passed against the old code before the change.
- The German `mkdir()` message check is replaced by a locale-independent check (`is_dir()` after `mkdir()`, errors
  silenced with a temporary error handler, no `@`). Directory or file failure throws a `RuntimeException`; the
  constructor throws if `fopen()` fails (bug fix). Baseline: 532 -> 525 entries (`uniqid()` entry stays).

### Step 2 (v4.29.0) – done

- `HttpRequest` is an immutable instance (`Core::$httpRequest`, `ViewContext::$httpRequest`); details, signatures and
  what is not covered in [docs/http-request/plan.md](../http-request/plan.md). Baseline: 525 -> 469 entries.

### Step 3 (v4.30.0) – done

- Session object: `Session` per request (`Core::$session`), `AuthSession`, `SessionCsrfTokenSource`, `FormContext`; the
  static session classes, `FormNameRegistry`, the identifier registries and the guards of `AuthUser` / `Authenticator`
  are gone; all data of yuf is below `$_SESSION['yuf']`. Details, layout and what is not covered in
  [docs/session/plan.md](../session/plan.md). Baseline: 469 -> 447 entries.


### Step 4 (v4.31.0) – done

- Area `core`, `Core.php`, `request`, `response`. Baseline 447 -> 375 (`core` 47, `Core.php` 18, `request` 5, `response`
  2 removed, no new entry). Tests 3134 -> 3254, no reflection in the new tests.
- **Env settings:** `Core::config()` / `Core::$config` (`mixed`) are gone; `EnvironmentSettings` (`$core->environmentSettings`) checks
  the six keys `Core` needs (types, time zone) and throws an `UnexpectedValueException` naming the key. Project keys
  (flat, e.g. `mailer.hostname`) are read with `getString()` / `getInt()` / `getBool()` / `getStringList()` / `has()`. `Core` now registers the autoloader for yuf before it reads the env file
  (the settings and directory classes are autoloaded); the application path follows after the directories.
- **Static state:** `Core::$config`, `Core::$httpResponse` (now an instance property) and the registry of `ErrorHandler`
  are removed. The guard `Core::$isInitialized` stays: `Core` registers the global autoloader and error handler once per
  process. `$_SERVER['DOCUMENT_ROOT']` is still read in `Core` (needed before the autoloader and the request exist);
  `src/Core.php` stays in `actraSuperglobalsAllowIn`.
- **Logger (decision of the user):** `Logger` is an interface (`logException()`, `logMessage()`), `FileLogger` the
  implementation (`maxLogSize:` argument for the tests), `RequestLogFormatter` (`@internal`, pure) builds the request part
  of the log: request line without query string, host, IP address, user agent, referrer without query/fragment, server
  variables from an allow-list (`REQUEST_METHOD`, `SERVER_PROTOCOL`, `HTTPS`, `HTTP_HOST`, `SERVER_NAME`, `SERVER_PORT`,
  `REMOTE_ADDR`, `HTTP_USER_AGENT`, `HTTP_ACCEPT_LANGUAGE`, `CONTENT_TYPE`, `CONTENT_LENGTH`, `SCRIPT_NAME`; `REQUEST_URI`
  and `QUERY_STRING` are left out because they carry the query string), GET/POST values masked as `***` for names
  containing `password`, `token`, `secret`, `csrf`, `key`, `auth` (any depth), uploaded files without temporary path,
  cookie names only. Decided by me: files are logged (name, type, size, error), the control characters of request values
  are replaced (log forging).
- **final / extension points:** everything `final` except `BaseView` (abstract, documented), the interfaces `Logger` and
  `ViewFactory`. `ContentType` / `MimeType` constants stay constants (no fixed sets: any file extension and about 600 MIME
  types); no enum introduced. `HttpResponseContent` is a `final readonly` value; `HttpErrorResponseContent` /
  `HttpSuccessResponseContent` no longer extend it.
- **Pipeline testability:** extracted `ContentResponseFactory` (processed content -> response, 404 without content),
  `DirectoryPathResolver` (placeholders of the directory settings), `RequestHandler::findRouteForRootRequest()` (redirect
  target of "/"), `HttpResponse` no longer exits in its constructor (a 304 is sent by `sendAndExit()`) and has
  `getHeader()` / `listHeaders()`. **Still not covered:** `Core::__construct()` and `prepareHttpResponse()` themselves
  (process-wide singleton, env file, `exit`), `ContentHandler::processRequest()` (needs a `Core` for `HtmlDocument`, step
  12), `HttpResponse::sendAndExit()` / `redirectAndExit()`, the 404 / 403 answers for files (they exit), the
  mail/`error_log` delivery of `FileLogger`.
- **Content-Language:** `ContentType::createHtml(languageCode:)` defaults to no language; `ContentResponseFactory` passes
  the language of `RequestHandler` (and `ExceptionHandler` for error pages) to `HttpResponse::createHtmlResponse()`.
- **Open / for later:** `ErrorHandler` throws for every PHP
  error regardless of `error_reporting()` (unchanged); `RequestHandler` keeps four `@phpstan-ignore property.uninitialized`
  for the properties set by `resolveRoute()` (a split into prepared and resolved handler would remove them);
  `FileHandler::getExtension()` (`common`, step 5) returns `false|string` although it never returns `false`.

### Superglobals rule – done

- `actra/coding-standard` raised to v1.3.0; `phpstan.neon` includes `phpstan-no-superglobals.neon`.
- `actraSuperglobalsAllowIn`: `src/Core.php` (`DOCUMENT_ROOT`), `src/core/HttpRequest.php` (`fromGlobals()`),
  `src/session/NativeSessionStorage.php`, `src/session/AbstractSessionHandler.php`, plus the tests that prepare
  superglobals: `SearchHelperRequestTest`, `HttpRequestFromGlobalsTest`, `HttpRequestGetallheadersTest`,
  `AbstractSessionHandlerTest`, `NativeSessionStorageTest`. No baseline entries; `example/` needs no exception.
