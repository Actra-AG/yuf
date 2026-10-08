# Remaining differences to the coding standard

> In progress: the open points below are worked through in
> [docs/standard-finish/plan.md](../standard-finish/plan.md) (v4.42.0 and later, see its handover notes and
> `UPGRADE.md`). This file is updated to the final state in the last step of that plan.

Final state after v4.41.0 (2026-10-08), `actra/coding-standard` v1.3.0: the plan
[docs/standard-completion/plan.md](../standard-completion/plan.md) is complete, `composer check` is green and the PHPStan
baseline is empty. Only the open points below remain. Counts come from searches in `src/` without the generated phone
metadata (`src/phone/data/`), so they are close, not exact.

Decision of the user (2026-10-08): everything that is still open in yuf is finished before `actra/backend` follows. That
is done: `actra/backend` follows next (its follow-up per release is in `UPGRADE.md`).

## Decisions for the remaining work

- Order: the template engine first ([docs/template-engine/](../template-engine/plan.md), done with v4.27.0, own tags included), then the other
  areas.
- `final`: every class becomes `final` unless it is a documented extension point (abstract base class or PHPDoc
  "Extension point: …"); inventory first, including what `actra/backend` extends.
- `src/phone/` (port of libphonenumber, done in v4.33.0) and `src/mailer/` (derived from PHPMailer, license notices kept, done in v4.36.0) are brought to
  the full standard like own code, with characterization tests first.

## PHPStan baseline: 0 entries

State after v4.41.0 (v4.41.0 had none to remove; 23 at v4.39.0, 532 at v4.27.0, 447 at v4.30.0, 375 at v4.31.0, 307 at v4.32.0, 229 at v4.33.0, 176 at v4.34.0, 136 at v4.35.0, 103 at v4.36.0, 75 at v4.37.0; v4.38.0 removed `api` 27, 48 at v4.38.0; v4.39.0 removed `html` 25).

- By area: none. `datacheck`, `exception`, `core`, `Core.php`,
  `request`, `response`, `common`, `phone`, `db`, `table`, `pagination`, `mailer`, `auth`, `security`, `session`, `api`, `html`, `layout`,
  `form` and `template` have none.
- Most frequent identifiers (counted with `count:` at v4.33.0, 250 errors in 229 entries): `argument.type` 64,
  `missingType.iterableValue` 62, `offsetAccess.notFound` 20, `return.type` 13, `disallowed.isset` 10, `binaryOp.invalid` 8,
  `method.nonObject` 7, `assign.propertyType` 5, `if.condNotBoolean` 5, `missingType.parameter` 5, `disallowed.switch` 5,
  `offsetAccess.invalidOffset` 4.

## Static state (`php.md`, section 1)

- 1 static property, `Core::$isInitialized` (v4.40.0: `ExceptionHandler::$registeredInstance` is gone; v4.38.0: the shared cURL handle and the request registry of `AbstractCurlRequest` are gone, `CurlClient` keeps a handle per instance; v4.34.0: `FrameworkDb::$instances` (connection pool), `DbSettings::$instances` and the static query log
  `DbQueryLogList::$stack` are gone; v4.33.0: the caches of `PhoneMetaData` and the `PhoneParser` singleton are gone; v4.31.0: `Core::$config`, `Core::$httpResponse` and the registry of `ErrorHandler` are gone, `Core`
  keeps the guard `$isInitialized` because it registers the global autoloader and error handler; v4.29.0: the caches of `HttpRequest`, `RequestBody::$data` and the `SearchHelper` registry are gone;
  v4.30.0: the session holder, `FormNameRegistry`, the identifier registries and the guards of `AuthUser` and `Authenticator`).
- Kept on purpose so far (see `plan.md`, step 10 "Stays" and "Later"):
    - `Core::get()`, `LocaleHandler::get()` / `register()` / `isRegistered()` and `CoreTestInstance` are gone (v4.26.0):
      the template engine, `HtmlSnippet` and `LogFile` get what they need as arguments; `Core` keeps a private guard
      against a second instance (`$isInitialized`);
    - caches: none (`AbstractCurlRequest` was replaced by `CurlClient` in v4.38.0, `PhoneMetaData` and `PhoneParser` by the
      `PhoneMetaDataRepository` instance in v4.33.0).
- `$GLOBALS`: none (removed in v4.30.0 with `AbstractSessionHandler::enabled()`).
- `HttpRequest` is an instance since v4.29.0 ([docs/http-request/plan.md](../http-request/plan.md)): the request
  superglobals (`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`) are only read in `HttpRequest::fromGlobals()`, in
  `Core` (`$_SERVER['DOCUMENT_ROOT']`) and in `AbstractSessionHandler` (removes an invalid session cookie from
  `$_COOKIE`, because `session_start()` reads it from there). `$_SESSION` is only touched in `NativeSessionStorage` and
  `AbstractSessionHandler` since v4.30.0 ([docs/session/plan.md](../session/plan.md)); projects use `Session`.

## Explicit comparisons (`php.md`, section 5)

- `isset()` 0, `empty()` 0, loose `==` / `!=` 0 (searched in `src/` after v4.41.0; the one `!=` in `SearchHelper` is SQL text).

## Types (`php.md`, section 3)

- 50 `class` declarations are not `final` (`^(abstract )?class` in `src/` without `src/phone/data/` after v4.41.0, 86 at v4.40.0, 93 at v4.39.0; all of them are abstract bases or documented extension points (`form`: `Form`, `TextField`, `TextAreaField`, `SelectOptionsField`, `CheckboxOptionsField`, `RadioOptionsField`, `BooleanField`, `IntegerField`, `FormControl`, `InputFieldRenderer` and the abstract bases; the 13 concrete rules, 17 renderers and 6 fields/components are `final` since v4.41.0); `exception` (`ExceptionHandler`, `UnauthorizedException` stay documented extension points) and `datacheck` are done in v4.40.0; `html` and `layout` are done: `HtmlElement` and `HtmlDataObject` stay documented extension points; the six request classes and `CurlResponse` of `api` are `final` now, `AbstractCurlRequest` stays abstract; `core`, `Core.php`, `request`, `response`, `common`, `phone`, `db`, `table`, `pagination`, `mailer`, `auth`, `security` and `session` are done; `AbstractMail` and `AbstractMailer` stay documented extension points; `AuthUser`, `Authenticator`, `MicrosoftAuthenticator`, `AuthWebToken` and `AbstractSessionHandler` are documented extension points; `FrameworkDb`, `DbResultTable`, `SmartTable`, `AbstractTableColumn`, `TableHeadRenderer`, `TableFilter` and `AbstractTableFilterField` stay documented extension points). Every class has been reviewed (`final`, or documented extension point, or `@internal`).
- `mixed` in own code: about 15 (e.g. `TableItem::getRawValue()`, documented as the values of any data source); `Core::config()` was removed in v4.31.0; `common` keeps only
  `JsonUtils::convertToJsonString(mixed)` and the narrowed JSON/XML data.
- Enums first: fixed sets still as string constants (about 80 public string/int constants after v4.36.0, not all of them fixed sets); done so far:
  (`HttpRequest::PROTOCOL_*` became `ProtocolEnum` in v4.29.0; the sets of `MailerConstants` became enums in v4.36.0).
- Missing types: see the baseline (`missingType.*`, about 70 entries).

## Exceptions and style (`php.md`, sections 4 and 6)

- Plain `new Exception(…)` instead of SPL or yuf exceptions: 0 (`form` was cleaned in v4.41.0; `mailer` in v4.36.0, `common` in v4.32.0; the template
  engine throws `TemplateException`; `core` was cleaned in v4.31.0).
- `switch` instead of `match`: 0 (the five of `mailer` are gone).
- `@` error suppression: 0.

## Names (`naming.md`)

- All public API names follow the standard (steps 1–7 of `plan.md`); the old template engine with its acronym names
  is deleted.

## Structure and separation (`php.md`, section 1)

- `LogFile` is an instance class since v4.28.0, `HttpRequest` since v4.29.0, the session (`Session`, `AuthSession`,
  `SessionCsrfTokenSource`, `FormContext`) since v4.30.0; no redesign of static state is open any more.
- Logic mixed with I/O, e.g. `ExceptionHandler` is split since v4.40.0 (`handleException()` only sends; `HtmlDocument` reads its content and template files, but takes everything else as `HtmlDocumentSettings` since v4.39.0), `Core` (reads the env file,
  creates directories).

## Tests (`testing.md`)

- Not covered: `Core::__construct()` and `prepareHttpResponse()` themselves (`Core` is a process-wide singleton that
  reads the env file; since v4.31.0 their parts are tested: `EnvironmentSettings`, `DirectoryPathResolver`,
  `ContentResponseFactory`, `RequestHandler::findRouteForRootRequest()`, `HttpResponse` incl. the 304 decision;
  `ContentHandler::processRequest()` needs a `Core` for the `HtmlDocumentSettings`; `HtmlDocument` itself is tested since v4.39.0), redirects, `ExceptionHandler::handleException()` (ends
  with `exit`; its response is tested through `createResponse()` since v4.40.0), the SSO logging of `MicrosoftAuthenticator`.
- Reflection to reset static state: none (`ExceptionHandlerTest` has none since v4.40.0).

## Open points

Collected from the "Open / for later" notes of [plan.md](../standard-completion/plan.md) (steps 4 to 14); none of them
breaks the standard, all of them are decisions or features for later.

- **`actra/backend` follows:** per release as listed in `UPGRADE.md` (v4.29.0 to v4.41.0): `HttpRequest` instance, `Session`,
  `FormContext`, `DbSettings` / `FrameworkDb`, `SmtpMailer` arguments, `IpTypeEnum::IP`, `HtmlDocument::get()`, `HtmlText`
  names, `HtmlTagAttribute::fromText()` in `SearchQueryField` / `SearchSelectOptionsField` (v4.41.0), table constants.
- **Design decisions for later:** `SearchHelper` has two purposes (SQL builders and search state); `CsvFile` is mutable;
  `RequestHandler` keeps four `@phpstan-ignore property.uninitialized`; `ErrorHandler` throws for every PHP error
  regardless of `error_reporting()`; `HtmlDataObject` is a mutable `stdClass` wrapper; `FormRenderer` keeps the two-phase
  `prepare()` / `getHtmlTag()` API and a component can be rendered once; `ToggleField` / `MultiToggleField` duplicate
  their child methods.
- **Functional gaps (new features, not standard):** no phone number validity per type; `FileField` checks neither type nor
  size of an upload; IBAN length per country is not checked; no IPv4-mapped IPv6 in IP whitelists; `acceptRedirectionResponseCode()`
  knows 301 and 303 only; `SmtpMailer` has `AUTH LOGIN` and STARTTLS only; `CountryCodeEnum` is unused and has two non-ISO
  codes.
- **Not covered by tests (needs `exit`, `Core` or the network):** `Core::__construct()`, `ContentHandler::processRequest()`,
  `HttpResponse::sendAndExit()` / `redirectAndExit()`, `ExceptionHandler::handleException()`, the DNS check of
  `SystemMailDomainResolver`, `SessionFileUploadStorage::store()` (real upload).
