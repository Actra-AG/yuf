# Plan: performance (coding standard v1.13.0)

`standards/performance.md` makes best possible performance the first priority after security and correctness. Three
read-only audits (request lifecycle; database, tables and HTTP client; templates, forms, phone and mailer) found the
gaps below. Nothing was measured yet: every step starts with a measurement (example app, a table and a form with many
rows/options) and checks the result by measuring again.

One step per release, `composer check` green, characterization tests first, `example/` checked in the browser after
steps that change responses or rendering. No step is breaking unless marked ⚠️.

## Decisions (user, 2026-10-09)

1. Preferred language: remembered after the view, only on requests that started the session anyway. No own cookie.
2. Template cache: opt-in production mode without freshness check (projects clear the cache on deployment).
3. Mailer server name: keep reverse DNS, resolve once and cache it in the cache directory.
4. Phone metadata: process-wide static memo (immutable data); update the class documentation.
5. Order: as listed below (correctness and locks first).

## Steps

### Step 1 – HTTP responses (correctness first)

- Bug: HTML/JSON responses send `Last-Modified: now` and an ETag; `isNotModified()` compares `If-Modified-Since` with
  `===`, so a repeated request within the same second gets `304` and the browser shows the stale page (e.g. after
  POST → 303 → GET). `src/core/HttpResponse.php` (`createHtmlResponse()`, `createResponseFromString()`).
- Dynamic HTML/JSON: `Cache-Control: private, no-store`, no `Last-Modified`, no ETag (HTML contains the CSP nonce,
  so its ETag never matches; the SHA-256 over the body is wasted). ⚠️ behaviour: browsers no longer store pages.
- Files: quoted ETags, `If-None-Match` as list with weak comparison and precedence over `If-Modified-Since`, no
  `Connection: Close` on `304`, optional `Cache-Control` with `max-age` / `public` / `immutable` for versioned assets.
- `NativeResponseSender`: clear every output buffer level, bigger chunks or `fpassthru()`, close a started session
  before the content is written (lock during downloads and `sendAndExit()` from a view).

### Step 2 – Session lock

- The preferred language starts the session on every request of a route with a language for visitors with a session
  cookie (`RequestHandler::rememberPreferredLanguage()`) and keeps the lock during the whole view. Remember it after
  the view, only if the session was started anyway (decision 1), and after the 404 checks.
- `lastActivity` is written on every start: update it only if older than 60 seconds, so `session.lazy_write` works.
- Optional: read-only session start (`read_and_close`) for views that only read.
- Update the open points in `docs/plans/standard-migration/remaining.md` (they assume requests without session use).

### Step 3 – Templates

- Memoize the compiled file per template within the request (`TemplateLoader`), drop the duplicate `is_file()`.
- Optional production mode without freshness check (`DirectoryTemplateCache`, cache cleared on deploy) (decision 2).
- `SelectorResolver`: fast path for `stdClass`, memoize how a member of a class is read instead of new reflection
  objects per access; `TemplateScopes::get()` without `array_reverse()`.
- `TemplateData::fromReplacements()`: no triple conversion of the data tree (produce `TrustedHtml` directly, or convert
  lazily per identifier).
- `SnippetTag`: resolve the snippets directory once, memoize snippet files.
- `HtmlDocument`: skip the active-navigation regex when the page has no `id="nav-`, keyed lookup instead of
  `in_array()`.
- Validate own template tags against the built-in names instead of building the tag collection twice (known open
  point).

### Step 4 – Autoloader cache

- `Core::fromEnvironment()` registers `actra/autoloader` without cache path, so the cache lands in `vendor/` (read-only
  or with paths of another machine on servers). Pass a cache file in the cache directory of the application.
- Document `opcache.preload` / classmap for production in `docs/setup.md`.
- In `actra/autoloader` (separate release): require cached entries directly, write the cache atomically.

### Step 5 – Database

- `PDO::ATTR_TIMEOUT` (connect timeout, default 3 s, `DbSettings` argument): today 60 s per worker when the database is
  unreachable.
- Streaming: `FrameworkDb::iterateRows()` / `DbSelectStmt::executeAndIterate()` (generator of `DbRow`), build
  `DbRow`s without `fetchAll()` + `array_map()`.
- `CsvFile`: write rows as they are added (or accept an iterable) instead of keeping all rows in memory.
- `DbResultTable`: no `COUNT` when the total is known (`offset + rows` of a page that is not full); memoize
  `TableSessionState` entries; build cell CSS classes once.
- `selectRow()`: fetch at most two rows.
- Optional: statement cache per connection, `insertMany()` helper, maximum size of the query log.
- Correctness (found on the way): `DbQuery` with `SELECT DISTINCT` counts wrongly (reject or count with a subquery).

### Step 6 – External calls

- cURL: separate connect timeout (3 s) and request timeout (10 s), millisecond setters; keep
  `DEFAULT_TIMEOUT_IN_SECONDS`.
- `MicrosoftClientCredentialsTokenProvider`: optional cache across requests (file in the cache directory, key tenant,
  client and scope, lifetime of the token minus 60 s).
- `SystemMailDomainResolver`: memoize per domain, optional cache across requests, port 25 check with 2 s timeout.
- `ReverseDnsServerNameResolver`: resolve once, cache in the cache directory (decision 3).
- `MicrosoftKeySetSource`: timeout 3–5 s.
- `FileLogger`: send the mail of a new issue after the response (`register_shutdown_function()`, runs after
  `fastcgi_finish_request()`), the ticket file stays synchronous.

### Step 7 – CPU in forms, phone and lookups

- `PhoneNumberField`: parse once per request; share one `PhoneMetaDataRepository` (static memo, decision 4);
  `PhoneValidator`: keyed constant of the supported regions.
- `HtmlTag` / `HtmlTagAttribute`: memoize validated names (one `preg_match()` per name instead of per object).
- Keyed lookups instead of `in_array()` in loops: `TldValidator` (1440 entries), `MultiOptionsField::isSelected()`,
  `SearchState::checkMultiFilter()`, `AccessRightCollection`.
- `SearchQueryBuilder`: maximum number of search words (a user can create thousands of `LIKE` conditions), no array
  copies in loops.
- `ContentHandler::hasContent()`: no `trim()` copy of the body per call.
- `HttpRequest`: read the body and parse `Accept-Language` lazily.
- `Route`: build the match pattern once, with `preg_quote()` (bug: `.` in a route path matches any character).
- `SmtpMailer`: write the message in blocks instead of one `fwrite()` per line.

### Step 8 – Opt-in caching of generated pages

- A view that knows the version of its data (e.g. `updated_at` of an article plus the language) answers `304` before
  rendering: ETag and `Last-Modified` from the data, not from the body. Only for pages without session data and CSRF
  tokens.
- The `304` must not send a new CSP nonce header (the browser would replace the stored header and block the scripts
  of the cached page): send the CSP header of the cached page, or no nonce-based CSP for such pages.
- Optional: server-side cache of rendered fragments (key with everything the fragment depends on, lifetime,
  invalidation).

## Handover notes

### Step 1 (v4.59.0) – done

- Generated content (`createHtmlResponse()`, `createResponseFromString()`): `Cache-Control: private, no-store`, no
  ETag, no `Last-Modified`, never `304` (fixes the same-second stale page). The `clock:` argument of both is kept for
  compatibility but unused; `ErrorResponseFactory` still passes it (its own `clock:` is public API).
- Files: ETag `"sha256(mtime-size-path)"` (header key stays `Etag` for `getHeader()`), IMF-fixdate with `GMT`,
  `private|public, max-age=N|no-cache[, immutable]`, `Expires` kept. A `304` has the validators and caching headers
  only. `isNotModified()`: `If-None-Match` list, weak comparison, `*`, precedence; `If-Modified-Since` `>=`.
- `NativeResponseSender::send()`: `session_write_close()` if a session is active, all output buffers cleared for files,
  `fpassthru()` instead of 8 KB chunks with `flush()`.
- Not measured with a benchmark: the step removes work (SHA-256 of every HTML body) and fixes behaviour; the example
  app was checked in the browser (200, `private, no-store`).

### Step 2 (v4.60.0) – done

- The preferred language is remembered after the view by `Core` (`RequestHandler::rememberPreferredLanguage()`, now
  public), only if the session handler was started and is not closed. `resolveRoute()` no longer touches the session.
  Visitors without cookie whose view starts a session now get the language remembered too (before: not).
- Not done, on purpose: throttling `lastActivity` saves nothing, because `AbstractSessionHandler::updateTimestamp()`
  writes the whole session like `write()`. A cheaper `updateTimestamp()` (touch the file) for `FileSessionHandler` and
  a read-only session start (`read_and_close`) remain ideas for later.
- `CoreTest`: both new tests fail on the code before the change (checked with `git stash`).

### Step 3 (v4.61.0) – done

- Measured with a page of 2000 table rows, one `loadSubTpl` per row, three selectors and an `if` per row (benchmark
  script, not committed): render 13.1 ms → 8.1 ms, conversion of the data 1.1 ms → 0.9 ms, same HTML.
- `TemplateLoader` keeps the compiled file per template (one lookup per engine). `DirectoryTemplateCache` has
  `checkTemplateChanges` (`.env.php` key of the same name, `EnvironmentSettings::$checkTemplateChanges`).
- `SelectorResolver`: `stdClass` read directly, other classes with a `ReflectionProperty` / `ReflectionMethod` kept
  per class and name (not for dynamic properties). `TemplateScopes`: innermost scope first, no `array_reverse()`.
- `TemplateData::fromReplacements()` marks the snapshots of `HtmlDataObject` in place instead of copying them.
- `SnippetTag` keeps the real path of the directory and the content of non-template snippets; `HtmlDocument` skips
  the navigation regex without `id="nav-` and looks up the active ids by key.
- Not done: the tag collection that `Core::prepareHttpResponse()` builds only to validate the own tags (a few small
  objects per request, not worth new API).

### Step 4 (v4.62.0) – done

- `Core::fromEnvironment()` resolves the directories first (with `DirectoryPathResolver`, loaded by `require_once`),
  then registers the autoloader with `<cache directory>autoloader.php` (`Core::AUTOLOADER_CACHE_FILE_NAME`). Checked
  in the example app: the cache is written to `example/app/cache/autoloader.php`, the file in `vendor/` stays
  untouched. `fromEnvironment()` stays without unit test (global, once per process).
- Not measured: the change fixes where the cache lives (a read-only `vendor/` or paths of another machine made every
  request scan for its classes); the speed of a warm cache is unchanged.
- Open, in `actra/autoloader` (separate release): write the cache atomically (temporary file and `rename()`; today
  concurrent requests can include a half-written file) and require cached paths directly instead of `file_exists()`
  per class.

### Step 5 (v4.63.0) – done

- Measured on SQLite with 100,000 rows of three columns: `selectRows()` 70.8 ms → 39.2 ms (rows built while fetching,
  no `fetchAll()` + `array_map()`); new `iterateRows()` 28 ms with +0.2 MB peak memory instead of +54.6 MB.
- `DbSettings::$connectTimeoutInSeconds` (default 3) → `PDO::ATTR_TIMEOUT`. `selectRow()` fetches at most two rows
  (new `DbRowCountException::moreThanOneRowWithoutCount()`). `CsvFile` takes `moreRows:` (iterable) in
  `createTemporaryFile()` and `pushDownloadAndExit()`.
- `DbResultTable::getTotalAmount()`: no `COUNT` for a page that is not full (except an empty page after the first,
  which may be beyond the end); tests with `SelectRowCountingDb`.
- `DbQuery::getTotalAmount()` counts a `SELECT DISTINCT` as sub query with the parameters of the select part.
- `actra/autoloader` raised to `~1.2.0` (atomic cache, cached classes included without `file_exists()`).
- Not done (not measured as relevant, optional in the plan): memoizing `TableSessionState`, cell CSS classes per
  column, a statement cache, `insertMany()`, a maximum size of the query log.

### Step 6 (v4.64.0) – done

- New `common\FileCache` (JSON file per key, `sha256` of the key as name, 0600 / directory 0700, atomic write,
  lifetime). Used by `MicrosoftClientCredentialsTokenProvider` (`tokenCache:`, lifetime of the token minus 60 s) and
  `ReverseDnsServerNameResolver` (`cache:`, one day); both opt-in.
- `FileLogger`: the mail of a new issue (and its fallback) runs in a shutdown function (`mailAfterResponse`, default
  `true`); nothing is registered without a recipient.
- Connect timeouts: `AbstractCurlRequest::DEFAULT_CONNECT_TIMEOUT_IN_SECONDS` 3 (request timeout stays 10),
  `GraphMailer` and the token provider 3, `MicrosoftKeySetSource` 5, `SystemMailDomainResolver` port 25 check 2.
- Not measured: the token cache saves one HTTPS request to the identity platform per request that sends mail; there
  is no tenant to measure against.
- Not done: millisecond timeout setters (seconds are enough so far); a per-domain cache of `SystemMailDomainResolver`
  (`ValidatedEmailAddress` and the form rule create their own resolver, so it would need plumbing through the rules).
