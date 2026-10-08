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
- `src/phone/` (port of libphonenumber) and `src/mailer/` (derived from PHPMailer, license notices kept) are brought to
  the full standard like own code, with characterization tests first.

## PHPStan baseline: 375 entries

State after v4.31.0 (532 at v4.27.0, 447 at v4.30.0; v4.31.0 removed `core` 47, `Core.php` 18, `request` 5, `response` 2).

- By area: `phone` 78, `common` 68, `db` 51, `table` 39, `mailer` 33, `api` 27, `auth` 26, `html` 25, `datacheck` 13,
  `exception` 10, `pagination` 3, `session` 2. `core`, `Core.php`, `request`, `response`, `security`, `src/form/` and
  `src/template/` have none.
- Most frequent identifiers: `argument.type` 118, `missingType.iterableValue` 99, `offsetAccess.notFound` 46,
  `return.type` 32, `assign.propertyType` 16, `binaryOp.invalid` 12, `disallowed.isset` 11, `method.nonObject` 11,
  `offsetAccess.nonOffsetAccessible` 10, `property.nonObject` 10, `missingType.parameter` 9.

## Static state (`php.md`, section 1)

- 10 static properties (v4.31.0: `Core::$config`, `Core::$httpResponse` and the registry of `ErrorHandler` are gone, `Core`
  keeps the guard `$isInitialized` because it registers the global autoloader and error handler; v4.29.0: the caches of `HttpRequest`, `RequestBody::$data` and the `SearchHelper` registry are gone;
  v4.30.0: the session holder, `FormNameRegistry`, the identifier registries and the guards of `AuthUser` and `Authenticator`).
- Kept on purpose so far (see `plan.md`, step 10 "Stays" and "Later"):
    - `Core::get()`, `LocaleHandler::get()` / `register()` / `isRegistered()` and `CoreTestInstance` are gone (v4.26.0):
      the template engine, `HtmlSnippet` and `LogFile` get what they need as arguments; `Core` keeps a private guard
      against a second instance (`$isInitialized`);
    - `FrameworkDb::getInstance()` (connection pool);
    - caches: `PhoneMetaData`, `PhoneParser`, `AbstractCurlRequest`, `DbQueryLogList`.
- `$GLOBALS`: none (removed in v4.30.0 with `AbstractSessionHandler::enabled()`).
- `HttpRequest` is an instance since v4.29.0 ([docs/http-request/plan.md](../http-request/plan.md)): the request
  superglobals (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`) are only read in `HttpRequest::fromGlobals()`, in
  `Core` (`$_SERVER['DOCUMENT_ROOT']`) and in `AbstractSessionHandler` (removes an invalid session cookie from
  `$_COOKIE`, because `session_start()` reads it from there). `$_SESSION` is only touched in `NativeSessionStorage` and
  `AbstractSessionHandler` since v4.30.0 ([docs/session/plan.md](../session/plan.md)); projects use `Session`.

## Explicit comparisons (`php.md`, section 5)

- `isset()` 31, `empty()` 2, loose `==` / `!=` 4, short ternary `?:` 14.

## Types (`php.md`, section 3)

- 169 of 288 classes are not `final` (state after v4.31.0; `core`, `Core.php`, `request` and `response` are done). Some are intended extension points (views, forms, fields, columns, exception
  handler); every class needs a review (`final`, or documented extension point, or `@internal`).
- `mixed` in own code: about 15 (e.g. `TableItem::getRawValue()`); `Core::config()` was removed in v4.31.0.
- Enums first: fixed sets still as string constants (126 public string/int constants, not all of them fixed sets), e.g.
  `MailerConstants` (`HttpRequest::PROTOCOL_*` became `ProtocolEnum` in v4.29.0).
- Missing types: see the baseline (`missingType.*`, about 170 entries).

## Exceptions and style (`php.md`, sections 4 and 6)

- Plain `new Exception(…)` instead of SPL or yuf exceptions: 5 (`form` 2, `mailer` 2, `common` 1; the template engine
  throws `TemplateException`; `core` was cleaned in v4.31.0).
- `switch` instead of `match`: 5.
- `@` error suppression: 3.

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
