# Remaining differences to the coding standard

State after v4.26.0 (2026-10-08), `actra/coding-standard` v1.2.0. `composer check` is green; most of the remaining
differences are held in the PHPStan baseline. Counts come from searches in `src/` without the generated phone metadata
(`src/phone/data/`), so they are close, not exact.

Decision of the user (2026-10-08): everything that is still open in yuf, including the areas postponed before (templates,
`src/common/`, `src/core/`, the remaining baseline), is finished before `actra/backend` follows. Work continues with the
postponed areas.

## Decisions for the remaining work

- Order: the template engine first ([docs/template-engine/](../template-engine/plan.md), done with v4.26.0), then the other
  areas.
- `final`: every class becomes `final` unless it is a documented extension point (abstract base class or PHPDoc
  "Extension point: …"); inventory first, including what `actra/backend` extends.
- `src/phone/` (port of libphonenumber) and `src/mailer/` (derived from PHPMailer, license notices kept) are brought to
  the full standard like own code, with characterization tests first.

## PHPStan baseline: 532 entries

- By area: `common` 92, `core` 89, `phone` 78, `db` 51, `table` 47, `mailer` 36, `api` 27, `auth` 26, `html` 25,
  `Core.php` 18, `exception` 15, `datacheck` 13, `request` 5, `session` 3, `pagination` 3, `security` 2, `response` 2.
  `src/form/` and `src/template/` have none (the old engine's 221 entries were removed with it).
- Most frequent identifiers: `argument.type` 127, `missingType.iterableValue` 110, `offsetAccess.notFound` 62,
  `return.type` 43, `assign.propertyType` 18, `binaryOp.invalid` 13, `disallowed.isset` 12,
  `offsetAccess.nonOffsetAccessible` 11, `method.nonObject` 11, `property.nonObject` 10, `missingType.parameter` 10.

## Static state (`php.md`, section 1)

- 27 static properties.
- Kept on purpose so far (see `plan.md`, step 10 "Stays" and "Later"):
    - `Core::get()`, `LocaleHandler::get()` / `register()` / `isRegistered()` and `CoreTestInstance` are gone (v4.26.0):
      the template engine, `HtmlSnippet` and `LogFile` get what they need as arguments; `Core` keeps a private guard
      against a second instance (`$isInitialized`);
    - session: `AbstractSessionHandler::getSessionHandler()` / `enabled()`, `AuthSession`, `CsrfToken`,
      `FormNameRegistry`;
    - `HttpRequest` and its caches;
    - `LogFile` (static facade with a registry of open files);
    - `FrameworkDb::getInstance()` (connection pool), `SearchHelper::getInstance()`;
    - identifier registries of `SmartTable`, `TableFilter`, `AbstractTableFilterField`, `SearchHelper`;
    - caches: `PhoneMetaData`, `PhoneParser`, `AbstractCurlRequest`, `DbQueryLogList`;
    - single-instance guards of `AuthUser` and `Authenticator`.
- `$GLOBALS`: once, in `AbstractSessionHandler::enabled()`.
- Superglobals read outside the request boundary in 12 files: `FormInput`, `UploadInput`, `SessionFileUploadStorage`,
  `HttpResponse`, `Logger`, `CsrfToken`, `ExceptionHandler`, `Core`, `DbResultTable`, `TableFilter`, `SearchHelper`,
  `AbstractMailer`.

## Explicit comparisons (`php.md`, section 5)

- `isset()` 31, `empty()` 2, loose `==` / `!=` 4, short ternary `?:` 14.

## Types (`php.md`, section 3)

- 204 of 286 classes are not `final`. Some are intended extension points (views, forms, fields, columns, exception
  handler); every class needs a review (`final`, or documented extension point, or `@internal`).
- `mixed` in own code: 21 (e.g. `Core::config()`, `TableItem::getRawValue()`).
- Enums first: fixed sets still as string constants (126 public string/int constants, not all of them fixed sets), e.g.
  `ContentType::HTML`, `HttpRequest::PROTOCOL_HTTPS`, `MailerConstants`.
- Missing types: see the baseline (`missingType.*`, about 170 entries).

## Exceptions and style (`php.md`, sections 4 and 6)

- Plain `new Exception(…)` instead of SPL or yuf exceptions: 9 (`core` 4, `form` 2, `mailer` 2, `common` 1; the template engine throws
  `TemplateException`).
- `switch` instead of `match`: 5.
- `@` error suppression: 3.

## Names (`naming.md`)

- All public API names follow the standard (steps 1–7 of `plan.md`); the old template engine with its acronym names
  is deleted.
- `Logger`: private constant `dnl` in lower case.

## Structure and separation (`php.md`, section 1)

- Session object instead of the static session classes; `HttpRequest` as instance (`HttpRequest::fromGlobals()`);
  `LogFile` as logger instance (see `plan.md`, "Later").
- Logic mixed with I/O, e.g. `HtmlDocument` and `ExceptionHandler` (render and read files), `Core` (reads the env file,
  creates directories).

## Tests (`testing.md`)

- Not covered: the request pipeline of `Core`, `HtmlDocument`, redirects, `ExceptionHandler::handleException()` (ends
  with `exit`), the SSO logging of `MicrosoftAuthenticator`.
- Reflection to reset static state: `AuthSessionTest`, `AuthenticatorTest`, `ExceptionHandlerTest`.
