# Plan: finish the coding standard in yuf

Everything that is still open after the template engine rewrite (v4.27.0), see
[docs/plans/done/standard-migration/remaining.md](../standard-migration/remaining.md). `actra/backend` follows when yuf is done.

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
  `UPGRADE.md` (as in docs/plans/done/standard-migration/plan.md).

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
2. **`HttpRequest` as instance** (design first in `docs/plans/done/http-request/design.md`, approved by the user): one object per
   request created by `Core` from the superglobals (`HttpRequest::fromGlobals()`), passed to `RequestHandler`, views
   (`ViewContext`), forms (`FormInput::fromGlobals()`), tables (`DbResultTable`, `TableFilter`), `SearchHelper`,
   `CspPolicySettings`, `HttpResponse`, `Logger` and `ExceptionHandler`; the static caches (`$inputData`, `$host`,
   `$protocol`, `$languages`) and `RequestBody::getData()` go away; fixed sets (`PROTOCOL_*`, request methods) as enums.
   Makes the request pipeline of `Core` testable. Several releases (decided in the design).
3. **Session object** (design first in `docs/plans/done/session/design.md`): one session object per request instead of the static
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
5. `common` (68), done in v4.32.0.
6. `phone` (78, full standard; characterization tests first), done in v4.33.0.
7. `db` (51), done in v4.34.0.
8. `table` (37) and `pagination` (3), done in v4.35.0.
9. `mailer` (33, full standard; characterization tests of the MIME output first), done in v4.36.0.
10. `auth` (26), `security` (0) and the rest of `session` (2), done in v4.37.0.
11. `api` (27), done in v4.38.0.
12. `html` (25) and `layout` (0, `final` only), done in v4.39.0.
13. `exception` (10) and `datacheck` (13), done in v4.40.0.
14. `form` (0 baseline; `final` / extension points of its classes, `HtmlTagAttribute` named constructors), done in
    v4.41.0. **The plan is complete.**

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
  what is not covered in [docs/plans/done/http-request/plan.md](../http-request/plan.md). Baseline: 525 -> 469 entries.

### Step 3 (v4.30.0) – done

- Session object: `Session` per request (`Core::$session`), `AuthSession`, `SessionCsrfTokenSource`, `FormContext`; the
  static session classes, `FormNameRegistry`, the identifier registries and the guards of `AuthUser` / `Authenticator`
  are gone; all data of yuf is below `$_SESSION['yuf']`. Details, layout and what is not covered in
  [docs/plans/done/session/plan.md](../session/plan.md). Baseline: 469 -> 447 entries.


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

### Step 5 (v4.32.0) – done

- Area `common`. Baseline 375 -> 307 (all 68 entries of `src/common/` removed, no new entry). Tests 3262 -> 3528.
  Details and before/after in `UPGRADE.md`.
- **Characterization first:** new tests for `StringUtils`, `JsonUtils`, `CsvFile`, `FileHandler`, `SimpleXmlExtended`,
  `ValidatedEmailAddress`, `UrlHelper` (edge cases), `BooleanSearchOperatorEnum`, `CountryCodeEnum`, `LogFile` (file
  name), `SearchHelper::checkDate()`. The expected values of the unchanged behaviour were checked against the old code;
  the buggy behaviour was found while doing so (see below) and fixed with a test.
- **final / extension points / static:** all classes `final` (`CsvFile`, `FileHandler` (already `readonly`),
  `JsonUtils`, `SearchHelper`, `SimpleXmlExtended`, `StringUtils`, `UrlHelper`, `ValidatedEmailAddress`); nothing in
  `actra/backend` extends them. The only extension point is the new interface `MailDomainResolver`. Static stays only for
  pure, stateless helpers: `StringUtils`, `JsonUtils`, `UrlHelper::generateAbsoluteUri()`, `FileHandler` (`getExtension()`
  pure; `removeFile()` and `renderFileSize()` read the file system but have no state), `CsvFile::stringToArray()`,
  `SimpleXmlExtended::convertXmlToArray()`, `SearchHelper::createSqlFilters()` / `createBooleanQuery()` (backend calls
  `createBooleanQuery()` statically). No static property in `common`.
- **Enums:** `EmailAddressErrorEnum` (the nine codes of `ValidatedEmailAddress`, values unchanged). `SearchHelper::PARAM_*`
  stay constants (names of two request parameters, no set of values). `BooleanSearchOperatorEnum` is `@internal`.
- **`mixed`:** only `JsonUtils::convertToJsonString(mixed)` (any encodable value) and the values of JSON/XML arrays
  (`array<array-key, mixed>`, narrowed right after decoding: `decodeJsonString()` throws for scalars).
- **Security findings:**
  - `SearchHelper`: every user value is a bound parameter (`LIKE ? ESCAPE '!'`, `=?`, `%`/`_`/`!` escaped). Identifiers of
    `createBooleanQuery()` and `createSqlSearch()` are validated against a whitelist pattern (now with `D`, so a trailing
    line break is invalid). The column of `createSqlFilters()` is an SQL expression taken over unchanged (checked only
    for empty and `?`); the contract "never user input" stays documented, the only caller (`TextFilterField`) gets it from
    the table definition. The deprecated `getBooleanQuery()` interpolated words and field names into SQL: removed.
  - `CsvFile`: CSV injection decision: strings starting with `=`, `+`, `-`, `@`, tab or CR get a leading `'`, numbers and
    numeric strings stay (negative numbers from the database are strings); switch `protectAgainstFormulas:`. Temporary
    file 0600 via `tempnam()`, removed after the download.
  - `FileHandler::removeFile()` allowed path traversal through token and file name: now whitelisted. `output()` trusts its
    path (documented, no allowed directory known).
  - `UrlHelper`: open redirect is not decided here (it builds a URI, it does not know the allowed hosts); documented, the
    scheme-relative `//host` is no longer passed on as is. Callers that redirect to user input must whitelist (nothing in
    yuf does).
  - `ValidatedEmailAddress`/`SystemMailDomainResolver`: the port 25 check connected to any A record of an entered domain
    (SSRF into the internal network): private and reserved addresses are skipped. `anna@` crashed with a `ValueError`.
  - `StringUtils::randomString()` shuffled with `str_shuffle()` (backend uses it for tokens): `random_int()` shuffle.
  - `SimpleXmlExtended::convertXmlToArray()`: no entity substitution (as before), `LIBXML_NONET` added.
- **Exceptions:** `new Exception()` of `JsonUtils` -> `RuntimeException`; `UnexpectedValueException`,
  `InvalidArgumentException` and `RuntimeException` elsewhere. `TimeOfDay` keeps `ValueError` for out-of-range parts (the
  right type for a valid type with an invalid value, tested since v4.25; a change would only be churn).
- **Found and fixed while characterizing** (details in `UPGRADE.md`): `FileHandler::getExtension('README')` gave `EADME`;
  `StringUtils::between/insertBeforeLast/breakUp/formatBytes/randomString`; `JsonUtils::minify()` kept comment text at the
  end and the whitespace after the last token; `SimpleXmlExtended` first empty child and `includeNull` in nested arrays;
  `CsvFile::stringToArray()` kept `\r` in the header row.
- **Stays untested:** `SystemMailDomainResolver` (DNS and network; its `ValidatedEmailAddress` logic is tested with
  `FixedMailDomainResolver`), `CsvFile::pushDownloadAndExit()` and `FileHandler::output()` (`exit`), the
  transliteration of non-ASCII characters in `StringUtils::urlify()` (depends on the locale of the process), the
  randomness of `randomString()` / `generateSalt()` (only length and character groups).
- **Open / for later:**
  - The Git index has the names `src/common/CSVFile.php` and `src/common/SimpleXMLExtended.php`, the files on disk are
    `CsvFile.php` and `SimpleXmlExtended.php` (renamed in an earlier release on a case-insensitive file system;
    `core.ignorecase` hides it). On Linux, the classes `CsvFile` and `SimpleXmlExtended` are not found from a clean
    checkout: `git mv src/common/CSVFile.php src/common/CsvFile.php` and the same for `SimpleXmlExtended.php` (two
    steps through a temporary name on macOS).
  - `SearchHelper` has two purposes (SQL builders of search texts, pure and static; search state in the session). A split
    (e.g. `SearchSqlBuilder`) would be a breaking change for `SearchHelper::createBooleanQuery()` in `actra/backend`:
    decide when backend follows. `actra/backend` still calls `SearchHelper::getInstance()` (gone since v4.29.0).
  - `CountryCodeEnum` is used nowhere and has no behaviour; `AA` and `UR` are no ISO 3166 codes (`UR` is probably a typo
    for `UY`, which exists). Removing cases is breaking: left as is.
  - `CsvFile` is mutable (`addRow()`); a builder/immutable variant would be a redesign.

### Step 6 (v4.33.0) – done

- Area `phone` (port of libphonenumber; licence notice kept in `src/phone/LICENCE` and in the class docblocks;
  `src/phone/data/` untouched and still excluded). Baseline 307 -> 229 (all 78 entries of `src/phone/` removed, no new
  entry). Details and before/after in `UPGRADE.md`.
- **Characterization first:** `tests/Unit/phone/` (5759 tests) passed against the old code before the change: parsing
  (national, `+`, `00`, IDD of the default region, brackets, slashes, extensions, RFC 3966, vanity numbers, full-width
  and Arabic-Indic digits, all error codes), rendering (internal / international, extension prefixes, formats of
  about 20 regions), the region / country calling code map, length tests per region, `PhoneMatcher`, the metadata, and
  **every example number of the metadata of every region** (1096 numbers of 245 regions, read from `src/phone/data/`
  by `PhoneExampleNumbers`; round trips of national, international and E.164-like input). The API has no number types
  (mobile / fixed line) and no national or E.164 format: it only offers "possible" (length), the internal format
  (`+41.446681800`) and the international format (`+41 44 668 18 00`); nothing else was added.
- **API decision:** consumer API is `PhoneNumber::createFromString()` / `getNationalSignificantNumber()`,
  `PhoneRenderer::renderInternalFormat()` / `renderInternationalFormat()` and `PhoneParseException` +
  `PhoneParseErrorEnum` (all `final`, signatures unchanged except the exception). Everything else is `final` and
  `@internal`. `actra/backend` (`DbAuthUser`) and `PhoneNumberField` only use the consumer API: no follow-up needed.
- **Static state:** `PhoneMetaData::$regionToMetaDataMap`, `$countryCodeToNonGeographicalMetadataMap` and
  `PhoneParser::$instance` are gone. `PhoneMetaDataRepository` (instance) loads and remembers the metadata of one
  lifetime; `PhoneMetaDataLoader` narrows the `require`d arrays once into `PhoneMetaData` / `PhoneDesc` / `PhoneFormat`
  (readonly, `UnexpectedValueException` with the key). The static entry points create one repository per call and load
  the files they need again (about 30 µs per parse + render, plain arrays, OPcache): nothing is cached between calls, so
  the entry points stay stateless. If this is ever too slow, pass a repository from a shared service, not a static.
- **Enums:** `PhoneParseErrorEnum` (values of the old constants and `-1` for "not possible"), `PhoneLengthResultEnum` (the
  `PhoneValidator` int constants), `PhoneCountryCodeSourceEnum` (the `FROM_*` constants of `PhoneConstants`).
  `PhoneConstants`, `PhonePatterns` stay constants (regular expression parts, character tables, no fixed sets).
- **Data files:** `src/phone/data/PhoneNumberMetadata_IT.php` and `_VA.php` refer to `PhoneCountryCodes::IT`, so that class
  stays (`final`, `@internal`). The values of every constant of `PhoneConstants` and `PhonePatterns` were compared with
  the old ones (only `UNWANTED_END_CHAR_PATTERN` changed, see below).
- **Structure:** the 170 lines of `PhoneParser::parse()` are split into small methods; by-reference parameters are
  return values (array shapes of private methods); the carrier code (never read) is dropped; `PhoneNumberNormalizer` is
  new. `PhoneRegionCountryCodeMap` is typed (`non-empty-list<string>`).
- **Bugs found and fixed (with tests, see `UPGRADE.md`):** the internal format dropped the Italian leading zero
  (`+39.212345678`; `PhoneNumberField` stores that format, so already stored Italian landlines are not repaired); the
  pattern for unwanted trailing characters used Java syntax (`&&`) that PCRE does not know, so `044 668 18 00;` / `!` /
  `,` / a closing quote were `NOT_A_NUMBER`; the matcher offsets are character offsets but were used with `substr()`;
  `isPossibleNumber()` crashed with a `TypeError` for an unknown country calling code.
- **Quirks of the metadata kept (pinned in `PhoneRegionExampleNumbersTest`):** 11 example numbers of the metadata are not
  possible for their own region (`NO`/`SJ` `uan`, `CI` mobile, `NE` toll free and premium rate, `CG` mobile, `SZ`, `BZ`,
  `TO`, `FJ` toll free, `SM` fixed line) and 3 (`MX` mobile, `GA` fixed line and mobile) start with the trunk prefix.
  An extension is cut after seven digits; a text with three or more letters is read as vanity number (`ext` at the
  end becomes digits). These are libphonenumber behaviours, not changed.
- **Stays untested:** the exact content of the 245 metadata files (only through their example numbers),
  `PhoneMetaDataRepository` with a broken file (the loader is tested with arrays).
- **Open / for later:** there is no validity check per number type (only length, as before); a real
  `isValidNumber()` / E.164 / national format would be new features. The country list of `PhoneRegionCountryCodeMap`
  is rebuilt on each `isValidRegionCode()` call (cheap, 245 entries).

### Step 7 (v4.34.0) – done

- Area `db`. Baseline 229 -> 176 (all 51 entries of `src/db/` and 2 entries of `table` for the now typed `params`
  removed, no new entry). Tests now 10563 (`tests/Unit/db/`: 224). Details and before/after in `UPGRADE.md`.
- **Characterization first:** `DbQueryTest` (57 tests: tokenizer, sections, joins, sub queries, parameter distribution,
  added parts, order escaping, every error message) passed against the old code before the change; `DbRuntimeException`
  (code and message) as well. `DbSettings`, `FrameworkDb`, the query log and `DbSelectStmt` logging were written after the
  change, because the old classes could not be built without a MySQL server (static pool, MySQL DSN).
- **Database in tests:** `tests/Double/db/SqliteDatabase` creates a real `FrameworkDb` on `sqlite::memory:` (the new
  `DbConnectionParameters` has a public constructor with a DSN; `FrameworkDb` forces its attributes over the options).
  `FrameworkDbTest` (select / selectRows / selectRow / execute, bound values, transactions incl. the destructor check,
  query log, `getLastInsertId()`, password not in the exception, errors without bound values) and `DbQueryDatabaseTest`
  (`selectFromDb()` and `getTotalAmount()` on generated SQL) run the production code. `SteppingClock` gives known query
  durations. The MySQL specific part is pure: `DbConnectionParameters::forMysql()` (DSN, init command `lc_time_names` /
  `sql_safe_updates`) and the validation of `DbSettings` (charset, host, database, locale) are unit tested.
- **Static state removed (decision):** `FrameworkDb::$instances` / `getInstance()`, `DbSettings::$instances` and
  `DbQueryLogList::$stack` are gone; no `DbConnectionPool` class was added, because nothing in yuf asks for a connection
  by name and a pool without consumer would be new, unused API. One `FrameworkDb` is one connection (public
  constructor, `DbConnectionParameters`). `actra/backend` keeps its `DB::$instance` singleton (its own static, task 6 of
  its plan replaces it by passing `DB` in); a project that needs several named connections holds an `array<string,
  FrameworkDb>` itself. The query log is one `DbQueryLogList` per connection (injected, with a `Clock`).
  `DbSettings::$identifier` was only used by the pool and is removed.
- **final / extension points:** everything `final` except `FrameworkDb`, a documented extension point (backend's `DB`
  extends it; it is a PDO subclass, composition would change every call in backend). `DbQuery`, `DbSelectStmt`,
  `DbSettings`, `DbQueryData`, `DbQueryLogItem`, `DbQueryLogList`, `DbRuntimeException` are `final`; `DbStatementExecutor`
  (the one place that executes, logs and wraps errors) and the two enums are `@internal`. Tests of `table` that stubbed
  `DbQuery` use a real one.
- **Enums:** `DbSortDirectionEnum` (replaces `DbQuery::SORT_ASC/DESC`, internal) and `DbQuerySectionEnum` (SELECT, FROM,
  WHERE; internal). Constants for tokens (`JOIN_KEYWORDS` ...) are lists, no sets of values.
- **Security findings:** all values are bound; identifiers: the order column is checked by whitelist (unchanged), the SQL of
  `createFromSqlQuery()` and of added parts is trusted and documented as such (the README says so for the order
  expression already). New: `DbSettings` validates host / database (`;` ends a DSN setting) and charset / locale (go into
  the DSN and the init command, locale is quoted now); bound values left the application in the message of
  `DbRuntimeException` (log, error page): removed; `#[SensitiveParameter]` on passwords; `sqlSafeUpdates` default kept.
  Not changed: the driver message of a failed query can contain values (duplicate entry), documented.
- **Bugs found:** `lastInsertId(): int` broke the contract of `PDO::lastInsertId()` (replaced by `getLastInsertId()`; `lastInsertId()` throws a `LogicException` naming it, decision of the user);
  an unfinished `DbQueryLogItem` returned a negative time; `prepare()` returned `false` through an exception of its own
  that was caught by itself; `createInQuery([])` produced invalid SQL; bool values bound through `execute(array)` become
  `''` (documented, types exclude `bool`).
- **Stays untested:** the real MySQL connection (`new FrameworkDb(forMysql(...))`, init command executed by the server,
  `lc_time_names`, `sql_safe_updates` behaviour), MySQL specific SQL of callers, the `DbRow` date parsing against real
  MySQL values (pure tests exist).
- **Open / for later:** `actra/backend` still uses `DbSettingsModel`, `FrameworkDB` and `new DB(dbSettingsModel: ...)`; when
  it follows: `DbSettings` without `identifier`, `new DB(connectionParameters: DbConnectionParameters::forMysql(...))`,
  `lastInsertId()` -> `getLastInsertId()`, `ExecuteAndFetch` -> `executeAndFetch`. `AmountParser` (`form`) is used by `DbRow`.

### Step 8 (v4.35.0) – done

- Area `table` and `pagination`. Baseline 176 -> 136 (all 37 entries of `src/table/` and 3 of `src/pagination/` removed, no new
  entry). Tests 10563 -> 10653. Details and before/after in `UPGRADE.md`.
- **Characterization first** (passed against the old code before the change): `tests/Unit/pagination/PaginationTest`
  (page lists for many positions, links, titles, exact markup in `tests/Fixture/table/`), `SmartTableTest` (head, rows,
  odd/even, empty, one/many results, thousands separator, replaced templates, duplicate column), `DbResultTableRenderTest`
  (on the SQLite database of step 7: exact markup with filter, pagination and sort links; sorting, user sorting replaces
  the order of the query, page 2 with count query, `limitToOnePage`, empty table with and without filter, sort link
  classes of the head renderer, filter conditions), `TableFilterFieldsTest` (text / date / options field: markup,
  conditions, `FilterOption`), more cases in `TableColumnRenderingTest` (`ActionsColumn`), `TableHelperTest`,
  `TableItemCollectionTest`. The new tests of the fixes were written after the change.
- **final / extension points:** extension points (documented in the class comment): `DbResultTable` (backend extends it:
  its constructor, the public HTML templates and the `PARAM_*` / `FILTER` constants stay), `SmartTable`,
  `AbstractTableColumn`, `TableHeadRenderer`, `TableFilter` (protected `reset()` / `checkInput()` / `applyFilters()` and
  the session accessors), `AbstractTableFilterField`. Everything else `final` (list in `UPGRADE.md`). No cleaner way
  than subclassing was found for `DbResultTable`: backend passes its own query and columns and overrides `render()`.
- **Static:** `Pagination` stays a static class (`final`): `render()` is a pure function of its arguments (it keeps no
  state and reads nothing but the snippet; the template engine is an argument). `TableHelper` stays static (pure
  factories). `LinkQuery` (`@internal`) builds the query string of page and sort links. No static property in the area.
- **Enum:** `TableSortDirectionEnum` (`ASC` / `DESC`, `opposite()`, `fromAscending()`, `isAscending()`) replaces the
  `TableHelper` constants. Not an enum: `ActionsColumn::EDIT` / `DELETE` (keys of an open set of individual links),
  `SmartTable` placeholders (strings of templates).
- **`mixed`:** only `TableItem::getRawValue()` / `$data` (the values of any data source, documented); the columns narrow
  with the new `TableItem::getScalarValue()`.
- **Security findings:** (1) `Pagination::render()` put `additionalLinkParameters` unencoded into an HTML attribute
  (`"` broke out of the `href`; the table encoded them on `addAdditionalLinkParameter()`, direct callers did not): all
  link parameters are encoded at the place the link is built. (2) `OptionsColumn` labels, `ActionsColumn` labels and
  `FilterOption` labels / values were output unescaped: text is encoded now, HTML is explicit (`HtmlText::fromHtml()` or
  `linkHtml`). `ActionsColumn` link targets, individual links, column labels (`AbstractTableColumn::$label`) and the HTML
  templates / classes of `SmartTable` and `SortableTableHeadRenderer` stay trusted HTML of the application (documented in
  the class comments). (3) Placeholders: values in cells and filter fields were searched for `[pagination]` etc. and
  replaced by markup: one pass over the template now. (4) The sort column of the request is checked against the sortable
  columns of the table (whitelist, unchanged) and direction through the enum; the order column is validated again by
  `DbQuery`. (5) `?page=` beyond an integer offset crashed: ignored. (6) The CSRF check of the filter (POST + token of
  the session; reset parameter without token) is unchanged and covered by tests.
- **Bugs found and fixed:** see "Fixed" in `UPGRADE.md` (`createTable()` without renderer, pagination links beyond the last
  page, delete link hidden by a number, placeholder replaced twice in `ActionsColumn`, `FileSizeColumn` with numeric
  strings, `StripHtmlTagsColumn` with `NULL`, stale stored option of `OptionsFilterField` broke the page for the session).
- **HTML output:** unchanged for the usual input (checked by the exact-markup tests). Differences only where the old
  output was broken or unsafe: encoded labels (see above); `&` in links is still
  unescaped `&` (safe, because all values are URL encoded), the pagination markup beyond the last page.
  Not changed (still as before): the column class `sort` is added twice to sortable columns
  (`class="sort sort"`; the class name of the column and of the renderer, both default `sort`).
- **Stays untested:** the custom snippet path of `TableFilter` / `Pagination` beyond the default snippets (the engine
  is tested elsewhere); `DbResultTable` against MySQL (SQLite runs the generated SQL); `BooleanColumn` with the strings
  `'1'` / `'0'` (rendered as text as before: with emulated prepared statements a `TINYINT` arrives as string; decide
  with backend whether it should map to the labels).
- **Open / for later:** `TableFilter::addPrimaryField()` accepts the same field identifier twice (the later replaces the
  earlier in `allFilterFields` only); `DateFilterField` accepts everything `new DateTimeImmutable()` accepts, including
  relative formats (`tomorrow`): harmless for a filter, a stricter format would be a behaviour change; `actra/backend`
  uses the removed `totalAmountMessage_*` names and the pre-4.29 `DbResultTable` constants (it follows with its own
  task).

### Step 9 (v4.36.0) – done

- Area `mailer` (derived from PHPMailer; the licence docblocks are in every derived file including the new ones split out
  of `MailerFunctions`, `gpl-3.0.txt` / `lgpl-3.0.txt` untouched). Baseline 136 -> 103 (all 33 entries of `src/mailer/`
  removed, no new entry). Tests 10653 -> 10905 (`tests/Unit/mailer/`: 252). Details and before/after in `UPGRADE.md`.
- **Characterization first:** 134 tests passed against the old code before the change: the complete header and body of
  text mails (quoted-printable, base64, 7bit, 8bit, binary), HTML mails with alternative text, all eight message types
  (alt, inline, attach and the combinations) with string and file attachments, recipients (to, cc, bcc, reply-to,
  confirm reading), non-ASCII names and subjects, punycode, word wrap, `mail()` line length, header injection (subject,
  names, addresses, custom headers, attachment names), address validation (valid and invalid cases), header and content
  encoders, text wrapper, file names and MIME types, attachments. The SMTP dialogue of the old `SmtpMailer` could not be
  tested without a socket: it was recorded once against a local socket server (greeting, EHLO, AUTH LOGIN, MAIL FROM,
  RCPT TO, DATA with dot stuffing, QUIT, rejected credentials, rejected recipient, bad greeting) and is reproduced with
  the fake transport in `SmtpMailerTest` (`MailMailer` could not be called at all without `mail()`; its arguments are
  tested with the new `MailFunction` double). The old behaviour that is wrong (see "Bugs found") was not pinned: those
  tests came with the fixes; the two tests that showed the duplicated attachments were flipped with the fix.
- **final / extension points:** `AbstractMail` (a project mail extends it, `setTextBody()` / `setHtmlBody()` protected)
  and `AbstractMailer` are documented extension points; everything else is `final`, value classes `readonly`.
  `TextMail`, `HtmlMail`, `SmtpMailer`, `MailMailer` are `final` (nothing in `actra/backend` extends them; backend uses
  `TextMail` and `SmtpMailer`). `@internal`: `MailMimeBody`, `MailMimeHeader`, `MailMimePart`, `MailerHeader`,
  `MailerHeaderCollection`, `MailerHeaderEncoder`, `MailerTextWrapper`, `MailerContentEncoder`, `MailerFileName`,
  `MailerMimeTypes`, `MailerMessageTypeEnum`, `StreamSmtpTransport`, `SmtpDataFormatter`.
- **Enums:** `MailerCharsetEnum`, `MailerEncodingEnum`, `MailerPriorityEnum`, `MailerContentTypeEnum`,
  `MailerMessageTypeEnum` (internal, replaces the strings `alt_inline_attach` ...). `MailerAddressKindEnum` was already
  one. No enum for SMTP authentication (there is only `AUTH LOGIN`) and none for MIME types of attachments (open set).
- **I/O and randomness testable:** `SmtpTransport` (open, readLine, writeLine, enableTls, close; `StreamSmtpTransport`
  is the only class that touches the socket, tested against a loopback server of the test process), `MailFunction`
  (`NativeMailFunction` calls `mail()`), `MimeIdGenerator`, `ServerNameResolver` (reverse DNS) and the existing `Clock`
  are constructor arguments of `AbstractMailer` / `SmtpMailer` / `MailMailer` with the production implementations as
  defaults. Doubles in `tests/Double/mailer/`: `CapturingMailer` (fixed date, id `ID`, server `mail.example.com`),
  `FakeSmtpTransport`, `RecordingMailFunction`, `FixedMimeIdGenerator`, `FixedServerNameResolver`, `ProjectHtmlMail`.
- **Structure:** `MailerFunctions` (519 lines, seven purposes) is split into `MailerHeaderEncoder`, `MailerTextWrapper`,
  `MailerContentEncoder`, `MailerFileName`, `MailerMimeTypes::getByFileName()`; the MIME body is built per message type
  by `MailMimeBody` and `MailMimePart` (the 130-line `switch` is a `match`); `SmtpDataFormatter` is the pure part of
  `DATA`; the attachments have a common interface `MailerAttachment` with `getContent()` (the body no longer asks
  `instanceof`). The static classes stay static on purpose: pure functions of their arguments without state (no static
  property in the area).
- **Security findings and fixes:** (1) `Bcc` header in every SMTP message (all recipients saw the blind copies):
  `headerHasBcc()`. (2) Header injection through the type of an attachment (`Content-Type` of the part): validated as
  `type/subtype`; custom header names validated (no colon, no space); line breaks in header values rejected before the
  encoder (an encoder fold `\r\n ` is the only line break a header may have). (3) `STARTTLS`: the result of
  `stream_socket_enable_crypto()` was ignored (credentials and message in plain text after a failed handshake):
  fails closed now, certificate chain and host name verified explicitly, TLS 1.2+; checked against a self-signed
  server (handshake refused). (4) Credentials: the log (public `$log`) held the base64 user name and password; exceptions
  never contained them, now they also do not contain addresses or header values (personal data, log forging). (5) SMTP
  command injection: addresses cannot contain line breaks (validated, tested), the `EHLO` name comes from reverse DNS
  and is now a plain host name or the address, `sendCommand()` still rejects line breaks. (6) Dot stuffing of long lines
  (see below). (7) Attachment names: directories and control characters removed, path of `MailerFileAttachment` is
  trusted (documented), stream wrappers (`phar://`, `http://`) are rejected, directories are rejected. (8) `mail()`:
  the `-f` sender is only passed if it matches `[A-Za-z0-9@_.-]+` (ASCII; was `ctype_alnum()` of the locale).
  (9) `SmtpMailer` validates host name and port in the constructor.
- **Bugs found and fixed (with tests, see `UPGRADE.md`):** Bcc leak; attachments sent twice in the related part;
  `TypeError` for subjects and names with more than a third non-ASCII characters (Cyrillic, CJK, `äöü`); header values
  with several encoded words were rejected (`Invalid header name or value`, e.g. every long non-ASCII name in a `mail()`
  message); dot stuffing of split lines; single part 7bit / 8bit / binary messages with lines of 1000+ characters;
  `TypeError` when the server closes the connection; `MailerStringAttachment` trimmed the (binary) content; the
  `$log` of `SmtpMailer` grew with every delivery and could hold `false` (`fgets()` failure).
- **Stays untested:** `NativeMailFunction` (calls `mail()`), the TLS handshake of `StreamSmtpTransport` with a valid
  certificate (only the refusal of a plain server is tested; the self-signed refusal was checked once by hand), reverse
  DNS (`gethostbyaddr()`; only the choice of the name is tested), the `X-Mailer` version (replaced in the tests),
  delivery to a real SMTP server and `sendmail`.
- **Open / for later:** a text body is quoted-printable encoded with `=0A` for its line breaks (PHPMailer behaviour,
  valid; real line breaks would be nicer); the lower casing of the local part of an address and `X-Mailer: PHP/<version>`
  (discloses the PHP version) are unchanged; addresses with non-ASCII local parts are sent without SMTPUTF8 negotiation;
  `SmtpMailer` only knows `AUTH LOGIN` and port 587 / STARTTLS (no implicit TLS on port 465, no `AUTH PLAIN` / OAuth);
  `addCustomHeader()` still takes the `maxLineLength` of the encoded words from the caller (a mailer-specific value that
  the mail does not know); with `useTls: false` the password goes over the network unencrypted (documented).
  `actra/backend` (`Mailer::sendTextMail()`): it still builds `SmtpMailer` without `serverAddress` (needs
  `HttpRequest::getServerAddress()` since v4.29.0) and passes `new SMTPMailer(...)` (class name case); nothing else of
  this release affects it (`TextMail` and `SmtpMailer` with default arguments).

### Step 10 (v4.37.0) – done

- Area `auth`, `security` and `session`. Baseline 103 -> 75 (all 26 entries of `src/auth/` and 2 of `src/session/` removed,
  no new entry). Tests 10905 -> 11080. Details and before/after in `UPGRADE.md`.
- **Characterization first** (passed against the old code before the change): `PasswordTest` (salt, SHA-256, valid /
  invalid, unicode, empty), `AccessRightCollectionTest`, `MicrosoftIdTokenTest` (with a generated RSA key and a
  self-signed certificate, `TestJwtIssuer`, no network: valid token, tampered payload, other key, `none` / HS256 / RS512,
  missing `kid`, expired, `nbf`, `aud`, `tid`, `nonce`, private key in the key set, broken certificate chain), more
  `AuthenticatorTest` cases (wrong password counting, lock-out also with the right password, inactive, no password right,
  IP whitelist in and out, token login without password check, failed credential check of the project),
  `UnauthorizedExceptionsTest`, `AuthResultEnumTest` (stored values), more `CspPolicySettingsTest` cases (default policy,
  nonce rules, empty directives), and `AbstractSessionHandlerTest` in separate processes (other address, other user
  agent, expired session, 30 minutes regeneration, invalid session ID characters, cookie flags, `regenerateId()`, the
  SameSite switch). The tests of fixes (strict mode, fixation, rehash, key cache, ...) came with the fixes.
- **final / extension points:** extension points (documented in the class comment): `AuthUser`, `Authenticator`,
  `MicrosoftAuthenticator`, `AuthWebToken`, `AbstractSessionHandler`. No cleaner way than subclassing was found for
  `AuthUser` / `Authenticator` (backend loads its user from the database and writes its own login log in the `db…()`
  and `logAuthResult()` hooks; composition would be a redesign of both with a repository interface, to decide when
  backend follows). Everything else `final`; `@internal`: `AuthSessionKeyEnum`, `CachedKeySet`, `JsonWebKeySetParser`,
  `LoginAttempt`, `MicrosoftLoginUri`, `MicrosoftTenantId`.
- **Enums:** none new. `AuthResultEnum` / `AuthMethodEnum` existed (values pinned by a test, they are stored in the login
  log of the projects). `AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN` stays a constant (rights are an open set of the
  project). The SameSite value is the literal type `'Lax'|'None'` of a private method (three values, never a parameter).
- **Static:** no static property in the three areas. Static stays only for pure functions: `Password::generateNew()` and
  `spendVerificationTime()` (functions of their arguments), `CsrfHiddenFieldRenderer::render()`,
  `JsonWebKeySetParser::parse()`, `MicrosoftLoginUri::create()`, `MicrosoftTenantId::assertValid()`,
  `IdTokenTimeClaimsValidator` (instance), `AuthWebToken::decodeJsonObject()`.
- **Security review, fixed (with tests):**
  1. *Passwords* were a salted SHA-256 (fast hash): `password_hash()` Argon2id now, legacy passwords verified with
     `hash_equals()` and upgraded at the login (`AuthUser::rehashPassword()`, new abstract `dbUpdatePassword()`); a login
     for an unknown user spends the time of a verification against a dummy hash.
  2. *Session fixation:* `use_strict_mode` has no effect for a `SessionHandler` subclass without `validateId()` (PHP then
     treats every ID as valid, checked with a probe: a well-formed unknown cookie ID was kept). `AbstractSessionHandler`
     implements `SessionUpdateTimestampHandlerInterface` with the abstract `sessionExists()`. `AuthSession::logIn()` did
     not regenerate the ID: it does now.
  3. *Session replacement* (other client or expired): the old data is removed before anything else, an unchanged ID is an
     error, missing handler data counts as untrusted (fail closed); `use_only_cookies` / `use_trans_sid` set.
  4. *JWT:* algorithm fixed to RS256 (checked: `none`, HS256, RS512, lower case), `iss` check added, exactly three
     segments, claims must have the right type, `hash_equals()` for `aud` / `tid` / `iss` / `nonce`, invalid JSON is an
     `UnauthorizedException`; the key cache is per tenant, written atomically with 0600, replaced only by a key set that
     parses, refreshed at most every 5 minutes; the download uses verified HTTPS without redirects, timeout and size
     limit; the tenant ID is validated (URL and file name).
  5. *Logs:* the Microsoft SSO log contained the raw ID token and the nonce: exception class and message only now.
  6. *CSP:* `Host` header injected into the policy (sanitized); nonce rules unchanged and tested (no `unsafe-inline` /
     `unsafe-eval` by default). *CSRF:* the hidden field encodes the token; token generation (`random_bytes(32)`) and
     `hash_equals()` were fine.
  7. The Microsoft login URL did not encode its parameters.
- **Security review, not changed (decisions for later):**
  - `logAuthResult()` receives the session ID (the one before the login, dead after a successful login but live after a
    failed one) and backend writes it into its login log; security.md asks for no session IDs in logs. Changing the
    signature breaks backend: replace it by a hash of the ID or drop it when backend follows.
  - `getUserName()` of the Microsoft token is the `email` claim: an email address is mutable and for multi-tenant apps
    not verified by Microsoft ("nOAuth"); `oid` / `sub` would be the stable identifier, but backend identifies users by
    email. The tenant is fixed and checked (`tid`), which limits it to the users of that tenant.
  - The certificate chain of a key is checked for consistency only (each signed by the next, root self-signed), not
    anchored in a trusted root and not for validity dates: the key set is trusted because it comes from Microsoft over
    verified HTTPS.
  - The lock-out after `maxAllowedWrongPasswordAttempts` is per user and permanent until the project resets the counter
    (a lock-out of a known user name by anybody is possible; the unlock is the project's decision). No rate limit per IP.
  - Sessions are bound to the exact remote address: a user whose address changes (mobile network) loses the session.
    Unchanged; behind a proxy `REMOTE_ADDR` is the proxy.
  - `session.cookie_secure` is always on: sessions need HTTPS (also in local development).
- **Bugs found and fixed:** see "Fixed" in `UPGRADE.md` (fixation, CSP host, broken key cache locked every login, key
  download on every unknown `kid`, JWT with extra segments accepted, `JsonException` instead of a failed login,
  `getTrustedRemoteAddress()` throwing for corrupt sessions).
- **Stays untested:** `MicrosoftKeySetSource::download()` (network), `MicrosoftAuthenticator::redirectToMicrosoftLogin()`
  (`exit`; the URL is tested through `MicrosoftLoginUri`), the session handler against another storage than files,
  `session_destroy()` failing ("Session object destruction failed" is ignored as before, the replacement is checked by the
  ID afterwards), `Password` with the PHP build without Argon2 (`PASSWORD_DEFAULT` branch).
- **Follow-up in `actra/backend`:** `MyAuthUser` needs `dbUpdatePassword()` (`DbAuthUserRepository::setPassword()` without
  resetting the attempts) and no `Password::generateNew('unused')` per user load; the password columns need 255
  characters for the hash and an empty salt; `new Password(salt:, hash:)`, `isValid()` and `generateNew()` keep working;
  API keys (`DbAuthApiKeyRepository`) should switch from `Password` to the new `SecretTokenHash` (SHA-256 of a 256 bit secret; Argon2id costs one verification per request): re-issue the keys or verify the old `Password` once and store the new hash; its login log keeps working
  (`logAuthResult()` unchanged).
- **Open / for later:** `IpValidator` is in `datacheck` (step 13); `UnauthorizedException` in `exception` (step 13);
  `HttpResponse::redirectAndExit()` is static and exits (step 4 area); `Session` / `NativeSessionStorage` /
  `ArraySessionStorage` / `SessionStorage` were already clean and only got line breaks.

### Step 11 (v4.38.0) – done

- Area `api`. Baseline 75 -> 48 (all 27 entries of `src/api/` removed, no new entry). Tests now 11345
  (`tests/Unit/api/`: 249). Details and before/after in `UPGRADE.md`.
- **Usage in other projects (read-only search, `actra\yuf\api`):** `crm.actra.ch` (`AbaNinja*`: `CurlGetRequest::prepare` (renamed `create`, see below),
  `CurlPostRequest::prepareWithJsonBody`, `useTokenAuthentication`, `setHttpHeader`, `setTimeoutInSeconds`, `execute`,
  `rawResponseBody`, `hasErrors`, `errorMessage`, `getJsonResponse`), `actra.domains` (`OpusClient`, `RealtimeClient`,
  `OpenProviderClient`, `Crm*`, `ExternalApiRequest` (takes `AbstractCurlRequest`), `CronApiRequest`,
  `ActionSyncSwitchErrors` (`useBasicHttpAuthentication`, `responseHttpCode`), `HttpApiCommand`, `syncExchangeRates`:
  the same calls plus `prepareWithPostBody`, `prepareWithoutBody`, Put / Patch / Delete requests, `curlInfo`, `errorCode`),
  `drogeriehaas.ch` (`hciSync`: `prepareWithPostBody`, `getXmlResponse`, `curlInfo`). Nobody uses `setCurlOption`,
  `removeCurlOption`, `disableSslCheck`, `acceptRedirectionResponseCode`, `CurlHeadRequest`,
  `createFromPreparedCurlHandle` or extends a request class. `my.cmas.ch` and `cron.actra.ch` have an old own copy
  (`framework\api`), not yuf. **Follow-up in projects:** rename `prepare…` to `create…` for the request classes; check that their API URLs are `https://` (credentials over plain
  HTTP are refused now) and that no server needs TLS 1.0 / 1.1 or an untrusted certificate; nothing else changes.
- **Characterization first** (`CurlRequestCharacterizationTest`, 54 tests, passed against the old code before the
  change): method, URL and query, form encoding (booleans, `null`, nested, objects, RFC 3986), the five body kinds with
  `Content-Type` of POST / PUT / PATCH, `HTTP_PRETTY_PRINT`, `Accept` of JSON:API, custom headers, basic and bearer
  authentication, timeouts, status codes 300-599 and their messages, accepted 301 / 303, redirects not followed,
  connection refused, request timeout, JSON / XML responses, independent requests. The tests of the fixes and of the
  new rules came after the change; the one test that pinned "a request can be executed once" was replaced.
- **Network in the tests:** no internet, no mock. `LocalHttpServer` starts PHP's built-in web server (`php -S` on a free
  loopback port, router `tests/Double/api/echo-server.php`: echoes method, URI, headers and body as JSON and has fixed
  paths for status codes, redirects, repeated headers, big and chunked bodies, JSON and XML) in a separate process,
  because `curl_exec()` blocks the process of the test. `LocalTlsServer` + `tls-server.php`: a TLS server with a
  self-signed certificate (generated per test) for the certificate check. The timeout test connects to a listening
  socket of the test process that never answers. `EchoedRequest` reads the echo. `phpstan.neon` allows the superglobals
  in `echo-server.php`; the two `proc_open()` calls carry a `@phpstan-ignore disallowed.function` with a reason.
- **Design / static:** `AbstractCurlRequest` describes the request (method as `RequestMethodEnum`, `CurlTargetUrl`,
  `CurlHeader`s, body, timeouts, limit, `CurlAuthentication`); `CurlClient` sends it (one `CurlHandle` per client,
  `curl_reset()` before every request); `CurlOptionsBuilder` (pure) translates it into cURL options;
  `CurlResponseCollector` (pure) collects body and headers and enforces the size limit; `CurlErrorEvaluator` (pure) decides
  about errors; `CurlFormEncoder` (pure) encodes post fields. No static property in the area. The static factories stay
  (stateless); decision of the user: renamed `prepare…()` -> `create…()` in this release (no aliases, table in
  `UPGRADE.md`; about 35 call sites in `crm.actra.ch`, `actra.domains`, `drogeriehaas.ch` to follow).
- **final / extension points:** the six request classes and `CurlResponse` (`final readonly`) and `CurlClient` are `final`;
  `AbstractCurlRequest` stays an abstract type for signatures (`ExternalApiRequest`), documented as no extension point.
  `@internal`: `CurlBodyTypeEnum`, `CurlAuthentication`, `CurlAuthenticationMethodEnum`, `CurlTargetUrl`, `CurlFormEncoder`,
  `CurlOptionsBuilder`, `CurlResponseCollector`, `CurlErrorEvaluator`, `CurlResponseError`.
- **Enums:** `RequestMethodEnum` is reused for the methods (all six classes map to a case; `OPTIONS` has no request class
  but the `match` is exhaustive), `CurlBodyTypeEnum` (form, XML, JSON, JSON:API, plain text with content type and default
  headers), `CurlAuthenticationMethodEnum`. `ERROR_BAD_HTTP_RESPONSE_CODE` / `ERROR_RESPONSE_TOO_LARGE` stay typed constants
  (error codes next to the cURL codes, not a closed set). `mixed`: only `CurlFormEncoder::convertValue()` (any value of a
  form array, narrowed right there) and the unsealed rest of the `curl_getinfo()` shape.
- **Security findings and fixes:** (1) `disableSslCheck()` and `setCurlOption()` could switch off certificate and host name
  checks, protocols and more: removed; verification is set explicitly (`VERIFYPEER`, `VERIFYHOST` 2, TLS 1.2+). (2) Any
  scheme (`file://`, `gopher://`, ...) reached cURL: URL validated (http / https, host, no user info, no control
  characters or backslashes) and `CURLOPT_PROTOCOLS_STR` / `CURLOPT_REDIR_PROTOCOLS_STR` set. (3) Redirects: never followed
  (`FOLLOWLOCATION` false, as before), so credentials cannot reach another host. (4) Header injection through values
  and names of `setHttpHeader()`, the bearer token and the basic credentials: validated, messages without the value.
  (5) `content-type` in other spellings bypassed the protected headers. (6) Timeouts of 0 (infinite) are rejected;
  defaults unchanged. (7) Unlimited response bodies: 32 MiB default limit (Content-Length check and a write callback for
  streamed bodies). (8) Credentials over plain HTTP: refused except for loopback. (9) Secrets in debug output:
  `__debugInfo()` of the request and `CurlAuthentication` (the secret is private). (10) `getXmlResponse()` with
  `LIBXML_NONET`; no entity substitution (as before; tested with an external file entity). (11) Exceptions and messages
  never contain a URL (query tokens), header value, token or body; `CurlResponse::$errorMessage` has the text of cURL
  (host names, no query). **Not changed / for the user:** the application must validate target URLs that come from user
  input (SSRF: yuf does not know the allowed hosts); an API key in `setHttpHeader('X-Api-Key')` over `http://` is not
  detected; `curlInfo` contains the full URL (query included), the callers who log it (`OpusClient`, `RealtimeClient`,
  `OpenProviderClient` do) should know; proxy variables of the environment (`https_proxy`) are still honoured by libcurl.
- **Bugs found and fixed:** status codes missing from `HttpStatusCodeEnum` (`429`, `418`, ...) were no error; `content-type`
  spelling bypass; `getJsonResponse()` / `getXmlResponse()` of a failed request threw a `TypeError`; HEAD requests returned
  the raw header text as body (now headers are parsed for every request).
- **Stays untested:** a successful TLS handshake (only the refusal of an untrusted certificate is tested), the minimum
  TLS version, connection reuse of `CurlClient` (the option reset is tested, the reuse is cURL), the proxy environment,
  `CURLE_UNSUPPORTED_PROTOCOL` from cURL itself (the URL is rejected earlier), response headers over the 300 KB limit of
  libcurl, HTTP/2, real servers.
- **Open / for later:** `acceptRedirectionResponseCode()` accepts only 301 and 303 (not 302 / 307 / 308), as before;
  `useBasicHttpAuthentication()` takes `user:password` as one string (unchanged API); no retry, no proxy setting, no
  cookie jar, no streaming to a file (the whole body is in memory up to the limit); `CurlResponse::$curlInfo` is the raw
  cURL array; a `PUT` / `PATCH` / `DELETE` with a body of another kind than the five factories needs a new `create…()`.

### Step 12 (v4.39.0) – done

- Area `html` and `layout`. Baseline 48 -> 23 (all 25 entries of `src/html/` removed, no new entry). Tests 11345 -> 11513
  (`tests/Unit/html/`, `tests/Unit/layout/`). Details and before/after in `UPGRADE.md`.
- **Characterization first:** `HtmlEncoder`, `HtmlTag` / `HtmlTagAttribute` / `HtmlText` (markup, attribute escaping,
  boolean attributes, self-closing tags), `HtmlDataObject` / `DetailDataObject` / the collections, `HtmlReplacementCollection`
  (every `add…()` and `getArrayObject()`), `NavigationItem` / `NavigationItemCollection` (access rights, active item,
  CSS classes) ran against the old code before the change: all passed except the ones that pin the later fixes (invalid
  UTF-8, `addInt()` as `int`, sticky `isActive`, validation). `HtmlDocument` could not be built without `Core`; its tests
  came after the change and the scenarios (template + content file, group, active ids, class handling) were compared
  with the old class through a throw-away probe (reflection on the old class, outside the repository): identical
  output.
- **`HtmlDocument` without `Core`:** it takes a `HtmlDocumentSettings` (view directory, file group, file title, file name,
  language code, copyright, robots; `ContentHandler` builds it) instead of `RequestHandler` and `Core`; tests render
  real pages with a temporary directory and the `TemplateEngineFactory`, no reflection. `ContentHandler::processRequest()`
  itself is still not tested (it needs a `Core` for the settings), see "Stays untested".
- **final / extension points:** everything `final` except `HtmlElement` (abstract base of the form components, documented:
  projects extend `FormComponent`, not it) and `HtmlDataObject` (documented: subclasses fill their data in the constructor,
  `DetailDataObject` is the example). `readonly` where possible (`HtmlReplacement`, `NavigationItem`,
  `HtmlDocumentSettings`, `HtmlTagAttribute` / `HtmlText` properties; `HtmlTag` and the collections are mutable by
  design). No `@internal` class: all of them are used by projects (`HtmlDocumentSettings` is only built by
  `ContentHandler` and tests).
- **Enums:** none. `HtmlText` has an `isHtml` flag, `DetailDataObject` an `$isHtml` and `HtmlTagAttribute` a
  `valueIsEncodedForRendering` flag (two states, no set of values). The CSS class defaults of `NavigationItem` are
  values, no set.
- **Static:** `HtmlEncoder` stays a static class (`final readonly`: pure functions of their argument, no state,
  no dependency). No static property in the area.
- **`mixed`:** only `HtmlReplacementCollection::getArrayObject(): ArrayObject<string, mixed>`: `ArrayObject` is invariant
  and the template engine and its tests add arrays and objects to it; the values `HtmlReplacement` hands over are
  `RendererValue` (string, int, float, bool, `stdClass`, lists, `null`).
- **Security findings and fixes:** (1) `HtmlDocument` added values of the request (`bodyClassName`, `requestedFileName`)
  as HTML: reflected XSS when a view renders a page for a URL-controlled name; all of them go through `addText()` now
  (`charset`, `scripts` and the CSRF field are HTML of ours). (2) The file group and title of the request were appended
  to the content directory without a check (`..`): 404 for `..` segments, absolute paths, backslash, null byte.
  (3) Tag and attribute names were output as they are: validated. (4) `valueIsEncodedForRendering: true` with a double
  quote made broken or injectable HTML: rejected. (5) `HtmlEncoder::encode()` lacked `ENT_SUBSTITUTE` (invalid UTF-8 gave an empty string,
  a silent loss of the value); now `ENT_QUOTES | ENT_SUBSTITUTE`, `UTF-8`. (6) `encodeKeepQuotes()` (no quote encoding) is only
  used between tags (`FileField` messages, `TableItem`, table columns, `ActionsColumn` labels; checked all callers); it
  is documented as never for attributes and carries one `@phpstan-ignore disallowed.function` with the reason.
  (7) `NavigationItem::$href` is checked (no `javascript:` / `data:`, no quote or angle bracket); its `title`, `svgPath`
  and CSS classes are trusted HTML of the application (documented). (8) `encodeArray()` / `encodeObject()` mutated their
  argument and nothing used them: removed.
- **Bugs found and fixed:** see "Fixed" in `UPGRADE.md` (request values as HTML, path traversal, invalid UTF-8,
  `addInt()` handing over a `float`, sticky `NavigationItemCollection::$isActive`). `HtmlReplacement::getDataForRenderer()`
  did not list `int` in its return type, that was the cause of the float.
- **HTML output:** byte-identical for normal input (template characterization tests, the `HtmlDocument` scenarios and
  the example are unchanged). Differences only for values with special characters in the request-controlled parts
  (now escaped) and for invalid UTF-8.
- **Outside the area:** `HtmlReplacement::fromHtml()` in `ExceptionHandler`, `HtmlDocumentSettings` in `ContentHandler`,
  one cast removed in `TemplateData::fromReplacements()` (the keys are `string` now), `TemplateDataTest` expects an `int`.
- **Stays untested:** `ContentHandler::processRequest()` and the creation of `HtmlDocument` from `Core` /
  `RequestHandler` (`Core` cannot be built), the combination of `HtmlDocument` with a real view.
- **Open / for later:** `NavigationItemCollection::addItem()` replaces an item with the same `navKey` silently;
  `HtmlDataObject` is a mutable `stdClass` wrapper (the templates read `data` directly); `HtmlDocument::render()` still
  changes its own replacements (`this`) and active ids when it renders (rendering twice is safe, tested);
  `HtmlSnippet::render()` adds the nonce to the caller's replacements (unchanged); `DetailDataObject` takes the label as
  HTML, a plain-text label would be a new argument; the form renderers still passed `valueIsEncodedForRendering: true`
  in 90 places (done in step 14: `HtmlTagAttribute::fromText()` / `fromHtml()` / `fromName()`).
- **Follow-up in `actra/backend`:** only the names it already has to change (`HtmlDocument::get()`,
  `HtmlText::unencoded()` / `encoded()`, `addEncodedText()`); `new NavigationItem(...)` keeps working (its hrefs are
  relative); `HtmlTag` / `HtmlTagAttribute` calls of `SearchQueryField` and `SearchSelectOptionsField` use valid names.

### Step 13 (v4.40.0) – done

- Area `exception` and `datacheck`. Baseline 23 -> 0 (all 10 + 13 entries removed, no new entry; `phpstan-baseline.neon` is
  empty now: `ignoreErrors: []`). Tests 11513 -> 11972. Details and before/after in `UPGRADE.md`.
- **Characterization first** (passed against the old code): all validators and sanitizers with edge cases (IPv4 / IPv6
  ranges and whitelists, an example IBAN of each of the 66 supported countries plus changed check digits, zip codes of
  DE / CH / AT, domains with IDN / label and total length, TLD list, float / integer incl. limits and a locale with a
  decimal comma if installed, domain normalization incl. query / path / port). The old code was probed for the behaviour
  that is wrong; those cases came with the fixes. `ExceptionHandler` could not be characterized (`exit`); the example
  (`/`, `/nope.html`, debug on and off) was compared before and after by hand.
- **ExceptionHandler:** `handleException()` is the thin shell (`createResponse()->sendAndExit()`); everything else is
  testable: `ErrorKindEnum` (status, page, titles, fixed production texts), `ErrorOutputFormatEnum` (JSON / text / HTML by
  content type), `ExceptionDebugInfo` (plain values of the debug page), `ErrorPageValues` (values of every error page, all
  escaped text), `ErrorPageRenderer` (file, missing page), `ErrorResponseFactory` (the `HttpResponse`). `ExceptionHandlerContext`
  holds what the pages need instead of `Core` (`errorDocsDirectory`, `copyright`, `availableLanguages`, a closure that
  creates the template engine) so tests build the handler without `Core`. Doubles: `ExceptionHandlerContextFactory`,
  `TeapotExceptionHandler`, fixtures in `tests/Fixture/exception/error_docs/`. `HttpResponse::getContentString()` was added
  (core area, additive) so tests can read the body.
- **Static state:** `ExceptionHandler::$registeredInstance` is gone: `register()` recognises a second registration through
  the handler `set_exception_handler()` returns (it restores it and throws). The only static property left in yuf is
  `Core::$isInitialized`. The tests need no reflection.
- **final / extension points:** `ExceptionHandler` (documented; hooks `createDebugResponse()`, `createNotFoundResponse()`,
  `createUnauthorizedResponse()`, `createDefaultResponse()`) and `UnauthorizedException` (subclassed in `auth`) stay open;
  `NotFoundException`, `PhpException`, all `datacheck` classes and the new helpers are `final` / `@internal`. `Sanitizer`,
  `Validator` and the typed classes stay static (`final readonly`: pure functions without state; backend calls
  `IpValidator::` statically).
- **Enums:** `IpTypeEnum` cases upper case (⚠️); new internal `ErrorKindEnum`, `ErrorOutputFormatEnum`. The zip code formats
  are a map by country code (open set, input is a string), not an enum.
- **Security findings and fixes:** (1) debug page output of message / file / trace was raw HTML (XSS in debug mode): escaped;
  (2) production JSON / text showed exception messages (JWT details) and codes (SQLSTATE): fixed texts and the HTTP status
  as code; (3) a missing error page file showed its path in production: logged instead; (4) `IpValidator::isInWhitelist()`
  accepted an invalid address that equals an entry (`''` / `''`): fails closed; (5) `ErrorPageRenderer` rejects file names
  with a path; (6) `requestedFileName` (user input) was added as HTML to the error pages: escaped. Debug mode still dumps
  session, GET, POST and files (by design, debug only). TLD list: IANA copy updated to version 2026100800 (2026-10-08, approved download;
  update procedure in the `TldValidator` docblock).
- **Bugs found and fixed:** see "Fixed" in `UPGRADE.md` (`FloatSanitizer` negative / zero-prefixed integers, `INF`;
  `IntegerSanitizer` 2^63; `trimmedString()` TypeError; `DomainSanitizer` `www.` rule; `DomainValidator` bare TLD and IDN TLD;
  `ZipCodeValidator` DE anchoring).
- **Stays untested:** `handleException()` itself (`exit`), the effect of `applySystemLocale()` of a real language on the
  error page texts, `Core`-built context (`Core` cannot be built), a failing template in an error page (the exception
  would leave the handler).
- **Open / for later:** `DomainSanitizer` reduces the input to the host (⚠️, decision of the user: port, path, query, fragment and
  user info after a scheme are dropped; `user@example.com` without scheme is kept). IBAN: the length per country is
  not checked (checksum and country only). `isInWhitelist()` does not treat IPv4-mapped IPv6 addresses (`::ffff:a.b.c.d`) as
  IPv4 (fails closed; a dual-stack server may need entries for both). A plain invalid whitelist entry is ignored, an invalid
  range throws. `Validator::stringWithoutWhitespaces()` knows ASCII whitespace only. Backend: `IpTypeEnum::ip` ->
  `IpTypeEnum::IP` in `ValidIpAddressRule`; nothing else it uses changed (`IpValidator::validate()` / `isInWhitelist()`,
  `NotFoundException`, `UnauthorizedException`); its API clients must not rely on the exception message in production.

### Step 14 (v4.41.0) – done

- Area `form` (last step of the plan). Baseline stays empty (`ignoreErrors: []`). Tests 11982 -> 12052. Details and
  before/after in `UPGRADE.md`.
- **`HtmlTagAttribute` (decision of the user):** private constructor, named constructors `fromText(name, text)` (plain text,
  escaped when rendered; `string|int`, so `maxlength` needs no cast), `fromHtml(name, html)` (encoded or trusted, a `"` is
  still rejected) and `fromName(name)` (no value). The user asked for `?string`; `null` is `fromName()` instead, because
  "no value" is not a text. All 111 call sites of `src/form/` were rewritten mechanically with a tokenizer script and
  reviewed: 99 `fromText()` (names, ids, option keys, classes, `type`, placeholder, links, `data-*`, numbers), 2
  `fromHtml()` (the two `value` attributes of `InputFieldRenderer` and `HiddenFieldRenderer`: `renderValue()` is encoded
  by the field), 10 `fromName()` (`checked`, `selected`, `multiple`, `autofocus`, `novalidate`, valueless `data-*`).
  The tests (`HtmlTagTest`, 16 call sites) were converted by hand. HTML output is byte-identical for values without
  special characters (all form, template and `example/` tests unchanged); with special characters the attributes are
  escaped now (see "Security").
- **Extends in the other projects (read-only grep of `/Users/christof/development/*` without `vendor`, `cache`, `yuf`,
  `framework`; many projects still run yuf v3):** `Form` 187, `TextField` 15, `SelectOptionsField` 14, `FormComponent` 9,
  `FormRenderer` 7, `FormField` 5, `TextAreaField` 4, `CheckboxOptionsField` 4, `FormOptions` 3 (final since v4.0.0), `FileField` 3
  (final since v4.0.0), `FormRule` 2, `DateField` 2 (final since v4.0.0), `StringRule` 1, `RadioOptionsField`, `OptionsField`,
  `TextualField`, `FormControl`, `FormCollection`, `BooleanField` 1 each. `actra/backend`: `Form` 15,
  `SelectOptionsField` 2, `StringRule`, `TextAreaField`, `TextField` 1 each. Nobody extends a concrete renderer, a concrete
  rule, `ToggleField`, `MultiToggleField`, `MultiSelectOptionsField`, `FormInfo`, `FormSubHeadline` or `NullField`.
- **final / extension points / internal:** `final`: those six fields/components, the 13 concrete rules and 17 renderers (the
  form-v4 design left them open "because projects extend them"; the grep shows they do not, customization goes through
  `FormRenderer` + `setRenderer()` and the typed rule bases). Documented extension points (PHPDoc "Extension point: ..."):
  `Form`, `TextField`, `TextAreaField`, `SelectOptionsField`, `CheckboxOptionsField`, `RadioOptionsField`, `BooleanField`,
  `IntegerField` (parent of `NumericField`), `FormControl`, `InputFieldRenderer` (parent of `NumericFieldRenderer`); the
  abstract bases stay abstract. `@internal`: `ToggleChildren`, `BooleanFieldListRenderer`, `CheckboxItemRenderer`,
  `HiddenFieldRenderer`, `NumericFieldRenderer`, `ToggleFieldRenderer` (projects use `DefinitionListRenderer`,
  `FormControlRenderer`, the static helpers of `LegendAndListRenderer`; these stay public API). `readonly` where possible
  (renderer properties, `FormSubHeadline`); `FormRule`, `ToggleChildren`, the fields and `FormRenderer` are mutable by
  design. `ExtensionPointsTest` pins the decisions.
- **Static state:** none in `src/form/` (only pure static helpers: `AmountParser`, the `add…ToParentHtmlTag()` helpers of
  `FormRenderer`, `FormInput::from…()`). `mixed` only in `FormInput` and `SessionFileUploadStorage::toUploadedFile()`
  (narrowing of request / session data).
- **Security findings and fixes:** (1) every plain text value of the markup was output as HTML (`valueIsEncodedForRendering:
  true`): `placeholder`, `name`, `id`, option keys, CSS classes, `data-*` values, the cancel link, the form `action`; a `"`
  threw, `&` / `<` were raw (HTML injection for a value that comes from data, e.g. an option key from a database). All
  are `fromText()` now. (2) The remove button of `FileFieldRenderer` output the field name without encoding. (3)
  `FormControl` output `FormMessages::$cancel` (plain text) as HTML. Checked and fine: labels / info texts / error texts
  are `HtmlText` (the caller says text or HTML; messages of `FormMessages` and `rejectInput()` are `fromText()`), file
  names in errors and the remove button are encoded, the CSRF token is read from the posted data only and compared by
  the token source, the upload pointer is restricted to `[a-zA-Z0-9_]` and only addresses the files of the own session,
  stored files are named after the PHP temp file (never the client name), paths from the session must be below the root
  directory (no `..`), `FileField` does not check type or size of an upload (`UploadedFile::$type` is client data;
  documented; the PHP limits apply, a project checks content type and extension itself).
- **Bugs found and fixed:** `FormSubHeadline` accepted any level (`<h7>`): 1 to 6 now; `Form::getField()` / `removeField()`
  threw a plain `Exception` with a misleading message (`LogicException`, new tests: the methods were not covered).
- **Open / for later:** a component can be rendered once (`getHtmlTag()` / `render()` a second time throws "You cannot
  overwrite an already defined Tag-Element", the renderer sets its tag once; `Form::render()` twice fails); `FormRenderer`
  keeps the two-phase `prepare()` / `getHtmlTag()` API. `FileField` has no built-in type / size check. `ToggleField` and
  `MultiToggleField` duplicate their child methods (a trait or a delegating base would remove it; both are `final`
  now). `actra/backend`: `SearchQueryField` and `SearchSelectOptionsField` use `new HtmlTagAttribute(name: 'for', value:
  $this->name, valueIsEncodedForRendering: true)` -> `HtmlTagAttribute::fromText(name: 'for', text: $this->name)`; projects
  with their own renderers (`intern.public-health-edu.ch`, `my.cmas.ch`) have the same pattern.

### Plan complete

All areas of `src/` have the coding standard; the baseline is empty (`phpstan-baseline.neon`: `ignoreErrors: []`).
What is left is listed in [docs/plans/done/standard-migration/remaining.md](../standard-migration/remaining.md) ("Open points"), taken
from the "Open / for later" notes above: the Git index names of `CSVFile.php` / `SimpleXMLExtended.php` (step 5), the
follow-up of `actra/backend` per release (`UPGRADE.md`), and the small functional points of each area. `actra/backend`
follows next, as decided.

### Superglobals rule – done

- `actra/coding-standard` raised to v1.3.0; `phpstan.neon` includes `phpstan-no-superglobals.neon`.
- `actraSuperglobalsAllowIn`: `src/Core.php` (`DOCUMENT_ROOT`), `src/core/HttpRequest.php` (`fromGlobals()`),
  `src/session/NativeSessionStorage.php`, `src/session/AbstractSessionHandler.php`, plus the tests that prepare
  superglobals: `SearchHelperRequestTest`, `HttpRequestFromGlobalsTest`, `HttpRequestGetallheadersTest`,
  `AbstractSessionHandlerTest`, `NativeSessionStorageTest`. No baseline entries; `example/` needs no exception.

## Final note (2026-10-09)

Done: every area of `src/` meets the standard, PHPStan baseline empty (v4.41.0); the rest followed in
[standard-finish](../standard-finish/plan.md). The plan moved to `docs/plans/done/` (coding standard v1.16.0).
