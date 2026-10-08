# Plan: `HttpRequest` as instance

Design: [design.md](design.md). Step 2 of [docs/standard-completion/plan.md](../standard-completion/plan.md).

## Steps

1. **Characterization tests (no release):** today's static `HttpRequest` getters with prepared superglobals
   (protocol detection, host fallback, port, URI/path/query, browser languages, remote address, user agent, referrer,
   input trimming and casting, arrays, files normalization, bearer token, cookies) and `RequestBody::getData()` where
   possible; superglobals restored in `tearDown()`; limits of the static caches documented.
2. **v4.29.0 – `HttpRequest` as instance (⚠️):** design sections 1–4 in one release. The characterization cases are
   moved to the instance (built with the constructor and with `fromGlobals()`); every user in yuf gets the request
   explicitly; `UPGRADE.md` with the full static → instance table and before/after examples.

## Follow-up in other projects

- Every static `HttpRequest::…()` call becomes an instance call on the request passed in (`$this->context->httpRequest`
  in views, an argument for forms and helpers, `$core->httpRequest` in bootstrap files).
- Views: `InputParameter` needs `source:`; forms pass `FormInput::fromHttpRequest(…)`; tables get the request.

## Handover notes

### Step 1 – done

Tests only, nothing changed in `src/`. 168 test cases (data provider cases counted), `composer check` green.

- `tests/Unit/core/HttpRequestServerTest.php` (45): protocol, host, port, URI/path/query, URL, request method.
- `tests/Unit/core/HttpRequestClientTest.php` (27): remote address, user agent, referrer, browser languages.
- `tests/Unit/core/HttpRequestInputTest.php` (76): merged `$_GET` + `$_POST`, string/integer/float/array/value, scalar check.
- `tests/Unit/core/HttpRequestFilesAndHeadersTest.php` (19): cookies, `getFile()`/`getFiles()`, `getBearer()`.
- `tests/Unit/request/RequestBodyTest.php` (1): only the empty body (see below).
- Helpers: `tests/Fixture/core/getallheaders.php`, `tests/Double/core/StaticRequestHeaders.php`.

**Static caches:** the Server, Client and Input classes run every test in its own process
(`#[RunTestsInSeparateProcesses]`, `#[PreserveGlobalState(false)]`); this works with the bootstrap and the autoloader.
No reflection. Superglobals are prepared per test and restored in `tearDown()`. Tests document that the caches freeze
the first value (`testProtocolIsCachedForTheWholeProcess`, `testHostIsCachedForTheWholeProcess`,
`testBrowserLanguagesAreCachedForTheWholeProcess`, `testInputIsCachedForTheWholeProcess`). Side effect: other tests of
the suite that read `HttpRequest::getHost()` share the cache of the main process (first read wins) – gone with step 2.
`getBearer()`: `getallheaders()` does not exist in the CLI. The bearer tests run in a separate process and load a
namespaced stand-in `actra\yuf\core\getallheaders()` (resolved before the global function), fed through
`StaticRequestHeaders`; one test documents the `Error` without the function.
`RequestBody::getData()`/`php://input` cannot be fed in a test: only the empty body is covered; parsing is covered by
`JsonRequestBodyTest`. In step 2 `getBody()` is a plain string of the instance, so it becomes testable.

**Findings (decide for step 2; design.md deviations marked with D):**

1. `getUri()`, `getQuery()`, `getRequestMethod()`, `getPort()` read `$_SERVER` keys without a check: missing
   `REQUEST_URI`/`QUERY_STRING`/`REQUEST_METHOD`/`SERVER_PORT` is a warning ("Undefined array key"), not an
   exception. `getPort()` returns 0 when `SERVER_PORT` is missing and would therefore not detect SSL. Decide the
   behaviour of `fromGlobals()` for CLI and missing keys (D: design says only host is validated).
2. `getRequestMethod()` throws `ValueError` for `HEAD`, `OPTIONS` and lowercase methods (the enum has no `HEAD` /
   `OPTIONS`). Decide: keep (and where is it caught) or extend the enum.
3. Protocol: `HTTPS` is `on` or `(int) 1` only; `ON` (uppercase) and other values fall through to the port check.
   `HTTPS=off` plus port 443 is `https`. `getUrl(protocol:)` accepts any string (`ftp`).
4. `getHost()` throws a plain `Exception`; design.md wants `UnexpectedValueException`. `HTTP_HOST` is returned as sent,
   including the port and without validation (host header injection risk; the `RequestHandler` has `allowedDomains`).
5. `getPath()`: cuts at the first `?` (character-based `mb_strpos`), returns `''` for an empty URI or `?a=1`.
   Works as expected for encoded `%3F`. No decoding, no normalization.
6. Browser languages: duplicates are kept (`de-CH,de` gives `['de', 'de']`); equal qualities keep the first only
   (`fr,de` gives `['fr']`, the second language is dropped, not just ranked lower); float rounding merges 0.57 and
   0.56 (`(int) (0.57 * 100)` is 56), so `de;q=0.57,en;q=0.56` gives `['de']`; `q=abc` counts as 0, `q=2` sorts first;
   `de, en; q=0.5` (space after `;`) is not recognized (language `'en; q=0.5'` is not even returned: the second part
   becomes quality 1 and loses against `de`); an empty first part (`,de`) wins with quality 1 and yields `['']`;
   `*` is returned as language. The result is a list of codes without region.
7. Input: `array_merge($_GET, $_POST)` renumbers integer-like keys (`?5=x` is found under `'0'`, not `'5'`; GET and POST
   numeric keys collide into 0, 1, …). POST wins also with a different type (`$_POST['k']` array replaces `$_GET['k']`
   string). A cached merge never sees later changes of the superglobals (the cause of the `SearchHelper` workaround
   writing into `$_GET`).
8. Casting: `getInputInteger()` uses `(int)`: `'12abc'` is 12, `''` is 0, `'abc'` is 0, `'1.5'` is 1, `'1e3'` is
   1000, `'1,5'` is 1, overflow saturates to `PHP_INT_MAX`, `true` is 1; a present but empty or non-numeric value is
   indistinguishable from `0`. `getInputFloat()` likewise (`'1,5'` is 1.0). `getInputString()` trims but not
   `getInputValue()`/`getInputArray()` (arrays are returned untrimmed with their keys, nested values unchecked).
   `getInputString(true)` is `'1'`, `false` is `''` (only reachable with prepared superglobals).
   `getInputValue()` has the return type `string|array|null`; a present `null` and a missing key are both `null`.
9. `getFile()` returns the raw `$_FILES` entry (single or multi upload, no normalization); `getFiles()` breaks on a
   single-file field (`TypeError` from `count()`) and on a missing field (warning "array offset on null", then
   `TypeError`); the docblock of `getFile()` is typed `?array` only. D: design says "normalized arrays; typed array
   shapes".
10. `getBearer()`: header name is case sensitive (`authorization` is not found, HTTP header names are not), scheme is
    case sensitive (`bearer abc`), `'Bearer '` gives `''` (empty string, not `false`), the token is trimmed.
    Without `getallheaders()` (CLI, some FastCGI setups) it is an `Error`; `fromGlobals()` must cope with that
    (D: design reads "the request headers" once). PHPStan baseline entries for `getBearer()` exist.
11. `getCookies()` returns `$_COOKIE` as is (also non-string values).
12. `RequestBody::getData()` caches `php://input` statically; `JsonRequestBody` is already instance based.
