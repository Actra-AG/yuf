# Remaining differences to the coding standard

State after v4.23.0 (2026-10-08), `actra/coding-standard` v1.2.0. `composer check` is green; most of the remaining
differences are held in the PHPStan baseline. Counts come from searches in `src/` without the generated phone metadata
(`src/phone/data/`), so they are close, not exact.

Decision of the user (2026-10-08): everything that is still open in yuf, including the areas postponed before (templates,
`src/common/`, `src/core/`, the remaining baseline), is finished before `actra/backend` follows. Work continues with the
postponed areas.

## PHPStan baseline: 760 entries

- By area: `template` 221, `common` 92, `core` 90, `phone` 78, `db` 51, `table` 47, `mailer` 36, `html` 27, `api` 27,
  `auth` 26, `Core.php` 19, `exception` 18, `datacheck` 13, `request` 5, `session` 3, `pagination` 3, `security` 2,
  `response` 2. `src/form/` has none.
- Most frequent identifiers: `argument.type` 181, `missingType.iterableValue` 126, `offsetAccess.notFound` 75,
  `return.type` 49, `missingType.parameter` 41, `method.nonObject` 40, `binaryOp.invalid` 36, `assign.propertyType` 23,
  `property.nonObject` 19, `offsetAccess.nonOffsetAccessible` 19, `disallowed.isset` 14.

## Static state (`php.md`, section 1)

- 28 static properties.
- Kept on purpose so far (see `plan.md`, step 10 "Stays" and "Later"):
    - `Core::get()` and `LocaleHandler::get()` for the compiled templates (`IfTag`, `SnippetTag`, `LangTag`);
      `Core::get()` also in `HtmlSnippet::render()` (template cache) and `LogFile`;
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

- Plain `new Exception(…)` instead of SPL or yuf exceptions: 34 (mostly `template` 8, `core` 4).
- `switch` instead of `match`: 5.
- `@` error suppression: 3.

## Names (`naming.md`)

- All public API names outside the templates follow the standard (steps 1–7 of `plan.md`).
- Left in `src/template/`: acronym names (`$forUID`, `$forDOM`, `$tagNParts`, 42 places), `str_replace_node()`, the
  class `CDataSectionNode`.
- `Logger`: private constant `dnl` in lower case.

## Structure and separation (`php.md`, section 1)

- Template engine (`src/template/`): the largest remaining area.
- Session object instead of the static session classes; `HttpRequest` as instance (`HttpRequest::fromGlobals()`);
  `LogFile` as logger instance (see `plan.md`, "Later").
- Logic mixed with I/O, e.g. `HtmlDocument` and `ExceptionHandler` (render and read files), `Core` (reads the env file,
  creates directories).

## Tests (`testing.md`)

- Not covered: the request pipeline of `Core`, `HtmlDocument`, redirects, `ExceptionHandler::handleException()` (ends
  with `exit`), the SSO logging of `MicrosoftAuthenticator`.
- Reflection to reset static state: `AuthSessionTest`, `AuthenticatorTest`, `ExceptionHandlerTest`.
