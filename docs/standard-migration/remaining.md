# Remaining differences to the coding standard

State after v4.27.0 (2026-10-08), `actra/coding-standard` v1.2.0. `composer check` is green; most of the remaining
differences are held in the PHPStan baseline. Counts come from searches in `src/` without the generated phone metadata
(`src/phone/data/`), so they are close, not exact.

Decision of the user (2026-10-08): everything that is still open in yuf, including the areas postponed before (templates,
`src/common/`, `src/core/`, the remaining baseline), is finished before `actra/backend` follows. Work continues with the
postponed areas.

The plan for the remaining work is [docs/standard-completion/plan.md](../standard-completion/plan.md).

## Decisions for the remaining work

- Order: the template engine first ([docs/template-engine/](../template-engine/plan.md), done with v4.27.0, own tags included), then the other
  areas.
- `final`: every class becomes `final` unless it is a documented extension point (abstract base class or PHPDoc
  "Extension point: …"); inventory first, including what `actra/backend` extends.
- `src/phone/` (port of libphonenumber, done in v4.33.0) and `src/mailer/` (derived from PHPMailer, license notices kept, done in v4.36.0) are brought to
  the full standard like own code, with characterization tests first.

## PHPStan baseline: 75 entries

State after v4.37.0 (532 at v4.27.0, 447 at v4.30.0, 375 at v4.31.0, 307 at v4.32.0, 229 at v4.33.0, 176 at v4.34.0, 136 at v4.35.0, 103 at v4.36.0; v4.37.0 removed `auth` 26 and `session` 2).

- By area: `api` 27, `html` 25, `datacheck` 13, `exception` 10. `core`, `Core.php`,
  `request`, `response`, `common`, `phone`, `db`, `table`, `pagination`, `mailer`, `auth`, `security`, `session`,
  `src/form/` and `src/template/` have none.
- Most frequent identifiers (counted with `count:` at v4.33.0, 250 errors in 229 entries): `argument.type` 64,
  `missingType.iterableValue` 62, `offsetAccess.notFound` 20, `return.type` 13, `disallowed.isset` 10, `binaryOp.invalid` 8,
  `method.nonObject` 7, `assign.propertyType` 5, `if.condNotBoolean` 5, `missingType.parameter` 5, `disallowed.switch` 5,
  `offsetAccess.invalidOffset` 4.

## Static state (`php.md`, section 1)

- 4 static properties (v4.34.0: `FrameworkDb::$instances` (connection pool), `DbSettings::$instances` and the static query log
  `DbQueryLogList::$stack` are gone; v4.33.0: the caches of `PhoneMetaData` and the `PhoneParser` singleton are gone; v4.31.0: `Core::$config`, `Core::$httpResponse` and the registry of `ErrorHandler` are gone, `Core`
  keeps the guard `$isInitialized` because it registers the global autoloader and error handler; v4.29.0: the caches of `HttpRequest`, `RequestBody::$data` and the `SearchHelper` registry are gone;
  v4.30.0: the session holder, `FormNameRegistry`, the identifier registries and the guards of `AuthUser` and `Authenticator`).
- Kept on purpose so far (see `plan.md`, step 10 "Stays" and "Later"):
    - `Core::get()`, `LocaleHandler::get()` / `register()` / `isRegistered()` and `CoreTestInstance` are gone (v4.26.0):
      the template engine, `HtmlSnippet` and `LogFile` get what they need as arguments; `Core` keeps a private guard
      against a second instance (`$isInitialized`);
    - caches: `AbstractCurlRequest` (`PhoneMetaData` and `PhoneParser` were replaced by the
      `PhoneMetaDataRepository` instance in v4.33.0).
- `$GLOBALS`: none (removed in v4.30.0 with `AbstractSessionHandler::enabled()`).
- `HttpRequest` is an instance since v4.29.0 ([docs/http-request/plan.md](../http-request/plan.md)): the request
  superglobals (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`) are only read in `HttpRequest::fromGlobals()`, in
  `Core` (`$_SERVER['DOCUMENT_ROOT']`) and in `AbstractSessionHandler` (removes an invalid session cookie from
  `$_COOKIE`, because `session_start()` reads it from there). `$_SESSION` is only touched in `NativeSessionStorage` and
  `AbstractSessionHandler` since v4.30.0 ([docs/session/plan.md](../session/plan.md)); projects use `Session`.

## Explicit comparisons (`php.md`, section 5)

- `isset()` 1, `empty()` 0, loose `==` / `!=` 1 (searched in `src/` after v4.36.0; `common`, `phone`, `db` and `mailer` are clean).

## Types (`php.md`, section 3)

- About 76 `class` declarations are not `final` (searched after v4.37.0; `core`, `Core.php`, `request`, `response`, `common`, `phone`, `db`, `table`, `pagination`, `mailer`, `auth`, `security` and `session` are done; `AbstractMail` and `AbstractMailer` stay documented extension points; `AuthUser`, `Authenticator`, `MicrosoftAuthenticator`, `AuthWebToken` and `AbstractSessionHandler` are documented extension points; `FrameworkDb`, `DbResultTable`, `SmartTable`, `AbstractTableColumn`, `TableHeadRenderer`, `TableFilter` and `AbstractTableFilterField` stay documented extension points). Some are intended extension points (views, forms, fields, columns, exception
  handler); every class needs a review (`final`, or documented extension point, or `@internal`).
- `mixed` in own code: about 15 (e.g. `TableItem::getRawValue()`, documented as the values of any data source); `Core::config()` was removed in v4.31.0; `common` keeps only
  `JsonUtils::convertToJsonString(mixed)` and the narrowed JSON/XML data.
- Enums first: fixed sets still as string constants (about 80 public string/int constants after v4.36.0, not all of them fixed sets); done so far:
  (`HttpRequest::PROTOCOL_*` became `ProtocolEnum` in v4.29.0; the sets of `MailerConstants` became enums in v4.36.0).
- Missing types: see the baseline (`missingType.*`, about 70 entries).

## Exceptions and style (`php.md`, sections 4 and 6)

- Plain `new Exception(…)` instead of SPL or yuf exceptions: 2 (`form`; `mailer` was cleaned in v4.36.0, `common` in v4.32.0; the template
  engine throws `TemplateException`; `core` was cleaned in v4.31.0).
- `switch` instead of `match`: 0 (the five of `mailer` are gone).
- `@` error suppression: 0.

## Names (`naming.md`)

- All public API names follow the standard (steps 1–7 of `plan.md`); the old template engine with its acronym names
  is deleted.

## Structure and separation (`php.md`, section 1)

- `LogFile` is an instance class since v4.28.0, `HttpRequest` since v4.29.0, the session (`Session`, `AuthSession`,
  `SessionCsrfTokenSource`, `FormContext`) since v4.30.0; no redesign of static state is open any more.
- Logic mixed with I/O, e.g. `HtmlDocument` and `ExceptionHandler` (render and read files), `Core` (reads the env file,
  creates directories).

## Tests (`testing.md`)

- Not covered: `Core::__construct()` and `prepareHttpResponse()` themselves (`Core` is a process-wide singleton that
  reads the env file; since v4.31.0 their parts are tested: `EnvironmentSettings`, `DirectoryPathResolver`,
  `ContentResponseFactory`, `RequestHandler::findRouteForRootRequest()`, `HttpResponse` incl. the 304 decision;
  `ContentHandler::processRequest()` needs a `Core` for `HtmlDocument`), `HtmlDocument`, redirects, `ExceptionHandler::handleException()` (ends
  with `exit`), the SSO logging of `MicrosoftAuthenticator`.
- Reflection to reset static state: `ExceptionHandlerTest` (the session tests have none since v4.30.0).
