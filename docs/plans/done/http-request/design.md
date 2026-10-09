# Design: `HttpRequest` as instance

Step 2 of [docs/plans/done/standard-completion/plan.md](../standard-completion/plan.md). Today `HttpRequest` is a class of static
getters over the superglobals with four static caches (`$inputData`, `$host`, `$protocol`, `$languages`), and
`RequestBody::getData()` caches `php://input` statically. yuf reads the request statically in 12 files and the
superglobals directly in 10 more; projects call the static getters from views, forms, static helpers and `index.php`.

Decisions of the user: an instance created once per request, with `HttpRequest::fromGlobals()` as public boundary
factory; forms get their input explicitly; one release; only explicit input getters (query or post, no merged input).

## 1. `HttpRequest`

`final readonly class HttpRequest` – an immutable snapshot of the request, no access to superglobals after creation.

- `public function __construct(…)` with named arguments for everything below (tests build requests without
  superglobals) and `public static function fromGlobals(): HttpRequest` (reads `$_SERVER`, `$_GET`, `$_POST`,
  `$_COOKIE`, `$_FILES`, the request headers and `php://input` once; validates and narrows the types at this boundary).
  `fromGlobals()` is meant for `Core`, bootstrap files and CLI scripts, not for logic classes (documented).
- Request line and server: `getMethod(): RequestMethodEnum`, `getUri(): string`, `getPath(): string`,
  `getQuery(): string`, `getProtocol(): ProtocolEnum` (new enum `HTTP` / `HTTPS`, same detection as today: `HTTPS`
  server variable, else port 443), `isSsl(): bool`, `getHost(): string` (`HTTP_HOST`, else `SERVER_NAME`, else
  `UnexpectedValueException` in `fromGlobals()`), `getPort(): int`, `getUrl(?ProtocolEnum $protocol = null): string`,
  `getServerName()`, `getServerAddress()` (for the mailer and the upload storage).
- Client: `getRemoteAddress(): string`, `getUserAgent(): string`, `getReferrer(): string`,
  `listBrowserLanguagesByQuality(): list<string>` (computed in `fromGlobals()` or on first call without static state),
  `getBearerToken(): ?string` (was `getBearer(): false|string`), `getHeader(string $name): ?string`,
  `getCookie(string $name): ?string`, conditional request headers for `HttpResponse` (`If-None-Match`,
  `If-Modified-Since`) through `getHeader()`.
- Input, only explicit (⚠️ no merged `$_GET` + `$_POST` anymore):
  `getQueryString()`, `getQueryInteger()`, `getQueryFloat()`, `getQueryArray()`, `hasQueryValue()` and the same for
  post (`getPostString()`, …), with today's semantics (trimmed strings, `null` for a missing or non-scalar value,
  integer/float casting as today – decided against the characterization tests). Files: `getFile(string $name)`,
  `getFiles(string $name)` as today (normalized arrays; typed array shapes). Body: `getBody(): string`.
- Removed (⚠️): every static getter, the static caches, the constants `PROTOCOL_HTTP` / `PROTOCOL_HTTPS` (→
  `ProtocolEnum`), `getInputString/Integer/Float/Array/Value()`, `hasScalarInputValue()`, `getCookies()`,
  `getBearer()`, `RequestBody::getData()` (→ `getBody()`); `RequestBody` itself if nothing else is left in it.
  `SSL_PORT` stays a typed constant.

## 2. Who gets the request

- `Core::__construct()` creates it with `HttpRequest::fromGlobals()` (it needs it for the HTTPS redirect) and keeps it
  in `public readonly HttpRequest $httpRequest`; `DOCUMENT_ROOT` is read there as today (bootstrap, not request data).
- `RequestHandler`, `CspPolicySettings::getHttpHeaderDataString()`, the session start (`AbstractSessionHandler`:
  remote address, user agent, session cookie), `HttpResponse` (redirect URL via `UrlHelper`, conditional requests),
  `Logger` and `ExceptionHandler` (request data in the log and the debug page) get it as argument or constructor
  dependency from `Core`.
- Views: `ViewContext::$httpRequest`. `BaseView` reads the IP address and the input from it.
- Views declare where an input parameter comes from: `InputParameter` gets the required argument
  `InputSourceEnum $source` (`QUERY` / `POST`) (⚠️ every view with input parameters). `BaseView::getInputString()`,
  `getInputInteger()`, `getInputFloat()`, `getInputArray()`, `getInputDomain()` keep their names and read from the
  declared source, so view code that uses them does not change; the required-parameter check uses the declared source.
- Forms: `FormInput::fromGlobals()` is removed (⚠️); new `FormInput::fromHttpRequest(HttpRequest $httpRequest, bool
  $methodPost)`. `Form::validate()`, `isSent()` and `process()` (and the field/upload methods that default to the
  current request today) require the input (⚠️). A view writes
  `$form->process(input: FormInput::fromHttpRequest(httpRequest: $this->context->httpRequest, methodPost: true))`; a
  shorter form (e.g. `Form::process(httpRequest:)` deriving the input from the form's own `methodPost`) is decided in
  the task if it removes repetition without hiding the dependency.
- Tables and search: `DbResultTable` (sorting, paging), `TableFilter` and its fields, `SearchHelper` get the request
  (constructor dependency next to the template engine). `SearchHelper` no longer writes into `$_GET`: it keeps its
  derived state itself.
- Mailer and upload storage: `AbstractMailer` gets the server address, `SessionFileUploadStorage` the server name as
  constructor arguments (or the request) instead of `$_SERVER`.
- Static helpers of projects that need host or IP address receive them from their caller (the view or form passes
  `$this->context->httpRequest->getHost()`); bootstrap files use `$core->httpRequest` (or `HttpRequest::fromGlobals()`
  before `Core` exists).

## 3. Tests

- Characterization first: today's static getters with prepared superglobals (one test class, superglobals restored
  in `tearDown()`; the static caches limit what can be tested – reset only via a fresh process or document it), incl.
  protocol detection, host fallback, browser languages, input trimming and casting, file normalization, bearer token.
- `HttpRequest` built with its constructor in every other test (no superglobals): `RequestHandler`, `BaseView`,
  forms, tables, `SearchHelper`, `CspPolicySettings`, `HttpResponse` conditional requests, `Logger` output. This also
  makes the request pipeline of `Core` testable except `fromGlobals()` itself.
- Hand-written doubles only; a small builder in `tests/Double/` for requests.

## 4. Release (v4.29.0, ⚠️)

One release with an `UPGRADE.md` table "static call → instance call" for every removed getter, before/after for a
view, a form, a table, a static helper and a bootstrap file, and the new `source:` of `InputParameter`.

## 5. Decisions after the characterization tests (step 1)

1. Number input is strict (⚠️): `getQueryInteger()` / `getPostInteger()` return a number only for a real integer
   string (as `PathVars::getAsInt()`), the float getters only for a real numeric string; `'12abc'`, `''`, `'abc'`,
   `'1.5'` (integer), overflow → `null`.
2. `RequestMethodEnum` gets `HEAD` and `OPTIONS`; `fromGlobals()` rejects an unknown method with a specific exception
   (handled like a client error), not with a `ValueError` deep in the code.
3. Bug fixes (no ⚠️, each pinned by a test, listed in `UPGRADE.md`):
    - HTTPS detection: the `HTTPS` server variable decides when present (`on` / `1`, case-insensitive; `off` means
      http); only without it the port 443 counts.
    - Browser languages: all languages in quality order (ties keep their order), no duplicates, no `*` or empty codes,
      spaces around `;q=` allowed, qualities compared without float rounding errors, clamped to 0…1.
    - `getFiles()` normalizes single and multi uploads and returns `[]` for a missing field.
    - Bearer token: header name and scheme case-insensitive, an empty token is `null`, works without
      `getallheaders()` (falls back to `$_SERVER['HTTP_AUTHORIZATION']`).
    - `fromGlobals()` throws `UnexpectedValueException` for a missing `REQUEST_METHOD`, `REQUEST_URI` or host (not a
      web request); a missing `SERVER_PORT` / `QUERY_STRING` is treated as not given (port 0, empty query).

## 6. Decided by me (change if you disagree)

1. `InputParameter` declares its source and `BaseView::getInput*()` keep their names (fewest changes in views).
2. `getBearer(): false|string` becomes `getBearerToken(): ?string` (no `false` as "missing").
3. `getCookies()` (whole array) becomes `getCookie(string $name)`; nothing outside yuf uses `getCookies()`.
4. `listBrowserLanguagesByQuality()` is computed once in `fromGlobals()` (part of the snapshot).
