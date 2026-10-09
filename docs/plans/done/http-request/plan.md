# Plan: `HttpRequest` as instance

Design: [design.md](design.md). Step 2 of [docs/plans/done/standard-completion/plan.md](../standard-completion/plan.md).

## Steps

1. **Characterization tests (no release):** today's static `HttpRequest` getters with prepared superglobals
   (protocol detection, host fallback, port, URI/path/query, browser languages, remote address, user agent, referrer,
   input trimming and casting, arrays, files normalization, bearer token, cookies) and `RequestBody::getData()` where
   possible; superglobals restored in `tearDown()`; limits of the static caches documented.
2. **v4.29.0 – `HttpRequest` as instance (⚠️, done):** design sections 1–4 in one release. The characterization cases are
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

### Step 2 (v4.29.0) – done

`composer check` green, `example/` checked (`/` 200 with "Hello World!", `/index.html` 200, `/nope.html` 404, http -> https
303, `TRACE` 405, 304 for a matching `If-None-Match`). Baseline 525 -> 469 entries, no new entry. 2966 tests, no test needs
a separate process except `HttpRequestGetallheadersTest`.

**`HttpRequest`** (`final readonly`, `src/core/HttpRequest.php`): constructor `__construct(string $host, RequestMethodEnum
$method = GET, string $uri = '/', string $queryString = '', ProtocolEnum $protocol = HTTPS, int $port = 0, string
$serverName = '', string $serverAddress = '', string $remoteAddress = '', array $headers = [], array $cookies = [], array
$queryParameters = [], array $postParameters = [], array $uploadedFiles = [], string $body = '', array $serverVariables
= [])`; `fromGlobals()`; getters of the design (`getMethod`, `getUri`, `getPath`, `getQuery`, `getProtocol`, `isSsl`,
`getHost`, `getPort`, `getUrl`, `getServerName`, `getServerAddress`, `getRemoteAddress`, `getUserAgent`, `getReferrer`,
`listBrowserLanguagesByQuality`, `getHeader`, `getBearerToken`, `getCookie`, `getBody`, `hasQueryValue`/`hasPostValue`,
`getQuery…`/`getPost…` String/Integer/Float/Array, `getFile`, `getFiles`). Additions to the design, all for forms, the
error log and the debug page: `listCookies()`, `getQueryParameters()`, `getPostParameters()`, `getRawFiles()`,
`getServerVariables()`. New: `ProtocolEnum`, `InputSourceEnum`, `UnsupportedRequestMethodException`;
`RequestMethodEnum` + `HEAD`, `OPTIONS`; `InputParameterCollection::findParameter()`; `HttpResponse::isNotModified()`
(public, pure, so the 304 decision is testable). `RequestBody` is deleted, `JsonRequestBody` stands alone.

**Decisions in the task** (not in the design): the headers are one case-insensitive map (user agent, referrer,
`Accept-Language`, bearer, conditional headers come from it; `fromGlobals()` builds it from `HTTP_*`, `CONTENT_*`, then
`getallheaders()` where it exists, then `REDIRECT_HTTP_AUTHORIZATION`); an empty `HTTP_HOST` falls back to
`SERVER_NAME`; `getFile()` returns the normalized entry of a one-file field and `null` for a multi field; a request
method that is unknown is answered with 405 in `Core` (before the exception handler exists); the `Logger` and
`ExceptionHandlerContext` get the request explicitly (not via `Core`); `Authenticator` and `MicrosoftAuthenticator` take it
as first constructor argument (smallest clean way, the subclasses of the projects pass `$core->httpRequest`);
`FileField` requires its `storage` (no default without the request); `AbstractMailer` takes `serverAddress` and
`AbstractMail::send()` its mailer; `SearchHelper::create()` replaces `getInstance()` (no static registry; `valueSource`
is required); the tables read sorting and paging from the query, `TableFilter` fields from the post data;
`UrlHelper::generateAbsoluteUri()` uses the path instead of the URI (bug fix). All in `UPGRADE.md` v4.29.0.

**Remaining superglobal reads in `src/`:** `HttpRequest::fromGlobals()`; `Core` (`$_SERVER['DOCUMENT_ROOT']`, bootstrap
data, not request data); `AbstractSessionHandler::setSessionName()` (`unset($_COOKIE[$sessionName])` removes an invalid
session ID, because `session_start()` reads the ID from `$_COOKIE`; the value itself comes from the request). `$_SESSION`
is the session redesign (step 3). No static call of `HttpRequest` remains except `fromGlobals()` in `Core`.

**Not covered:** `Core::__construct()` (singleton, env file, redirect and 405 end with `exit`), the 304 and redirect
responses themselves (`exit`), `ExceptionHandler::handleException()`, `php://input` in `fromGlobals()` (empty in the CLI;
the body is tested through the constructor), `AbstractMailer::getServerName()` (reverse DNS lookup), `getallheaders()`
only through a stand-in (`tests/Fixture/core/getallheaders.php`, separate processes).

**Open / for later:** `Form::validate()` still needs `FormInput::fromHttpRequest(…, methodPost: …)` with the method
repeated (a shorter `Form::validateRequest(httpRequest:)` was not added, it would hide the dependency); `getQuery()` (raw
string) and `getQueryString(name:)` (one parameter) are easy to mix up; `Logger` still writes cookies and `$_SERVER` to
the log as before (a candidate for a security review); `SmartTable`/`TableFilter`/`AbstractTableFilterField` keep their
static identifier registries (step 3). `src/common/LogFile.php` showed an unstaged removal of `__destruct()` that is not
part of this step (found in the working tree after `composer cs:fix`, left as found).

## Final note (2026-10-09)

Done: `HttpRequest` is an instance since v4.29.0. The plan moved to `docs/plans/done/` (coding standard v1.16.0).
