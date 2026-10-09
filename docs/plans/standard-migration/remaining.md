# Remaining differences to the coding standard

Final state after v4.57.0 (2026-10-08), `actra/coding-standard` v1.3.0. Two plans brought yuf to the standard:
[docs/plans/standard-completion/plan.md](../standard-completion/plan.md) (v4.28.0–v4.41.0, every area of `src/`, empty
PHPStan baseline) and [docs/plans/standard-finish/plan.md](../standard-finish/plan.md) (v4.42.0–v4.57.0, security gaps, a
testable `Core`, the open design points, small functional gaps, line lengths and new features). `composer check` is
green (20554 tests), the PHPStan baseline is empty. yuf is done; `actra/backend` follows next.

Counts come from searches in `src/` (396 files) without the generated phone metadata (`src/phone/data/`).

## Follow-up for `actra/backend`

Its plan is `../backend/docs/standard-migration/plan.md`; every breaking change is in `UPGRADE.md` with before/after
(v4.11.0–v4.57.0). Found in read-only searches during the plans:

- `new Core(` -> `Core::fromEnvironment(` (v4.45.0).
- `SearchHelper` -> `SearchState::create(…)` (`AbstractSearchForm`, still uses the removed `getInstance()`) and
  `SearchQueryBuilder::createBooleanQuery(` (`UserTable`, `TokenTable`, `VisitTable`) (v4.48.0).
- `RequestHandler::get()->route` / `->pathVars` (`BackendView`) -> `ViewContext` (v4.29.0, v4.49.0).
- `SearchQueryField`, `SearchSelectOptionsField`: `prepareHtmlTag()` -> `createHtmlTag()` (v4.51.0) and
  `new HtmlTagAttribute(…)` -> `HtmlTagAttribute::fromText()` (v4.41.0).
- `LanguageSwitcherTest`: `->data` of `HtmlDataObject` -> `toTemplateData()` (v4.50.0).
- `HttpResponse::redirectAndExit()` (about 20 views) keeps working; it returns `never` since v4.44.0.
- `ActraBackend` adds a navigation item `users`: a project collection with an own `users` item throws since v4.52.0.
- Everything listed for v4.29.0–v4.41.0 (`HttpRequest` instance, `Session`, `FormContext`, `DbSettings` /
  `FrameworkDb`, `SmtpMailer` arguments, `IpTypeEnum::IP`, `HtmlDocument::get()`, `HtmlText` names, table constants).

## Coding standard

- PHPStan baseline: 0 entries. `@phpstan-ignore`: 3, each with a reason (`AmountParser`, `MultiOptionsField`,
  `HtmlEncoder`); the four of `RequestHandler` are gone since v4.49.0.
- Explicit comparisons: `isset()` / `empty()` 0, loose `==` / `!=` 0; `switch` 0; `@` 0; `new Exception(` 0.
- Lines ≤ 120 characters in `src/`, `tests/` and `example/` (v4.52.1).
- `final`: 51 class declarations are not `final`; all of them are abstract bases or documented extension points
  ("Extension point: …"), every class has been reviewed.
- `mixed`: 51 lines in own code, all at the boundary where untyped data is narrowed (request, session, JSON, env file,
  cURL, `TableItem::getRawValue()`).
- Enums: fixed sets are enums; 86 public `string` / `int` constants remain (placeholders, defaults, limits, keys –
  no fixed sets).
- Static state: `Core::$isInitialized`, the once-per-process guard of `Core::fromEnvironment()` (it registers the global
  autoloader and error handler). The constructor of `Core` has none since v4.45.0.
- Superglobals: only in `Core` (`DOCUMENT_ROOT` in `fromEnvironment()`), `HttpRequest::fromGlobals()`,
  `NativeSessionStorage` and `AbstractSessionHandler` (`actraSuperglobalsAllowIn`).
- Reflection in tests: only to assert `final` / `abstract` / missing methods of the API (`ExtensionPointsTest` and a
  few value tests), never to reach private code or reset state.

## Not covered by tests

Needs the real process, the network or a real upload; each place is documented in its PHPDoc:

- `Core::fromEnvironment()` (global, once per process) and `NativeResponseSender::send()` (`header()`,
  `fastcgi_finish_request()`, `exit`; its output part `writeContent()` is tested).
- `SessionFileUploadStorage::store()` (`is_uploaded_file()` / `move_uploaded_file()` refuse every other file).
- The DNS check of `SystemMailDomainResolver`, the SSO logging of `MicrosoftAuthenticator`.
- `GraphMailer` and `MicrosoftClientCredentialsTokenProvider` are tested against local scripted servers only; three
  points need one check against a real Microsoft 365 tenant: the request body type `text/plain; charset=utf-8`
  (Graph documents `text/plain`), delivery to `Bcc` recipients of the MIME header, and an attachment of about 2 MB
  (4 MB request limit, Base64 twice).

## Open points (no standard violation, decisions or ideas for later)

- **Performance:** moved to [docs/plans/performance/plan.md](../performance/plan.md).
- **Session:** a request with a session cookie that never touches the session sends no `Set-Cookie` on the Lax
  redirect change (the browser keeps the Strict cookie); the preferred language is only remembered on requests that
  use the session anyway (both decided).
- **Phone:** carrier codes and `nationalPrefixOptionalWhenFormatting` of libphonenumber are not ported;
  `PhoneMatcher::groupCount()` counts groups up to the last matched one (fine for the metadata, which has one group).
- **Uploads:** `GraphMailer` has no upload sessions, so attachments above about 2 MB need SMTP.
