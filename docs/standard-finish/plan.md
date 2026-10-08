# Plan: finish yuf

What is still open after [docs/standard-completion/plan.md](../standard-completion/plan.md) (v4.41.0, empty PHPStan
baseline), taken from [docs/standard-migration/remaining.md](../standard-migration/remaining.md). One step per release,
each small enough to release on its own. `actra/backend` follows when the plan is done (its
`docs/standard-migration/plan.md`).

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
- **New features:** all three are planned (steps 11 to 13); their scope is asked at the start of each step.
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
   and README updated.

### Design points

5. **v4.46.0 – split `SearchHelper` (⚠️):** pure static SQL builders (`createSqlFilters()`, `createBooleanQuery()`,
   `createSqlSearch()`) into their own class (e.g. `SearchQueryBuilder`), the stored search state (`create()`,
   `check…()`) stays or becomes `SearchState`; names decided in the step per `naming.md`. `UPGRADE.md` for
   `actra/backend` (`createBooleanQuery()` and the removed `getInstance()` -> `create()`).
6. **v4.47.0 – resolved route of `RequestHandler` (⚠️):** `resolveRoute()` returns a readonly value object (route,
   language, file title / extension / name / group, route variables, path vars) used by `Core`, `ContentHandler`,
   `ExceptionHandler`; the four `@phpstan-ignore property.uninitialized` go away.
7. **v4.48.0 – `HtmlDataObject` and `CsvFile`:** `HtmlDataObject` stores an array instead of a shared `stdClass`
   (a child added with `addDataObject()` is copied, later changes of the child do not leak); the template engine
   reads the same selectors. `CsvFile`: decided in the step (builder with `addRow()` is fine; immutable result or
   documented builder). Extension point `HtmlDataObject` stays (`actra/backend` uses it).
8. **v4.49.0 – `FormRenderer` and toggle fields (⚠️ for own renderers):** one-phase renderer API (`render(): HtmlTag`
   without stored tag, so a component can be rendered more than once; `prepare()` / `getHtmlTag()` / `setHtmlTag()`
   removed or adapted); `ToggleField` / `MultiToggleField` share the child methods (trait or delegation via
   `ToggleChildren`). HTML output byte-identical (form and `example/` tests). `UPGRADE.md` with before/after for own
   renderers.

### Small functional gaps

9. **v4.50.0 – validation gaps (⚠️ behaviour):** IBAN length per country (table in `IbanValidator`); IPv4-mapped IPv6
   addresses (`::ffff:a.b.c.d`) match IPv4 whitelist entries (normalized before the comparison); `CountryCodeEnum`:
   remove the non-ISO `AA` and `UR` (`UY` exists); `TableFilter` throws for a second field with the same identifier,
   `NavigationItemCollection::addItem()` for a second item with the same `navKey`; `DateFilterField` accepts the
   date format only (no relative dates like "tomorrow").

### Style

10. **v4.50.1 – lines ≤ 120 characters:** the 3 lines in `src/` (`HtmlTag`, `CurlFormEncoder`, `Form`) and the 63 in
    `tests/`. No behaviour change.

### New features (scope asked at the start of each step)

11. **v4.51.0 – phone numbers:** validity per number type, E.164 and national format in `src/phone/`.
12. **v4.52.0 – SMTP authentication:** more methods than `AUTH LOGIN` in `SmtpMailer` (e.g. `AUTH PLAIN`).
13. **v4.53.0 – redirect codes:** `acceptRedirectionResponseCode()` also for 302, 307, 308.

### End

14. Update [docs/standard-migration/remaining.md](../standard-migration/remaining.md) to the final state, so that
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
