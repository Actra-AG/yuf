# Plan: finish yuf

What is still open after [docs/plans/done/standard-completion/plan.md](../standard-completion/plan.md) (v4.41.0, empty PHPStan
baseline), taken from [docs/plans/done/standard-migration/remaining.md](../standard-migration/remaining.md). One step per release,
each small enough to release on its own. `actra/backend` follows when the plan is done (its
`docs/plans/done/standard-migration/plan.md`).

## Decisions (user)

- Order: security gaps, then a testable `Core`, then the remaining design points, the small functional gaps, the line
  lengths, and the new features last.
- **Uploads:** `FileField` gets a required allow-list `allowedFileTypes:` (MIME type and extensions per entry) and
  `maxFileSize:` (default 10 MB). The type is detected from the file content (`finfo`, `ext-fileinfo` is added to
  `require`), never taken from the client; `UploadedFile::$type` becomes the detected type.
- **PHP errors:** `ErrorHandler` respects `error_reporting()` (levels outside it and errors silenced with `@`, e.g. in
  vendor code, are ignored); every reported level throws a `PhpException`, deprecations too. Reporting everything is
  the default: `defaultErrorReporting` in the env file becomes optional with the default `E_ALL`.
- **Core:** `new Core(envFilePath: …)` becomes `Core::fromEnvironment(envFilePath: …)` with the same arguments (the
  global part: autoloader, env file, `error_reporting()`, time zone, directories, error handler, request from the
  globals, the once-per-process guard). The constructor takes explicit settings (`CoreSettings`, `HttpRequest`,
  `ResponseSender`) and touches no globals, so tests build `Core` directly. `HttpResponse` sends through a small
  `ResponseSender` interface (`sendAndExit()` / `redirectAndExit()` keep their names; tests pass a double). The HTTPS
  redirect and the 405 response leave the constructor and become responses.
- **Performance (user, after step 3):** responses go out as fast as possible. `NativeResponseSender` ends the
  request with `fastcgi_finish_request()` (if available) before `exit`, so session write, destructors and shutdown run
  after the client has the response; the session starts lazily and its lock is released early (steps 5 and 6); the template
  tags are built once per request; the directories are only checked/created in `Core::fromEnvironment()`; README:
  production settings (`opcache.validate_timestamps=0`, optional preloading). No early flush or streaming of HTML
  (ETag, 304 and `Content-Length` need the whole content).
- **New features:** all three are planned (steps 13 to 15); their scope is asked at the start of each step.
- No backwards compatibility: renames and removals without aliases, every breaking change ⚠️ with before/after in
  `UPGRADE.md`, as before.

## Definition of done for a step

1. Characterization tests first where behaviour changes or is not covered (hand-written doubles in `tests/Double/`, no
   reflection).
2. Baseline stays empty; no new `@phpstan-ignore` (the step that removes the four of `RequestHandler` leaves none).
3. Coding standard for every touched line (explicit comparisons, `match`, SPL / yuf exceptions, `final` or documented
   extension point, enums, names, lines ≤ 120 characters, no new static state).
4. `UPGRADE.md` section, README if affected, handover note here, `ddev composer check` green, `example/` checked
   (`curl` and, for HTML changes, the browser).

## Steps

### Security

1. **v4.42.0 – `ErrorHandler` respects `error_reporting()` (⚠️ behaviour):** `handlePhpError()` returns `false` (PHP
   goes on with its standard handling) when `(error_reporting() & $errorCode) === 0`, otherwise it throws as now
   (return type `bool`, documented "never returns `true`"). `EnvironmentSettings`: `defaultErrorReporting` optional,
   default `E_ALL`. Tests: reported level throws, unreported level and `@` do not, the default. `UPGRADE.md`: `@` and a
   lower `defaultErrorReporting` work now (before: every error threw).
2. **v4.43.0 – upload type and size (⚠️):** `FileField` arguments `allowedFileTypes:` (list of a new `UploadFileType`
   value object: MIME type + allowed extensions, with named constructors for the common types, e.g. `pdf()`, `jpeg()`,
   `png()`, … decided in the step) and `maxFileSize:` (bytes, default 10 MB, > 0). A pure `UploadTypeChecker` (or
   similar) decides with the detected MIME type (`finfo` on the temporary file, behind a small `FileTypeDetector`
   interface so tests need no real upload) and the extension of the client name (lower case); the client MIME type is
   ignored. New messages in `FormMessages` (`fileTypeNotAllowed`, `fileExceedsMaxSize`, with placeholders), checked before
   `store()`. `UploadedFile::$type` is the detected type. `composer.json`: `ext-fileinfo`. `example/` has no upload
   (check). Also check `SessionFileUploadStorage::store()`: keep the stored file name made from the PHP temp name.

### Testable Core

3. **v4.44.0 – `ResponseSender` (⚠️ small):** interface `ResponseSender` (send status, headers, body or file, end the
   process: `never`), production `NativeResponseSender` (`header()`, `echo`, `readfile()`, `exit`), test double that
   records and throws (a `never` method may throw). `HttpResponse::sendAndExit(ResponseSender $sender = new
   NativeResponseSender())`, `redirectAndExit(…, ResponseSender $sender = …)`, `createResponseFromFilePath()` without
   `exit` (404 / 403 as response). `ExceptionHandler::handleException()` sends through the sender of its context. Tests:
   `sendAndExit()` (headers, body, file, 304), `redirectAndExit()`, `handleException()`. `BaseView`, `CsvFile`,
   `FileHandler`, `MicrosoftAuthenticator`, `RequestHandler` pass the sender where they have one (`ViewContext`).
4. **v4.45.0 – `Core` from explicit settings (⚠️):** `Core::fromEnvironment(…)` (the global part, once per process) and
   a constructor with `CoreSettings` (directories, env settings, copyright year), `HttpRequest`, `ResponseSender`.
   HTTPS redirect and 405 become responses of `prepareHttpResponse()` (no `exit` in the constructor).
   `ContentHandler::processRequest()` gets what it needs instead of `Core` (`HtmlDocumentSettings` parts, session,
   form context). Tests: `Core` constructor and `prepareHttpResponse()` with a temp directory, routes and views of
   `tests/Double/`; `processRequest()`. The guard `$isInitialized` moves to `fromEnvironment()`. `example/public/index.php`
   and README updated. Performance: `NativeResponseSender` calls `fastcgi_finish_request()` (if the function exists)
   after the output and before `exit`; `prepareHttpResponse()` builds the template tags once (today twice: check +
   engine); the explicit constructor takes resolved directories without file system checks (only `fromEnvironment()`
   checks and creates them); README section on production settings (opcache `validate_timestamps=0`, preloading).
5. **v4.46.0 – lazy session, short lock (⚠️ behaviour):** the session handler does not call `session_start()` in its
   constructor; the session starts on the first access of `Session` (read or write), so requests that never touch it
   take no lock and send no cookie. Early release of the lock: after the response is created (before sending) the
   session is written and closed (`session_write_close()`), and/or an explicit `Session::close()` for long views
   (decided in the step, ask the user if it changes the API). Keep the security behaviour (strict mode, ID
   regeneration, trusted client check, cookie SameSite/Lax change for redirects) and its tests; measure parallel
   requests of one session before/after in `example/`.

6. **v4.47.0 – preferred language without a new session (⚠️ behaviour, decision of the user after step 5):** routes
   with a language remember the preferred language only if the session is active: started in this request or the
   request carries a session cookie (resuming an existing session). Visitors without a session get the language
   from the URL and start no session (no lock, no cookie). The redirect of "/" reads the preferred language only
   from an active session. New `Session::isActive()` (through `SessionStorage`, ⚠️ for own storages); tests for both
   cases in `RequestHandler`.
### Design points

7. **v4.48.0 – split `SearchHelper` (⚠️):** pure static SQL builders (`createSqlFilters()`, `createBooleanQuery()`,
   `createSqlSearch()`) into their own class (e.g. `SearchQueryBuilder`), the stored search state (`create()`,
   `check…()`) stays or becomes `SearchState`; names decided in the step per `naming.md`. `UPGRADE.md` for
   `actra/backend` (`createBooleanQuery()` and the removed `getInstance()` -> `create()`).
8. **v4.49.0 – resolved route of `RequestHandler` (⚠️):** `resolveRoute()` returns a readonly value object (route,
   language, file title / extension / name / group, route variables, path vars) used by `Core`, `ContentHandler`,
   `ExceptionHandler`; the four `@phpstan-ignore property.uninitialized` go away.
9. **v4.50.0 – `HtmlDataObject` and `CsvFile`:** `HtmlDataObject` stores an array instead of a shared `stdClass`
   (a child added with `addDataObject()` is copied, later changes of the child do not leak); the template engine
   reads the same selectors. `CsvFile`: decided in the step (builder with `addRow()` is fine; immutable result or
   documented builder). Extension point `HtmlDataObject` stays (`actra/backend` uses it).
10. **v4.51.0 – `FormRenderer` and toggle fields (⚠️ for own renderers):** one-phase renderer API (`render(): HtmlTag`
   without stored tag, so a component can be rendered more than once; `prepare()` / `getHtmlTag()` / `setHtmlTag()`
   removed or adapted); `ToggleField` / `MultiToggleField` share the child methods (trait or delegation via
   `ToggleChildren`). HTML output byte-identical (form and `example/` tests). `UPGRADE.md` with before/after for own
   renderers.

### Small functional gaps

11. **v4.52.0 – validation gaps (⚠️ behaviour):** IBAN length per country (table in `IbanValidator`); IPv4-mapped IPv6
   addresses (`::ffff:a.b.c.d`) match IPv4 whitelist entries (normalized before the comparison); `CountryCodeEnum`:
   remove the non-ISO `AA` and `UR` (`UY` exists); `TableFilter` throws for a second field with the same identifier,
   `NavigationItemCollection::addItem()` for a second item with the same `navKey`; `DateFilterField` accepts the
   date format only (no relative dates like "tomorrow").

### Style

12. **v4.52.1 – lines ≤ 120 characters:** the 3 lines in `src/` (`HtmlTag`, `CurlFormEncoder`, `Form`) and the 63 in
    `tests/`. No behaviour change.

### New features (scope asked at the start of each step)

13. **v4.53.0 – phone numbers:** validity per number type, E.164 and national format in `src/phone/`.
13.1. **v4.53.1 – phone matcher (decision of the user after step 13):** `PhoneMatcher::matches()` matches the whole
     number with every alternative of the pattern (as Java's `matches()`, which libphonenumber uses); the parser keeps
     the national prefix when the number without it no longer matches (GA fixed line example). Tests over all example
     numbers through the parser.
13.2. **v4.54.0 – `PhoneNumberField` checks validity by default (⚠️ behaviour, decision of the user after step
     13.1):** without `allowedNumberTypes:` the field accepts valid numbers only (today: possible numbers, i.e. the
     length only, so `044 668 18 00 / 12` passes since v4.53.1).
14. **v4.55.0 – SMTP authentication:** `AUTH PLAIN` besides `AUTH LOGIN` (chosen from the methods the server announces
    in its EHLO answer, or fixed by an argument) and `XOAUTH2` (Microsoft 365, Gmail) with the token from a small
    interface (`OAuthTokenProvider`, implemented by the project or by step 15); no `CRAM-MD5`.
15. **v4.56.0 – Microsoft Graph mailer (decision of the user: Microsoft ends basic SMTP authentication):** a mailer
    that sends through the Graph API (`POST /users/{sender}/sendMail` with the MIME message that yuf builds, so
    attachments, HTML and headers work as with SMTP), authenticated with the OAuth 2.0 client credentials flow
    (tenant, client id, client secret) through `CurlClient`; the token provider also serves `XOAUTH2` of step 14. Size
    limit of the Graph request (4 MB) checked with a clear exception. Tests with the local HTTP server doubles of
    `tests/Double/api/`.
16. **v4.57.0 – redirect codes:** `acceptRedirectionResponseCode()` also for 302, 307, 308.

### End

17. Update [docs/plans/done/standard-migration/remaining.md](../standard-migration/remaining.md) to the final state, so that
    `actra/backend` can follow.

## Handover notes

### Step 1 (v4.42.0) – done

- `ErrorHandler::handlePhpError()` returns `bool`: `false` when `(error_reporting() & $errorCode) === 0` (covers `@`),
  otherwise it throws `PhpException` as before (deprecations too). PHPDoc: never returns `true`.
- `EnvironmentSettings`: `defaultErrorReporting` optional, default `E_ALL` (a wrong type still throws); new private
  `readOptionalInteger()`. README table, class PHPDoc and `UPGRADE.md` (`## [v4.42.0]`) adapted;
  `.env.example.php` and `example/.env.php` keep the key (still valid, explicit `E_ALL`).
- Tests: 12052 -> 12057 (`ErrorHandlerTest`: deprecation throws, unreported level returns `false`, level 0, registered
  handler with `@`; `EnvironmentSettingsTest`: default and explicit value; the "missing" case of the key was removed
  from the data provider). `error_reporting()` and handlers are restored in every test.
- `ddev composer check` green, baseline empty, `example/` answers 200.
- Open: none.

### Step 2 (v4.43.0) – done

- New in `src/form/upload/`: `UploadFileType` (readonly: `list<string> $mimeTypes`, `list<string> $extensions`, both
  non-empty and validated in the constructor, lower case, extensions without dot; `accepts(detectedMimeType, fileName)`
  is the pure decision), `FileTypeDetector` (interface) and `FinfoFileTypeDetector` (`FILEINFO_MIME_TYPE`; `null` for a
  missing or unreadable file). No separate `UploadTypeChecker`: `UploadFileType::accepts()` is enough.
- `finfo` (libmagic 5.46, DDEV) reports: pdf `application/pdf`, jpeg `image/jpeg`, png `image/png`, gif `image/gif`,
  webp `image/webp`, svg `image/svg+xml`, txt `text/plain`, csv `text/csv` for a regular CSV but `text/plain` for
  `;`-separated or quoted ones, docx / xlsx / pptx their `application/vnd.openxmlformats-officedocument.*` type (when
  `[Content_Types].xml` is the first entry; otherwise libmagic says `application/zip`), zip `application/zip`.
  Decision: an entry has several MIME types. `csv()` = `text/csv` + `text/plain`; `docx()`, `xlsx()`, `pptx()` = own type
  + `application/zip`. The extension has to fit in every case. No `svg()` (XSS), documented in the PHPDoc.
- `FileField`: required `allowedFileTypes:` (after `storage:`), `maxFileSize:` (default 10 MB, > 0),
  `fileTypeDetector:` (default `FinfoFileTypeDetector`); `InvalidArgumentException` for an empty list and a size < 1.
  Order in `acceptUpload()`: upload error, duplicate, empty, too large (before the detector is asked), type, `store()`.
  A file that cannot be examined (`null`) counts as "type not allowed". `FormMessages::fileExceedsMaxSize` (`[maxSize]`,
  binary units: `10 MB`, `1.5 KB`, `512 bytes`) and `fileTypeNotAllowed`, English and German.
- `FileUploadStorage::store()` got `string $detectedType`; `SessionFileUploadStorage` uses it as `UploadedFile::$type` and
  still names the file after the PHP temp name. `FileFieldRenderer` renders `accept=".pdf,.jpg"` after `id`.
- `composer.json`: `ext-fileinfo` in `require` (README requirements list too). `example/` has no upload.
- Tests: 12057 -> 12134 (`UploadFileTypeTest`, `FinfoFileTypeDetectorTest` with real files, `FileFieldUploadCheckTest`,
  doubles `FixedFileTypeDetector` and an adapted `InMemoryFileUploadStorage`, renderer and message tests; existing
  tests got `allowedFileTypes` and the `accept` attribute in the expected markup).
- `ddev composer check` green, baseline empty.
- Open: none.

### Step 3 (v4.44.0) – done

- New in `src/core/`: `ResponseSender` (`send(HttpResponse): never`) and `NativeResponseSender` (final; `send()` =
  status, headers, `ob_end_clean()` for a file, then `writeContent()`, `exit`). `writeContent()` is public and prints
  the string, or the file in 8192-byte chunks with `flush()`, nothing for a 304 or a response without content; it is
  tested through output buffering, `send()` is documented as not unit tested (calls `header()` and `exit`).
- `HttpResponse`: the private constructor only takes status, string and file path; the default headers moved to the
  private `createContentResponse()` (used by the three content factories, behaviour and headers unchanged) and
  `createStatusResponse()` (status only: no ETag, no cache headers; used for 404 / 403). New `getContentFilePath()`,
  `createRedirectResponse()` (status and `Location` only, as sent today), `sendAndExit(ResponseSender)` /
  `redirectAndExit(…, ResponseSender)` return `never`. `createResponseFromFilePath()` returns the 404 / 403 response.
- Senders passed: `ExceptionHandlerContext::$responseSender` (default native; `handleException()` returns `never`),
  `ViewContext::$responseSender` (used by `BaseView`), optional argument `ResponseSender $responseSender = new
  NativeResponseSender()` on `RequestHandler::__construct()`, `ContentHandler::processRequest()`,
  `CsvFile::pushDownloadAndExit()`, `FileHandler::output()`, `MicrosoftAuthenticator::redirectToMicrosoftLogin()`
  (instead of the constructor, to keep the subclasses untouched). `Core` has a private `$responseSender` (native) and
  passes it to the exception context, `RequestHandler` and `ContentHandler`; its HTTPS redirect and 405 are unchanged.
- Doubles: `RecordingResponseSender` (records, throws `ResponseSentException`; `capture(Closure(ResponseSender):
  void)` runs an action and returns the sent response, so tests need no try / catch because PHPStan treats `never`
  calls as terminating), `NonStartingSessionHandler` counts `changeCookieSameSiteToLax()`; the `ViewContextFactory`
  and `ExceptionHandlerContextFactory` use a `RecordingResponseSender` by default.
- Tests: 12134 -> 12155 (`HttpResponseTest`: `sendAndExit()`, redirect response, `redirectAndExit()` with Lax change,
  404 / 403 / 200 for a file; `NativeResponseSenderTest`; `ExceptionHandlerTest`; `BaseViewTest` (success, error,
  invalid JSON body); `CsvFileTest`; `FileHandlerTest`; `RequestHandlerRootRequestTest` redirect of "/").
- `ddev composer check` green, baseline empty, `example/` answers 200 (404 page for an unknown path, HTTP redirects to
  HTTPS).
- Open for step 4: `Core` takes the `ResponseSender` as constructor argument (remove the private native one), the
  HTTPS redirect and the 405 become responses sent through it; `ContentHandler::processRequest()` gets the sender from
  there (its default argument can go); the `new NativeResponseSender()` defaults on the other classes stay.

### Step 4 (v4.45.0) – done

- `Core::fromEnvironment()` (same arguments and defaults as the old constructor) does the global part once per
  process: guard `$isInitialized` (the only static state), autoloader, env file, `error_reporting()`, time zone,
  `DOCUMENT_ROOT`, directories (`is_dir` / `mkdir` only here, in the static `createIfNotExists()`), `app` autoloader
  path, `ErrorHandler`, `HttpRequest::fromGlobals()`. An `UnsupportedRequestMethodException` is answered with
  `HttpResponse::createStatusResponse(HTTP_METHOD_NOT_ALLOWED)->sendAndExit()` (native sender; no `header()` / `exit`
  of its own). `createStatusResponse()` is public now (was private). `fromEnvironment()` is untested (global, once per
  process); documented in the PHPDoc of `Core`.
- `new Core(CoreSettings $settings, HttpRequest $httpRequest, ResponseSender $responseSender = new
  NativeResponseSender())` touches no globals, no files, no static state. New `src/core/CoreSettings.php` (final
  readonly: `EnvironmentSettings`, copyright year, document root, framework / base / app / cache / error docs / log /
  settings / snippets / view directory). Core keeps its public properties with the same names and types; only
  `baseDirectory` and `appDirectory` are `readonly` now (were `private(set)`). The private native sender is gone.
- HTTPS redirect: first thing in `prepareHttpResponse()` after the "already prepared" guard (so a second call throws
  for the redirect too): returns `HttpResponse::createRedirectResponse()` (303, same URL as before). No logger,
  exception handler, session, tags or routes are needed for it, so a request without HTTPS never fails on a missing
  route or an invalid tag name. The `ErrorHandler` is registered before (in `fromEnvironment()`), as before.
- `ContentHandler::processRequest(requestHandler, localeHandler, templateEngine, httpRequest, session, sessionHandler,
  formContext, copyright, robots, responseSender)`: explicit arguments (no extra value object); `responseSender` has no
  default now. It builds the `HtmlDocumentSettings` once (instead of `getHtmlDocument()` reading `Core` lazily) and
  keeps the template engine and form context; `getHtmlDocument()` works as before.
- Performance: `NativeResponseSender::send()` calls `fastcgi_finish_request()` after `writeContent()` if the function
  exists, then `exit` (documented in the class PHPDoc; not unit tested, like `send()`). Template tags: the early check
  (before the exception handler exists) still builds one collection with a placeholder locale handler, because the
  `lang` tag holds the locale handler of the request, which is only known after the route is resolved, and the
  exception handler needs valid tags for its error pages. Per request that is still two builds (check + engine);
  nothing real is saved, a build is 7 small objects. Saving it would need a collection whose `lang` tag gets the
  locale handler later; not done (open point). Directory checks (`is_dir()` / `mkdir()` of the 8 directories) now only run in
  `fromEnvironment()`, still once per request in production, so nothing is saved there either; the benefit is that
  the constructor is free of them.
- README: new section "Production settings" (`opcache.validate_timestamps=0` with reset on deploy, also for the
  compiled templates in `app/cache/`; optional `opcache.preload`; `fastcgi_finish_request()` with PHP-FPM), Quick
  Start with `Core::fromEnvironment(`. `example/public/index.php` and `index.example.php` use it.
- Tests: 12155 -> 12178: `CoreTest` (constructor, `renderCopyrightYear()`, `prepareHttpResponse()`: HTML view, route
  callback, no CSP, session handler double, HTTPS redirect, second call, no routes, invalid tag name before the handler
  exists, unknown route -> `NotFoundException` and 404 through the registered handler, `createTemplateEngine()`),
  `ContentHandlerTest` (`processRequest()`: callback, view, context arguments, no session, copyright / robots, twice,
  missing content file), `HttpResponseTest::testStatusResponseHasOnlyTheStatus`; new double `CoreWorkDirectory`
  (temporary app directory with `CoreSettings`, removed in tearDown). `CoreTest` removes the exception handler in
  tearDown; `prepareHttpResponse()` sets no locale for routes without language, so no `setlocale()` is changed.
- `ddev composer check` green, baseline empty. `example/`: `/` 200, `/nothing-here.html` 404, `http://` 303 to https,
  unsupported methods (`TRACE`, `BREW`) 405.
- Bug fixed in review: `ContentHandler::processRequest()` left its output buffer open when the view threw (the
  exception handler then printed into the buffer of the failed view, which PHP flushed at the end: partial view output
  could precede the error page). The buffer is discarded now (`try` / `catch`, rethrow); the test checks the level.
- Open: template tags are still built twice per request (see above).

### Step 5 (v4.46.0) – done

- `AbstractSessionHandler`: the constructor starts nothing. New public `ensureStarted()` (idempotent; the former
  `start()`: settings, save handler, `session_start()` with strict mode, new / untrusted / expired / regeneration
  handling, last activity; the flag is set right after the native start, so the nested `regenerateId()` of the start
  works), `isStarted()`, `isClosed()`, `writeClose()`. The handler already inherits `SessionHandler::close()` (the PHP
  save handler callback), so the new method is `writeClose()`. `writeClose()` calls `session_write_close()` (throws
  `RuntimeException` if it fails), is idempotent and marks the handler closed also if it was never started (a later
  start then throws `LogicException`, so a close before the first use cannot be undone by accident). `getId()`,
  `regenerateId()`, `getTrustedRemoteAddress()`, `getTrustedUserAgent()`, `getSessionCreated()` call `ensureStarted()`;
  `regenerateId()` throws after the close. `validateId()` / `updateTimestamp()` are PHP callbacks and need no start.
  `ensureStarted()` throws a `LogicException` if output was sent (`protected isOutputSent()` = `headers_sent()`, a hook
  so a test double can produce the state; PHPUnit holds output back) or if the session was closed before its first
  use. `changeCookieSameSiteToLax()` / `…ToNone()` return if the session was not started, throw `LogicException` if it
  is closed.
- `NativeSessionStorage`: `ensureStarted()` before every read and write (replaces `assertStarted()`); `set()`,
  `remove()`, `replaceAll()`, `regenerateId()` throw `LogicException` ("The session is closed: it cannot be changed any
  more.") after the close, reads work from the closed `$_SESSION`; new `close()`. `SessionStorage::close()` is new
  (⚠️ own storages), `ArraySessionStorage` has the same write rules, new `Session::close()`.
- `Core::prepareHttpResponse()`: after `processRequest()` and before the `ContentResponseFactory`, a started session
  is closed (`isStarted()` then `writeClose()`); an unstarted one is left alone. If the view throws, the exception
  handler answers and the session is written at the end of the script (after `fastcgi_finish_request()`), as before.
- Lazy reading (item 2), where the session was read on every request without being needed:
  `HtmlDocument` read the CSRF token in its constructor (every HTML page started the session) and `ExceptionHandler`
  did the same for every error page (also 404). `csrfField` is lazy now: new `HtmlReplacementCollection::addLazyHtml()`
  / `HtmlReplacement::fromLazyHtml()` hand the template a `TrustedHtml` that wraps a function (`TrustedHtml` has a
  hooked virtual property `html` that runs the function once on first read; the class is not `readonly` any more). The
  token is only read if a template uses `csrfField`. `ErrorPageValues::$csrfFieldHtml` is a function (`@internal`).
  The error page and the debug page must not fail because the session cannot be started (the failed start can be the
  reason of the error page): `ExceptionHandler::renderCsrfField()` catches `Throwable` and gives an empty field,
  `ExceptionDebugInfo` shows "The session could not be read: <message>" instead of the export.
- Checked, no read in the constructor / on creation: `Core::prepareHttpResponse()` (handler, `Session`,
  `SessionCsrfTokenSource`, `FormContext`: objects only), `ExceptionHandler::setSession()`, `AuthSession` creation in
  `ContentHandler`, `SessionPreferredLanguage`, `Logger` (does not use the session), `Form` / `CsrfTokenField` (read on
  render / validate). Still start the session on a request, with reason: `RequestHandler::resolveRoute()` for a route
  with a language (it reads the preferred language and writes it when it differs: the "remember my language" feature
  needs the session; a language-less route does not touch it), `findRouteForRootRequest()` (request of `/`, reads the
  preferred language), the debug page (exports the session), and what a view uses (`AuthSession`, forms with CSRF,
  tables, `SearchHelper`, uploads, `MicrosoftAuthenticator`). Idea, not done: remember the language only when the
  session is started for another reason.
- Not unit tested: the real `headers_sent()` (`isOutputSent()` is overridden in a double), the interplay with
  `fastcgi_finish_request()`, parallel requests of one session (the example runs with `individualSessionHandler:
  false`, so there is nothing to measure there; a throw-away script with a real `FileSessionHandler` confirmed: no
  file before the first access, one after it, `session_status()` is `PHP_SESSION_NONE` after `writeClose()`, read works,
  write throws).
- Doubles: `NonStartingSessionHandler` follows the real rules now (records `starts`, `closes`, `regenerations`,
  `sameSiteLaxChanges`; its constructor calls the parent, the former `@phpstan-ignore` is gone; the test sets
  `$_SESSION`), new `OutputSentSessionHandler`, `FailingSessionStorage`, `CountingCsrfTokenSource`. `phpstan.neon`:
  `tests/Unit/CoreTest.php` joined `actraSuperglobalsAllowIn` (sets and unsets `$_SESSION` for the double).
- Tests: 12178 -> 12213. `AbstractSessionHandlerTest` (separate processes) calls `ensureStarted()` where it relied on
  the eager start and has new tests (lazy start, start by storage read / write, close then read / write / cookie
  change / start, output sent); `NativeSessionStorageTest` (no start without access, every access starts once, close,
  writes after close), `ArraySessionStorageTest`, `SessionTest`, `CoreTest` (unstarted session untouched, started one
  closed after the view, write after prepare throws), `HtmlDocumentTest` (token only read if the template uses it),
  `ExceptionHandlerTest` (page without `csrfField` never reads the session, failing session on the error page and the
  debug page), `HtmlReplacementCollectionTest`, `TrustedHtmlTest`.
- `ddev composer check` green, baseline empty, no new `@phpstan-ignore`. `example/`: `/` 200, `/nothing-here.html` 404
  (no `Set-Cookie` in either), `http://` 303.
- Open: a request with a session cookie that does not touch the session and redirects with the Lax change keeps the
  cookie of the browser as it is (Strict): no `Set-Cookie` is sent, as decided ("nothing to change"); it only matters
  if a project relied on the redirect to re-send the cookie as Lax. `NativeResponseSender` could close a started
  session before `fastcgi_finish_request()` for responses that views send themselves (`sendAndExit()` in a view): today
  the lock is held until the end of the script there.

### Step 6 (v4.47.0) – done

- `AbstractSessionHandler::isActive()`: `isStarted()` (the method, so doubles can override it), else the request has a
  cookie with the session name whose value matches the existing ID pattern (`VALID_SESSION_ID_PATTERN`). The name is
  `SessionSettings::$individualName`, else `session_name()` (PHP default or ini); nothing is set or started, so the
  check is safe before the start (tested: status `PHP_SESSION_NONE`, name unchanged). It uses `HttpRequest::getCookie()`,
  not `$_COOKIE`. Only the format of the ID is checked, not whether a session with that ID exists (that needs the save
  handler, i.e. the start): an unknown or expired ID counts as active, and the first access replaces it with a new
  session. A name set by a project handler in `executePreStartActions()` through `ini_set('session.name')` is not
  known before the start (use `individualName`).
- `SessionStorage::isActive()` (⚠️ own storages), `NativeSessionStorage` delegates, `ArraySessionStorage` has
  `active: true` (third constructor argument, after `id`), `Session::isActive()` delegates.
- `RequestHandler`: `rememberPreferredLanguage()` returns without session or with an inactive one; the preferred
  language of `findRouteForRootRequest()` is only read from an active session. Without it the code goes on as before
  without a session (Accept-Language, then the first default route). No other place reads the session on a plain
  language route.
- Doubles: new `CountingSessionStorage` (in-memory, counts accesses, flag `active`, to prove "not used"); the
  `FailingSessionStorage` is active.
- Tests: handler `isActive()` (data provider: no cookie, valid, default name, invalid / empty / too long ID, other
  cookie name, individual name not sent; started without cookie; closed), `ArraySessionStorage`, `Session`,
  `NativeSessionStorage` delegation, `RequestHandler` language route (inactive: storage never used, data unchanged;
  active: written) and root redirect (inactive: preferred language ignored, storage never used).
- Open: a visitor without a session who calls only language routes never gets a preferred language (decided).

### Step 7 (v4.48.0) – done

- `SearchHelper` removed (no alias). New `final` classes in `src/common/`:
  - `SearchQueryBuilder`: static `createSqlFilters()`, `createBooleanQuery()`, `createSqlSearch()` and all private
    helpers; the constants `LIKE_PLACEHOLDER`, `LIKE_ESCAPE_MAP`, `COLUMN_NAME_PART`, `FIELD_NAME_PATTERN` are private
    there (they are not needed by `SearchState`).
  - `SearchState`: `create()`, `checkSearchTerm()`, `checkString()`, `checkFilter()`, `checkMultiFilter()`,
    `checkDateRangeFilter()`, `PARAM_RESET` / `PARAM_FIND`, the session handling. Session section and keys unchanged;
    doc comment of `SessionSectionEnum::SEARCH` updated.
- Decisions: `createSqlSearch()` used no instance state -> static in `SearchQueryBuilder` (was an instance method, so
  it needed a `SearchHelper` instance with request and session for nothing). `checkDate()` used no state either, but it
  is only used by `checkDateRangeFilter()` and is date parsing of the search form, not SQL -> `public static` in
  `SearchState` (a private method would have lost the direct test; a third class is not worth it). No private
  constructor in `SearchQueryBuilder` (the repo's other static helper classes have none).
- Usages updated: `TextFilterField` (`SearchQueryBuilder::createSqlFilters()`), `BooleanSearchOperatorEnum` and
  `SessionSectionEnum` doc comments, README (boolean search, session notes), `phpstan.neon`
  (`actraSuperglobalsAllowIn`: `tests/Unit/common/SearchStateRequestTest.php`). `example/` does not use it.
- `actra/backend` (read only, not changed): `src/libs/form/AbstractSearchForm.php` (type `SearchHelper`,
  `SearchHelper::getInstance(instanceName: $name)`, comment) -> `SearchState::create(…)` with request, value source and
  session; `src/libs/table/UserTable.php`, `TokenTable.php`, `VisitTable.php` (`SearchHelper::createBooleanQuery(`) ->
  `SearchQueryBuilder::createBooleanQuery(`. `backend/UPGRADE.md` (line 152) only mentions it in text.
- Tests: 12230 -> 12230 (50 test methods before and after, no assertion removed). `SearchHelperBooleanQueryTest` ->
  `SearchQueryBuilderBooleanQueryTest`, `SearchHelperFilterTest` -> `SearchQueryBuilderFilterTest` (filters and
  `createSqlSearch()`, now called statically), `SearchHelperRequestTest` -> `SearchStateRequestTest` (plus the
  `checkDate()` tests from the filter test), `SearchHelperSessionTest` -> `SearchStateSessionTest`. Long lines wrapped.
- `UPGRADE.md`: `## [v4.48.0]` with the before/after table.
- `ddev composer check` green, baseline empty, `example/` answers 200.
- Open: none.

### Step 8 (v4.49.0) – done

- New `final readonly` `ResolvedRoute` (`route`, `language`, `fileName`, `fileGroup`, `fileTitle`, `fileExtension`,
  `routeVariables`, `pathVars`: all values that `resolveRoute()` set before). `RequestHandler::resolveRoute()` returns it;
  `route`, `fileTitle`, `fileExtension`, `fileGroup`, `routeVariables`, `pathVars` and `getPathVar()` are removed from
  `RequestHandler`, the four `@phpstan-ignore property.uninitialized` are gone (none left in `src/`).
- Decision: `RequestHandler` keeps `language` and `fileName` (`public private(set)`, both have a value before
  resolving) as the state of the request so far. Reason: with an unknown route or an extension that is not accepted
  `resolveRoute()` throws, and the error page then used the language of the route and the resolved file name that were
  already set (the extension case). A `?ResolvedRoute` kept on the handler would be `null` in exactly that case and
  change the page. `ExceptionHandler` is unchanged. Characterization tests (written first, green before the change):
  extension not accepted keeps language / file name / language root, route without language, forced file group / name,
  path pattern variables, no extension, accepted extension; `ExceptionHandlerTest`: error page after resolving.
- New `@internal` `RoutePathMatch` (route + variables of the path pattern) replaces the side effects of
  `findRouteOfPath()` / `setPathVariable()` (they wrote `fileName`, `fileGroup`, `routeVariables` into the handler).
- `Core::prepareHttpResponse()` and `ContentHandler::processRequest(resolvedRoute:, …)` use the `ResolvedRoute`.
  `getPathVar()` removed: `PathVars` (already used for views) does the same. `PathVars` PHPDoc adapted.
- `example/` and README did not use the removed properties. `UPGRADE.md`: `## [v4.49.0]`.
- Tests: 12230 -> 12238 (8 characterization tests added, no assertion removed; existing ones read the
  `ResolvedRoute`).
- `ddev composer check` green, baseline empty, `example/` checked (200 / 404 / 303).
- Open: none.

### Step 9 (v4.50.0) – done

- `HtmlDataObject` keeps its values in a private array (`array<string, bool|HtmlDataObject|list<HtmlDataObject>|string|null>`).
  `addDataObject()` / `addHtmlDataObjectsArray()` store clones (their values are private and never exposed, so a
  shallow clone is a safe copy at any depth). The public `$data` is removed; `toTemplateData(): stdClass` builds a new
  snapshot (used by `HtmlReplacement`). The constructor stays (subclasses call `parent::__construct()`).
- Characterization test first showed the leak (green on the old code), then flipped. New tests: several parents, grandchild,
  fresh snapshot each time, order and numeric property names. The selector test `std.list.first` now reads
  `std.list.0.name` (a list of data objects), because `$data` can no longer be changed from outside.
  Tests: 12238 -> 12243.
- `CsvFile`: no code change. Rows are private, `addRow()` takes arrays by value, nothing returns them. The class
  PHPDoc now names it a builder (one file per instance, rows only added).
- Other projects (read only): no read or write of `->data` on an `HtmlDataObject` found in `backend` (apart from
  `backend/tests/Unit/libs/common/LanguageSwitcherTest.php` lines 93-95: `$data[1]->data->label|href|isCurrent`),
  nor in the projects using yuf 4. Subclasses: `drogeriehaas.ch` `GlobalNote`. Other `->data` hits are `FormOptions` /
  `TableItem`, which are unchanged.
- `UPGRADE.md`: `## [v4.50.0]`. `ddev composer check` green, baseline empty, `curl https://yuf.ddev.site/` 200.
- Open: none.

### Step 10 (v4.51.0) – done

- **One-phase renderer API:** `FormRenderer::createHtmlTag(): HtmlTag` (abstract, public) replaces `prepare()`,
  `prepareHtmlTag()`, `getHtmlTag()`, `setHtmlTag()` and the stored `$htmlTag` (no state left in `FormRenderer`; the
  three static helpers stay). All 17 renderers and the callers (`FormComponent::getHtmlTag()`, `DefinitionListRenderer`,
  `LegendAndListRenderer`, `BooleanFieldListRenderer`; `DefaultFormRenderer`, `DefaultCollectionRenderer` and
  `ToggleFieldRenderer` only call `$child->getHtmlTag()`) are adapted. `FormSubHeadline` already overrode `getHtmlTag()`
  and needed no change. `NumericFieldRenderer`: `$tag = parent::createHtmlTag();` then its attributes, `return $tag;`
  (the `LogicException` for a missing tag is gone). `InputFieldRenderer` PHPDoc and `FileField` PHPDoc updated.
  `DefinitionListRenderer::addHtmlTagBeforeFormField()` keeps its list (builder state, read on every call).
- **Tests:** HTML byte-identical: no existing test was changed and all pass. New `RenderTwiceTest` (5 tests: a form with
  all kinds of fields incl. toggle children, errors and numeric field rendered twice and `getHtmlTag()` twice; single
  field twice; renderers return a new tag every time; numeric attributes not doubled). The test was written after the
  change (the old code throws `You cannot overwrite an already defined Tag-Element` on the second call, as described in
  the task). `Form::render()` twice is idempotent (the global error is only added once). Tests: 12243 -> 12248.
- **Toggle fields:** trait `HasToggleChildren` (no `Trait` suffix, names the ability) with `addChildField()`,
  `addChildComponent()`, `getChildField()`, `getChildComponent()`, `setDefaultChildFieldRenderer()`, the hook
  `$childrenByMainOption` and the two `validateChildFields…()` overrides. `ToggleChildren` (@internal) stays the
  implementation. Chosen over delegation because the two classes have different parents (`SingleOptionsField` /
  `MultiOptionsField`) and the remaining code per class is only the constructor and `getDefaultRenderer()`. The trait
  creates `ToggleChildren` lazily (`?ToggleChildren` plus private `getToggleChildren()`), because PHPStan reports a
  readonly property initialised from a trait method and a property hook with `??=` as uninitialised. Public API
  unchanged.
- **Other projects (read only, yuf 4: `backend` ^4.10, `drogeriehaas.ch` ^4.7; `yuf-skeleton` `^3.2 || ^4.0` has no hit):**
  no class extends `FormRenderer` or `InputFieldRenderer` and nobody calls `prepare()`, `setHtmlTag()` or `getHtmlTag()`
  on a renderer. Callers of `prepareHtmlTag()`: `backend/src/libs/form/component/SearchQueryField.php:34`,
  `backend/src/libs/form/component/SearchSelectOptionsField.php:24`,
  `drogeriehaas.ch/app/libs/form/component/SearchQueryField.php:34`,
  `drogeriehaas.ch/app/libs/form/component/SearchSelectOptionsField.php:25` (all in an overridden `getHtmlTag()`:
  `$this->getDefaultRenderer()->prepareHtmlTag()` -> `createHtmlTag()`). `drogeriehaas.ch/.../QuillEditorField.php` only
  uses `FormRenderer::addErrorsToParentHtmlTag()` (unchanged). Those files still use the old
  `new HtmlTagAttribute(...)` (v4.41.0 change, see its UPGRADE section).
- `UPGRADE.md`: `## [v4.51.0]`. `ddev composer check` green, baseline empty, `curl https://yuf.ddev.site/` 200 (`example/`
  renders no form, so no HTML comparison).
- Open: none.

### Step 11 (v4.52.0) – done

- **`IbanValidator`:** `COUNTRY_CODES` list replaced by `LENGTH_BY_COUNTRY_CODE` (same 66 countries, no new ones); the
  length must match after removing spaces. Source of the lengths: SWIFT IBAN registry (release 99, December 2024) as
  listed in https://en.wikipedia.org/wiki/International_Bank_Account_Number, "IBAN formats by country" (fetched as raw
  wikitext and parsed). All 66 existing registry examples in `IbanValidatorTest` have exactly these lengths (checked
  by a script and by a test). Tests per country: example valid, rebuilt from its BBAN with computed check digits
  valid, one character too short and one too long (with correct check digits, so only the length is wrong) rejected.
  The "before" behaviour (wrong length with correct checksum accepted) was seen by running those tests before the change.
- **`IpValidator`:** only `::ffff:0:0/96` is normalized (`mappedToIpv4()`; ten zero bytes plus `ffff`), checked on the
  binary form from `inet_pton()`, so `::ffff:a.b.c.d`, the hex form and long forms are the same. Single entries and
  addresses are compared normalized (both directions). Ranges: a mapped range with prefix >= 96 becomes the IPv4 range
  (prefix - 96); with a shorter prefix it stays an IPv6 range. The address is tried in its original form too, so a mapped
  client still matches IPv6 ranges that contain it (`::/0`, `::/80`), as before. `::ffff:0:0/96` as entry now admits all
  IPv4 clients (decision; documented). Not treated as IPv4: `::a.b.c.d` (compatible), `64:ff9b::/96` (NAT64),
  `::ffff:0:a.b.c.d` (translated), `ffff` in other positions (all tested). PHPDoc updated.
- **`CountryCodeEnum`:** `AA` and `UR` removed. Usages: none in `src/`, `tests/`, `example/`, `backend`,
  `drogeriehaas.ch`, `yuf-skeleton` (grep of `::AA`, `::UR`, `'AA'`, `'UR'`).
- **`TableFilter`:** `assertIdentifierIsFree()` (checks `$allFilterFields`, i.e. both lists) before `init()` in
  `addPrimaryField()` / `addSecondaryField()`; the rejected field is not initialised and not added.
- **`NavigationItemCollection::addItem()`:** throws for a second `navKey`. The old test
  `testAddingTheSameKeyReplacesTheItem` (characterization of replacing) is replaced by rejection tests. Usages (read
  only): yuf has no caller besides the tests; `backend` (`ActraBackend`: one `users` item, children `userList`,
  `tokens`, `visits`, `notifications`) and `drogeriehaas.ch` (`BackendNavigationItemCollection`, frontend collection)
  use distinct keys per collection, nothing relies on replacing. `backend` adds its item to a collection the project
  passes in: a project that already has a `users` item there would now throw. Neither project uses `TableFilter`.
- **`DateFilterField`:** the field renders `<input type="text">` (not `type="date"`) with the value in `renderFormat`
  (default `d.m.Y H:i:s`), the session stores `Y-m-d H:i:s`. Decision: accepted input formats are `Y-m-d` and `d.m.Y`
  (each optionally ` H:i` / ` H:i:s`) plus the `renderFormat` (resubmitted values must work). `createFromFormat('!'.$f)`,
  `getLastErrors() === false` (rejects `2026-02-30`) and a round trip `format() === input` (rejects `26-03-01`). In review: also `Y-n-j` and `j.n.Y` (`2026-3-1`,
  `1.3.2026`, as the form `DateField` and as Swiss users type it).
  A date without `:` still gets 00:00:00 / 23:59:59. The session value is parsed with `Y-m-d H:i:s` only and ignored
  if it does not fit (before: `DateMalformedStringException`). Whitespace around the input is trimmed by
  `HttpRequest::getPostString()` (unchanged). Used in `my.cmas.ch` / `artplattform.com` (old framework, not yuf 4).
- `UPGRADE.md`: `## [v4.52.0]`. `ddev composer check` green, baseline empty, `curl https://yuf.ddev.site/` 200.
- Tests: 12248 -> 12593.
- Open: none.

### Step 12 (v4.52.1) – done

- Lines longer than 120 characters (counted as characters, `.php` files without `src/phone/data/` and
  `example/app/cache/`): `src/` 5 -> 0, `tests/` 48 -> 0, `example/` 0 -> 0 (the plan said 3 and 63; the counts
  above are the measured ones). Wrapped only: named arguments one per line, string concatenation, PHPDoc rewrapped,
  expected HTML of the data provider `BooleanFieldV3MarkupTest` split into concatenated parts (byte-identical string).
- Tests: 12596 -> 12596, assertions 23873 -> 23873.
- `UPGRADE.md`: `## [v4.52.1]`. `ddev composer check` green, baseline empty.
- Open: none.

### Step 13 (v4.53.0) – done

- Scope (user): validity per number type, E.164 / national format, allow-list of number types in `PhoneNumberField`.
- **Types:** `PhoneNumberTypeEnum` (string-backed with the metadata group names; `FIXED_LINE_OR_MOBILE` added as result
  value). `PhoneValidator` (still `@internal`) got `isValidNumber()`, `getNumberType()` (null = invalid) and
  `isValidNumberOfType()`, ported from libphonenumber (`getNumberTypeHelper`, region of a shared calling code by
  `leadingDigits` or first matching type, general desc first, type order premium rate, toll free, shared cost, VoIP,
  personal, pager, UAN, voicemail, fixed line / mobile). `isValidNumberOfType()` accepts `FIXED_LINE_OR_MOBILE` as
  `FIXED_LINE` and as `MOBILE`. Decision: `PhoneNumber::isValid()`, `getType()`, `isValidForType()` as public entry points
  (the validator is internal and needs the repository).
- **Metadata:** `PhoneMetaDataLoader` / `PhoneMetaData` load the ten type descriptions, `leadingDigits` and
  `sameMobileAndFixedLinePattern`; `PhoneFormat` has `nationalPrefixFormattingRule`. `PhoneRegionCountryCodeMap::
  getRegionCodesForCountryCode()`. `src/phone/data/` untouched.
- **Matcher:** `PhoneMatcher::matches()` takes the first alternative that matches at the start, so a pattern like
  `A|B` fails if `B` is the whole number (pinned by `testMatchesTakesTheLongestAlternativeOfThePatternAtTheStart`; Java
  backtracks). Not changed (used by the parser and the format choice); new `matchesCompletely()` (`(?:p)\z`) is used for
  the number types. Open: decide whether `matches()` should be fixed (a possible behaviour change in the parser).
- **Formats:** `renderE164Format()`, `renderNationalFormat()`; `renderInternationalFormat()` reuses the shared code. The
  national format replaces the first `$n` of the format by the rule (`0$1`) as libphonenumber does and takes the national
  formats; formats come from the main region of the calling code (as in libphonenumber). Not implemented: carrier codes,
  `nationalPrefixOptionalWhenFormatting`.
- **Field:** `allowedNumberTypes:` (empty = possible number as before, not tightened) and `numberTypeErrorMessage:`
  (default `invalidErrorMessage`). A render option for the format was left out (`renderInternalFormat` is a bool).
- **Tests:** every example number of the metadata (1096, as in the metadata, built without the parser) is valid and of its
  type (fixed line / mobile also as `FIXED_LINE_OR_MOBILE`); known numbers CH, DE, US, GB, FR, IT; formats CH, DE, US,
  GB, FR, IT, RU, JP, BR with extension and national prefix; field with and without allow-list.
- **Parser quirk (not changed):** the example number of GA fixed line (`01441234`) is parsed with the leading `0` stripped
  (7 digits) and so is no valid number; libphonenumber keeps it because the original matches the general description.
- `UPGRADE.md`: `## [v4.53.0]`, README section "Phone numbers". `ddev composer check` green, baseline empty.
- Tests: 12596 -> 16009.
- Open: the `matches()` question above.
- Found in review: the intermittent failure of `composer check` (also seen in step 3) was
  `RandomMimeIdGeneratorTest`: the generator removed `=`, `+`, `/` from Base64, so the length varied and was below
  the tested 40 in about one run of 20. The generator returns `bin2hex(random_bytes(21))` now (42 characters), the
  test checks exactly that.

### Step 13.1 (v4.53.1) – done

- **Matcher:** `PhoneMatcher::matches()` is the full match with backtracking (`(?:p)\z`, flags `uAi` as before).
  `matchesCompletely()` is removed: the class is `@internal`, so a patch release may do that; `PhoneValidator` uses
  `matches()`. Callers checked: parser (`matchNationalNumber()` for general description before/after the national
  prefix and the country calling code of the default region), format selection in `PhoneRenderer`, validator. None
  of the 1096 example numbers changed its result by this alone.
- **National prefix (item 2):** the viability check of libphonenumber (`maybeStripNationalPrefixAndCarrierCode`: keep
  the number if the original matches the general description and the stripped one does not) was already ported in
  `stripNationalPrefix()`; it only worked after the `matches()` fix (the original was not recognised as viable if an
  alternative matched a prefix first). The GA example was not caused by it: Gabon has no national prefix, the parser
  dropped the leading zero because it kept leading zeros for Italy only. libphonenumber keeps them in every country
  (`setItalianLeadingZero(true)` when the national number starts with `0`); `detectLeadingZeros()` does that now
  (without leading zero: `false`, for Italy `null` as before). `PhoneConstants::ITALIAN_LEADING_ZERO_COUNTRY_CODES`
  is still used for that `null`.
- **Renderer (found by the new tests):** the national prefix rule (`0$1`) is applied with `preg_replace()`: its `$1`
  stands for the replaced first group of the format, as with Java's `replaceFirst()` (AR mobile `$2 15-$3-$4` became
  `09 15-...`, is `011 15-...` now).
- **Tests:** `PhoneParsedExampleNumbersTest` (every example number through `createFromString()` with its region: valid,
  type, national / international / E.164 rendering parsed back to the same number). Characterised before the fix:
  failing were GA fixedLine (not valid) and AR mobile (national rendering), and NO uan, SJ uan, CI mobile, NE tollFree,
  NE premiumRate, CG mobile, SZ tollFree, SM fixedLine, BZ tollFree, TO tollFree, FJ tollFree (not possible).
  `PhoneMatcherTest`, `PhoneParserTest` (GA, strip viability with hand-built metadata), `PhoneNumberTest`,
  `PhoneRendererFormatTest` (AR).
- **Changed expectations:** `testMatchesTakesTheLongestAlternativeOfThePatternAtTheStart` replaced by
  `testMatchesTriesTheAlternativesUntilTheWholeSubjectMatches`; `matchesCompletely` tests merged into it;
  `PhoneRegionExampleNumbersTest`: the `NOT_POSSIBLE` list and its throw test removed, `WITH_TRUNK_PREFIX` only MX
  mobile; `PhoneRendererTest` GA fixed line `+241.1441234` / `+241 1441234` -> `+241.01441234` / `+241 01 44 12 34`,
  GA mobile `+241.6031234` / `+241 6 03 12 34` -> `+241.06031234` / `+241 06 03 12 34`; `PhoneNumberTest` invalid
  numbers `044 668 18 00 / 12` and `0 0 0 0 0 0 0 0 0` (CH) are possible now (not valid).
- Open: `PhoneMatcher::groupCount()` counts the groups up to the last matched one (PHP drops trailing unmatched
  groups); `stripNationalPrefix()` uses `groupCount() - 1` for that reason. Fine for the metadata (one group), not
  for patterns with several groups where the last one is unmatched.
- `UPGRADE.md`: `## [v4.53.1]`. `ddev composer check` green, baseline empty. Tests: 16009 -> 20455.
- Review: the two viability tests called the private `stripNationalPrefix()` through reflection (forbidden). It is
  `public static` now (`PhoneParser` is `@internal`, the method has no state; `matchNationalNumber()` static too) and
  the tests call it directly; no reflection in `tests/Unit/phone/`.

### Step 13.2 (v4.54.0) – done

- **Field:** `PhoneNumberField::parsePhoneNumber()` returns the number only if `PhoneNumber::isValid()`. So the default
  accepts valid numbers only; an invalid (also a possible) number stays as typed (not normalized to the internal format,
  so the user sees what they typed) and adds `invalidErrorMessage`. With `allowedNumberTypes:` unchanged (valid and of one
  of the types, `numberTypeErrorMessage`). Constructor and `setValue()` keep a not valid number as typed too.
- **Changed expectation:** `PhoneNumberFieldNumberTypeTest` provider case `012 345 67 89` (CH, possible, no assigned
  number): accepted (`true`) -> rejected (`false`); test renamed `testWithoutAllowListOnlyValidNumbersAreAccepted`. No
  other test number was affected (all others are valid). New cases: `044 668 18 00 / 12`, stays as typed, other country.
- **Other places:** no other validation of phone numbers in `src/` (`PhoneNumber::createFromString()` is only used by
  the field and in `src/phone/`); no datacheck validator for phone numbers; `example/` has no phone field.
- `UPGRADE.md`: `## [v4.54.0]`, README "Phone numbers". `ddev composer check` green, baseline empty.
- Tests: 20455 -> 20458.

### Step 14 (v4.55.0) – done

- **Scope (user):** `AUTH PLAIN` and `XOAUTH2` besides `AUTH LOGIN` in `SmtpMailer`; no `CRAM-MD5`.
- **New:** `SmtpAuthMethodEnum` (`LOGIN`, `PLAIN`, `XOAUTH2`, value = name in the `AUTH` line), interface
  `OAuthTokenProvider` (`getAccessToken(): string`, in `actra\yuf\mailer`; step 15 implements it), `SmtpCapabilities`
  (internal, readonly: parses the answer to `EHLO`, multi-line, several `AUTH` lines, legacy `AUTH=`, the first line
  is the greeting and never a capability). `SmtpMailer` got the optional arguments `authMethod:` and
  `oAuthTokenProvider:` at the end of the constructor (existing calls unchanged; `XOAUTH2` without provider and a
  provider with `LOGIN` / `PLAIN` throw `InvalidArgumentException`).
- **Selection:** fixed method not announced (also when nothing is announced) -> `MailerException` with the announced
  methods. Automatic: with a provider `XOAUTH2`, otherwise `PLAIN` before `LOGIN`; a server without any `AUTH` line gets
  `LOGIN` (`XOAUTH2` with a provider) as before; announced methods that do not contain a candidate -> `MailerException`.
  The capabilities of the answer to the last `EHLO` count (after STARTTLS). ⚠️ A server that announces `PLAIN` gets
  `AUTH PLAIN` now instead of `AUTH LOGIN` (marked in `UPGRADE.md`, `authMethod: LOGIN` keeps the old dialogue).
- **Dialogue:** `PLAIN` sends `AUTH PLAIN base64("\0user\0password")` as initial response (RFC 4954); NUL in user name or
  password throws. `XOAUTH2` sends `AUTH XOAUTH2 base64("user=…\x01auth=Bearer …\x01\x01")`; `235` is success, `334`
  (JSON error) is answered with an empty line and the final code is reported, any other code too; the token is requested
  after STARTTLS and the choice of the method, only with a user name; an empty token throws.
- **Security:** the existing rule is unchanged: with `useTls: true` (default) the credentials go after STARTTLS (a failed
  handshake or missing STARTTLS aborts); with `useTls: false` they go in clear text (documented, for a server on the same
  host). The log gets `AUTH PLAIN (hidden)` / `AUTH XOAUTH2 (hidden)` / `(hidden)` (`sendCommand(logAs:)` replaces the
  `isCredential` flag); exception messages carry codes only, never credentials, tokens or the server's challenge.
- **Tests:** 20458 -> 20491 (`SmtpCapabilitiesTest`, `SmtpAuthenticationTest`, double `FixedOAuthTokenProvider`; `AUTH LOGIN`
  stays covered by the unchanged `SmtpMailerTest`, which passed before and after). Exact base64 payloads are literals made
  with `base64` on the command line.
- `UPGRADE.md`: `## [v4.55.0]`, README "Sending mail with SMTP". `ddev composer check` green, baseline empty. `example/`
  does not use the mailer.

### Step 15 (v4.56.0) – done

- **New:** `MicrosoftClientCredentialsTokenProvider` (client credentials flow through `CurlClient` and `CurlPostRequest`,
  form body, token cached in the instance until `expires_in` minus 60 s; `expires_in` as int or digit string, `token_type`
  must be Bearer; tenant ID checked against `[A-Za-z0-9.-]`), `GraphMailer` (extends `AbstractMailer`), internal
  `MailerHttpErrorReader` (reads `error` / `error_description` of OAuth and `error.code` / `error.message` of Graph; only
  strings, whitespace and control characters replaced, cut at 300 characters).
- **Message:** `headerHasTo()`, `headerHasSubject()` and `headerHasBcc()` are all `true`: Graph takes the recipients of a
  MIME message from the `To`, `Cc` and `Bcc` headers (there is no envelope; Exchange removes `Bcc` from the delivered
  message). The envelope sender (`senderEmail` of the mail) is not used, `From` must be the mailbox or a send-as address.
  The message is `header CRLF CRLF body`, Base64 encoded; `SmtpDataFormatter` is not used (dot stuffing would corrupt it).
  Content-Type of the request is `text/plain; charset=utf-8` (what `CurlPostRequest::createWithPlainTextBody()` sends).
- **Size:** the Base64 text is checked against 4 194 304 bytes before the token is asked for; the exception names the limit
  and says that larger attachments are not supported. The attachments are Base64 in the MIME message and the message again
  in the request, so about 2 MB of attachments is the maximum (documented). The tests build the real sizes (2.4 MB fails,
  2.1 MB passes), no configurable limit.
- **Errors:** 202 is success, everything else (also 200, redirects) a `MailerException` with the HTTP status and the
  details; transfer failures with the cURL message. The access token goes through `useTokenAuthentication()`: plain HTTP
  to a host other than localhost or an invalid token throws a `MailerException` with a fixed text (no token). No retry
  after 401 (a provider that caches a revoked token delivers it until it expires).
- **Tests:** 20491 -> 20537. New doubles: `ScriptedHttpServer` (+ router `scripted-server.php`: status and body per path
  from a file, requests recorded in a file, temporary directory removed in the destructor), `AdjustableClock`;
  `LocalHttpServer` got the optional arguments `script:` and `environment:`, `EchoedRequest::fromJson()`;
  `scripted-server.php` is allowed to use `$_SERVER` in `phpstan.neon` like `echo-server.php`.
- `UPGRADE.md`: `## [v4.56.0]`, README "Sending mail with Microsoft 365 (Graph API)" (also the `XOAUTH2` example with the
  Outlook scope). `ddev composer check` green, baseline empty. `example/` does not use the mailer.

### Step 16 (v4.57.0) – done

- **Change:** `CurlErrorEvaluator::isBadStatusCode()` accepts 301, 302, 303, 307 and 308 with
  `acceptRedirectionResponseCode()`; 300, 304, 305, 306 and everything from 400 stay errors. `HttpStatusCodeEnum` got
  `HTTP_PERMANENT_REDIRECT = 308` (302 and 307 existed). `describeStatusCode()` has texts for 302, 307 and 308.
  `AbstractCurlRequest::acceptRedirectionResponseCode()` PHPDoc updated; `isRedirectionResponseCodeAccepted()` needed
  no change.
- **Tests:** 20537 -> 20554, data sets only (evaluator providers over 300-308 with and without acceptance, status
  texts, `CurlRequestCharacterizationTest` against the local server: 301/302/303/307/308 accepted, 300/304 errors). The
  former row "404 still an error" of `acceptedRedirectProvider` was removed: PHP turns any status with a `Location`
  header of the router into 302, so it never tested 404 (the evaluator test covers 404 accepted).
- `UPGRADE.md`: `## [v4.57.0]`. README does not document it. `example/` does not use the Curl client.

### Step 17 – done

- `docs/plans/done/standard-migration/remaining.md` rewritten to the final state after v4.57.0: follow-up list for
  `actra/backend` (from the read-only searches of the steps), the standard figures (baseline 0, 3 documented
  `@phpstan-ignore`, 51 non-final classes = abstract bases / extension points, `Core::$isInitialized` as the only static
  state), what is not covered by tests (incl. the checks of `GraphMailer` against a real tenant) and the open points.
  **The plan is complete.**

## Final note (2026-10-09)

Done: all steps released (v4.42.0–v4.57.0); final state in
[docs/plans/done/standard-migration/remaining.md](../standard-migration/remaining.md). The plan moved to `docs/plans/done/` (coding standard v1.16.0).
