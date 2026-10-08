# Upgrade Guide

This document tracks relevant changes and upgrade instructions for developers.

---

## [v4.49.0] – 2026-10-08

`RequestHandler::resolveRoute()` returns its result instead of filling properties. Search your project for
`resolveRoute(`, `processRequest(` and `->fileTitle`, `->fileExtension`, `->fileGroup`, `->routeVariables`,
`->pathVars`, `->route` or `getPathVar(` on a `RequestHandler`.

### ⚠️ `resolveRoute()` returns a `ResolvedRoute`

The new `final readonly` class `actra\yuf\core\ResolvedRoute` holds everything that depends on the route. The
properties `route`, `fileTitle`, `fileExtension`, `fileGroup`, `routeVariables` and `pathVars` of `RequestHandler` are
removed (before the call they were uninitialized and threw an `Error` when read). `fileName` and `language` stay on
`RequestHandler` as the state of the request so far (the exception handler needs them for error pages if the route
cannot be resolved), and `ResolvedRoute` has the final values.

| Before | After |
|:--|:--|
| `$requestHandler->resolveRoute();` then `$requestHandler->route` | `$resolvedRoute = $requestHandler->resolveRoute();` then `$resolvedRoute->route` |
| `$requestHandler->fileTitle` / `->fileExtension` / `->fileGroup` | `$resolvedRoute->fileTitle` / `->fileExtension` / `->fileGroup` |
| `$requestHandler->routeVariables` / `->pathVars` | `$resolvedRoute->routeVariables` / `->pathVars` |
| `$requestHandler->fileName` after `resolveRoute()` | `$resolvedRoute->fileName` (`$requestHandler->fileName` is the same value) |
| `$requestHandler->language` after `resolveRoute()` | `$resolvedRoute->language` (`$requestHandler->language` is the same value) |
| `$requestHandler->getPathVar(nr: 1)` | `new PathVars(values: $resolvedRoute->pathVars)->get(nr: 1)` |

### ⚠️ `ContentHandler::processRequest()` takes the `ResolvedRoute`

| Before | After |
|:--|:--|
| `processRequest(requestHandler: $requestHandler, localeHandler: …)` | `processRequest(resolvedRoute: $resolvedRoute, localeHandler: …)` |

Only `Core` calls it. Views get the same values as before through `ViewContext` (`route`, `fileGroup`, `fileTitle`,
`pathVars`) and `BaseView::getPathVar()`; nothing changes for them.

---

## [v4.48.0] – 2026-10-08

`SearchHelper` had two purposes and is split. Search your project for `SearchHelper`.

### ⚠️ `SearchHelper` is replaced by `SearchQueryBuilder` and `SearchState`

`SearchHelper` is removed without an alias. The pure SQL builders moved to the new `final` class
`actra\yuf\common\SearchQueryBuilder`, the stored search state of a search form to `actra\yuf\common\SearchState`.
Behaviour, arguments and results are unchanged; replace the class name and the `use` statement:

| Before | After |
|:--|:--|
| `SearchHelper::createSqlFilters(` | `SearchQueryBuilder::createSqlFilters(` |
| `SearchHelper::createBooleanQuery(` | `SearchQueryBuilder::createBooleanQuery(` |
| `$searchHelper->createSqlSearch(string:, columns:)` (instance method) | `SearchQueryBuilder::createSqlSearch(string:, columns:)` (static, no instance needed) |
| `SearchHelper::create(instanceName:, httpRequest:, valueSource:, session:)` | `SearchState::create(instanceName:, httpRequest:, valueSource:, session:)` |
| `SearchHelper::getInstance(instanceName:)` (removed in an earlier release) | `SearchState::create(instanceName:, httpRequest:, valueSource:, session:)` |
| `SearchHelper::PARAM_RESET`, `SearchHelper::PARAM_FIND` | `SearchState::PARAM_RESET`, `SearchState::PARAM_FIND` |
| `$searchHelper->checkSearchTerm()`, `checkString()`, `checkFilter()`, `checkMultiFilter()`, `checkDateRangeFilter()` | the same methods on `SearchState` |
| `$searchHelper->checkDate(date:)` (instance method) | `SearchState::checkDate(date:)` (static) |
| Type `SearchHelper` (properties, parameters, return types) | `SearchState` |

`createSqlSearch()` and `checkDate()` used no instance state, so they are static now. Calls through an instance
(`$helper->checkDate(...)`) still work in PHP, but static analysis reports them; use the class name.

The session section is unchanged (`SessionSectionEnum::SEARCH`, `yuf.search.<instance name>`, same keys), so stored
search states keep working across the update.

---

## [v4.47.0] – 2026-10-08

A visitor without a session no longer gets one just because a route has a language. Search your project for
`implements SessionStorage`, `preferredLanguage` and `isDefaultForLanguage`.

### ⚠️ The preferred language needs an active session

| Before | After |
|:--|:--|
| Every request of a route with a language started the session (lock, `Set-Cookie`, session file) to remember the language | The language is only remembered if the session is active: started in this request, or the request carries a session cookie with a valid ID (`Session::isActive()`). A visitor without a session cookie starts none |
| The request of `/` read the preferred language from the (started) session | It reads it only from an active session; without one the first browser language with a default route is used, else the first default route |

Consequence: a first-time visitor who only calls language routes has no preferred language, so a later request of `/`
follows the browser language again. Once the visitor has a session for another reason (login, a form with CSRF
protection, own data), the language of the next language route is remembered as before. Check the ID of the cookie
only: whether a session with that ID still exists is known after the start (an expired or unknown ID counts as active
and is replaced by a new session on the first access).

### ⚠️ `SessionStorage::isActive()`

Own implementations of `SessionStorage` need `public function isActive(): bool` (started in this request or a valid
session cookie in the request; it must not start the session). `ArraySessionStorage` has the constructor argument
`active: true` (default); `active: false` simulates a visitor without a session.

### New: `Session::isActive()` and `AbstractSessionHandler::isActive()`

Ask whether the visitor has a session without creating one: `$this->context->session?->isActive()`. The check reads the
cookie of the request (`SessionSettings::$individualName`, else PHP's default name) and starts nothing.

---

## [v4.46.0] – 2026-10-08

The session starts on first use and its lock is released early, so responses are faster and parallel requests of one
user do not wait for each other. Search your project for `$_SESSION`, `register_shutdown_function(`, `__destruct(`,
`implements SessionStorage`, `extends AbstractSessionHandler` and `session_start(`.

### ⚠️ The session starts on first use

| Before | After |
|:--|:--|
| `Core::prepareHttpResponse()` started the session (`session_start()`, lock, cookie, session file) for every request | The session starts with its first access (`Session`, `NativeSessionStorage`, `AbstractSessionHandler::ensureStarted()`); a request that never uses it takes no lock, sends no session cookie and creates no file |
| `new FileSessionHandler(…)` started the session in its constructor | The constructor starts nothing; new public methods `ensureStarted()` (idempotent), `isStarted()`, `isClosed()` and `writeClose()` |
| Every HTML page and every error page read the CSRF token, so the session was always started | The `csrfField` of the page template and of the error pages is built when a template uses it |
| `changeCookieSameSiteToLax()` / `changeCookieSameSiteToNone()` always (re)started the session | They do nothing if the session was not started in this request (no cookie is sent, so none can be changed); they throw a `LogicException` if the session is closed |
| A session start after the response was sent: PHP warning (`PhpException`) | `LogicException` ("The session cannot be started after the response was sent …") |

Still read on every request: a route with a language remembers it as the preferred language (`RequestHandler`), so the
session starts for such a route; and a request of `/` reads it to find the route of the user. Code that needs the
session before the first byte of output is sent (e.g. `session_start()` of your own) has to use
`$core->sessionHandler?->ensureStarted()`; do not call `session_start()` yourself.

Project handlers (`extends AbstractSessionHandler`) get the lazy start for free; their constructor must not rely on a
started session.

### ⚠️ The session is closed after the view

| Before | After |
|:--|:--|
| The session was written at the end of the script (after `sendAndExit()`, destructors and shutdown functions) and kept its lock until then | `Core::prepareHttpResponse()` writes and closes a started session after the view, before it builds the response (`AbstractSessionHandler::writeClose()`) |
| Writes after the view (destructors, shutdown functions, code after `prepareHttpResponse()`) were saved | They throw a `LogicException` ("The session is closed: it cannot be changed any more."): `Session::set()`, `remove()`, `regenerateId()`, `clearUserData()`, `NativeSessionStorage::replaceAll()` and everything built on them (login, CSRF token, table and search state). Reads still work from the data of the closed session |
| Starting the session again was not a topic | Not supported: a session that was closed before its first use throws a `LogicException` on the first access |

Migration: write the session while the view runs. Search for `$_SESSION` / `Session` use in `register_shutdown_function(`
callbacks and `__destruct(` methods, in code that runs after `prepareHttpResponse()` and in log or statistics code that
runs after the response; move it into the view or keep the data without the session.

### ⚠️ New method `SessionStorage::close()`

Own implementations of `SessionStorage` (tests, scripts, other stores) must implement `public function close(): void`:
write what the storage holds and release it; afterwards `set()`, `remove()`, `replaceAll()` and `regenerateId()` must
throw a `LogicException`, reads keep working, a second call does nothing. `ArraySessionStorage` does exactly this, so
tests behave like production.

### New: `Session::close()`

A view that runs long (export, report) calls `$this->context->session?->close()` after its last session access, so
the lock is released before the long work and parallel requests of the user do not wait. `Core` closes the session after
the view anyway; `close()` is only for releasing it earlier. After `close()` the session can be read, not written.

### Template internals

`TrustedHtml` (`@internal`) accepts a function that builds the HTML when a template reads the value, and is not
`readonly` any more. `HtmlReplacementCollection::addLazyHtml()` adds such a value. `ErrorPageValues::$csrfFieldHtml`
(`@internal`) is a function now. An error page whose CSRF field cannot be built (e.g. the session cannot be started)
is shown without the field, and the debug page shows the failed session export as text.

---

## [v4.45.0] – 2026-10-08

`Core` is created from explicit settings, so it can be tested; the global part moved to `Core::fromEnvironment()`.
Search your project for `new Core(`, `processRequest(`, `register_shutdown_function(` and `__destruct(`.

### ⚠️ `new Core(` becomes `Core::fromEnvironment(`

| Before | After |
|:--|:--|
| `new Core(envFilePath: …, copyrightYear: …, autoloaderPath: …, baseDirectory: …, …)` | `Core::fromEnvironment(envFilePath: …, copyrightYear: …, autoloaderPath: …, baseDirectory: …, …)`: the same arguments with the same defaults |
| `new Core(…)` registered the autoloader and the error handler, read the environment file and created the request | the same, in `fromEnvironment()`; it can be called once per process (a second call throws `LogicException`) |
| `new Core(…)` for tests: not possible | `new Core(CoreSettings $settings, HttpRequest $httpRequest, ResponseSender $responseSender = new NativeResponseSender())`: touches no globals and no files, so tests build it from a temporary directory and a request double |

Migration: change the `new Core(` line of your front controller (`public/index.php`); everything else stays
(`$core->viewDirectory`, `$core->environmentSettings`, `$core->availableLanguages`, `prepareHttpResponse()`, …).
`$core->baseDirectory` and `$core->appDirectory` are `readonly` (they were `private(set)`). New class `CoreSettings`
(the environment settings, the resolved directories and the copyright year), which `fromEnvironment()` creates. The
directories are only checked and created in `fromEnvironment()`.

### ⚠️ The HTTPS redirect and the 405 response are responses

| Before | After |
|:--|:--|
| `new Core(…)` redirected a request without HTTPS (303) and ended the script | `prepareHttpResponse()` returns the redirect response (303, `Location` is the HTTPS URL) before it does anything else (no exception handler, no session, no route needed); send it as usual with `sendAndExit()` |
| A request method that yuf does not support ended the script with `header()` and `exit` | `Core::fromEnvironment()` sends a response with the status 405 and no content through the `NativeResponseSender` |

New: `HttpResponse::createStatusResponse(HttpStatusCodeEnum)` is public (a response with only a status).
Code between `new Core(` and `prepareHttpResponse()` now also runs for a request without HTTPS.

### ⚠️ `ContentHandler::processRequest()` no longer takes `Core`

| Before | After |
|:--|:--|
| `processRequest(RequestHandler, LocaleHandler, Core $core, TemplateEngine, ResponseSender $responseSender = new NativeResponseSender())` | `processRequest(RequestHandler, LocaleHandler, TemplateEngine, HttpRequest $httpRequest, ?Session $session, ?AbstractSessionHandler $sessionHandler, FormContext $formContext, string $copyright, string $robots, ResponseSender $responseSender)`: all arguments are required, there is no default sender |

`Core::prepareHttpResponse()` calls it; call it yourself only in tests.

### Performance: `fastcgi_finish_request()`

`NativeResponseSender::send()` calls `fastcgi_finish_request()` (if PHP-FPM provides it) after the output and before
`exit`: the client has the whole response before the session is written and the destructors and shutdown functions
run. Nothing can be output after sending a response. Check your `register_shutdown_function()` callbacks and
destructors: what they print is not sent anymore. See "Production settings" in the README (`opcache`).

### Bug fix: output of a failed view

When a view threw (e.g. a `NotFoundException` for a missing content file), `ContentHandler::processRequest()` left the
output buffer of the view open, so partial output of the view could be sent before the error page. The buffer is
discarded now.

---

## [v4.44.0] – 2026-10-08

Responses are sent through the new interface `ResponseSender`, so sending, redirects and the exception handler can be
tested. Search your project for `sendAndExit(`, `redirectAndExit(`, `createResponseFromFilePath(`,
`pushDownloadAndExit(`, `->output(`, `redirectToMicrosoftLogin(`, `new ViewContext(`, `new ExceptionHandlerContext(`,
`new RequestHandler(`, `new ContentHandler(` and `processRequest(`.

### ⚠️ Sending ends with `never`, a `ResponseSender` can be passed

| Before | After |
|:--|:--|
| `HttpResponse::sendAndExit(): void` | `sendAndExit(ResponseSender $responseSender = new NativeResponseSender()): never` |
| `HttpResponse::redirectAndExit(…, ?AbstractSessionHandler $sameSiteLaxSessionHandler = null): void` | `…, ResponseSender $responseSender = new NativeResponseSender()): never` |
| `CsvFile::pushDownloadAndExit(HttpRequest $httpRequest): void`, `FileHandler::output(HttpRequest $httpRequest, bool $forceDownload = false): void` | the same plus an optional last argument `ResponseSender $responseSender = new NativeResponseSender()`; return type `never` |
| `MicrosoftAuthenticator::redirectToMicrosoftLogin(…): void` (protected) | optional last argument `ResponseSender $responseSender = new NativeResponseSender()`; return type `never` |
| `ExceptionHandler::handleException(Throwable): void` | `never`; it sends through `ExceptionHandlerContext::$responseSender` |
| `new ExceptionHandlerContext(…)`, `new ViewContext(…)`, `new RequestHandler(…)`, `ContentHandler::processRequest(…)` | each has a new optional last argument `ResponseSender $responseSender = new NativeResponseSender()`; `ViewContext::$responseSender` is what `BaseView` sends with |

Nothing changes for code that calls these methods without the new argument, except that a method that returns `never`
makes the code after it unreachable (PHPStan reports it, remove such code). Own implementations of `ResponseSender`
(tests: record the response and throw) are new; the native sender prints exactly what `sendAndExit()` printed before
(status line, headers, the string or the file in chunks, nothing for a 304).

### ⚠️ `HttpResponse::createResponseFromFilePath()` no longer ends the script

| Before | After |
|:--|:--|
| A missing file (or a directory) sent `404` with `header()` and ended the script, an unreadable file sent `403` the same way | The method returns a response with the status `404` or `403` (no headers, no content, so only the status line is sent, as before) |

Migration: nothing, if you send the result with `sendAndExit()` (as `FileHandler::output()` and
`CsvFile::pushDownloadAndExit()` do). If you used `createResponseFromFilePath()` only to read headers, check
`$httpResponse->httpStatusCode` first.

### New: `HttpResponse::createRedirectResponse()`, read access for senders

`HttpResponse::createRedirectResponse(string $relativeOrAbsoluteUri, HttpRequest $httpRequest, HttpStatusCodeEnum
$httpStatusCode = HTTP_SEE_OTHER)` returns the redirect as a response (status and the absolute `Location` header, no
other header, no content); `redirectAndExit()` is the Lax change of the session handler plus this response sent.
`HttpResponse::getContentFilePath()` returns the path of a file response (for `ResponseSender` implementations).

---

## [v4.43.0] – 2026-10-08

`FileField` checks type and size of every upload before it is stored. Search your project for `new FileField(`,
`implements FileUploadStorage`, `->store(`, `UploadedFile` (the `type`) and tests or CSS that rely on the exact markup
of the file input.

### ⚠️ `FileField`: `allowedFileTypes` is required, `maxFileSize` is new

| Before | After |
|:--|:--|
| `new FileField(name: 'cv', label: $label, storage: $storage)` accepted every file | `new FileField(name: 'cv', label: $label, storage: $storage, allowedFileTypes: [UploadFileType::pdf()])`; the argument is required (a non-empty list of `UploadFileType`, otherwise `InvalidArgumentException`) |
| No size check (only `upload_max_filesize` of PHP) | `maxFileSize:` in bytes, default `10 * 1024 * 1024`, must be more than 0 (otherwise `InvalidArgumentException`); a larger file is rejected with `FormMessages::fileExceedsMaxSize` |
| The type was what the browser sent | The type is detected from the file content (`finfo`, `FileTypeDetector`); an upload is accepted if the detected MIME type and the extension of the file name (lower case, after the last dot; no extension = rejected) belong to the same `UploadFileType`. Rejected: `FormMessages::fileTypeNotAllowed` |

`UploadFileType` has the named constructors `pdf()`, `jpeg()` (`jpg`, `jpeg`), `png()`, `gif()`, `webp()`,
`plainText()` (`txt`), `csv()` (`text/csv` or `text/plain`, libmagic reports either), `docx()`, `xlsx()`, `pptx()` (the
Office type or `application/zip`, if the container is not recognised) and `zip()`. There is none for SVG (scripts, XSS
risk when the file is served again); build other types with `new UploadFileType(mimeTypes: [...], extensions: [...])`
(lower case, extensions without dot).

Migration: pass the types the field really needs. `ext-fileinfo` is a requirement of the library now. The new messages
(`fileExceedsMaxSize` with the placeholder `[maxSize]`, e.g. "10 MB"; `fileTypeNotAllowed`) are in `FormMessages` and
`FormMessages::german()`; the file name is appended like for the other file errors. `FileField` takes an optional
`fileTypeDetector:` (`FileTypeDetector`, default `FinfoFileTypeDetector`) for tests.

### ⚠️ `UploadedFile::$type` is the detected type

| Before | After |
|:--|:--|
| `UploadedFile::$type` was the MIME type the browser sent | `UploadedFile::$type` is the MIME type detected from the content (e.g. `application/pdf`) |

### ⚠️ `FileUploadStorage::store()` has a new argument

| Before | After |
|:--|:--|
| `store(string $pointer, UploadInput $upload): ?UploadedFile` | `store(string $pointer, UploadInput $upload, string $detectedType): ?UploadedFile` |

Migration: own storages (and test doubles) add the argument and use `$detectedType` as the `type` of the returned
`UploadedFile` instead of `$upload->type`. `SessionFileUploadStorage` does that and still names the stored file after
the temporary name PHP gave to it.

### ⚠️ `FileFieldRenderer`: `accept` attribute

| Before | After |
|:--|:--|
| `<input type="file" name="file[]" id="file">` | `<input type="file" name="file[]" id="file" accept=".pdf,.jpg,.jpeg">` (the extensions of all allowed types, each once, after `id`) |

The attribute only narrows the file dialog of the browser; the check happens on the server.

---

## [v4.42.0] – 2026-10-08

`ErrorHandler` respects `error_reporting()`, and `defaultErrorReporting` in the environment file is optional. Search
your project for `@` operators, `error_reporting(` and `defaultErrorReporting`.

### ⚠️ PHP errors: the level counts

| Before | After |
|:--|:--|
| Every PHP error threw a `PhpException`, regardless of `error_reporting()` and of the `@` operator | Only errors that `error_reporting()` includes throw a `PhpException` (deprecations too); other errors, also those silenced with `@`, are left to PHP's standard handling |
| `ErrorHandler::handlePhpError()` returned `never` | `ErrorHandler::handlePhpError()` returns `bool`: `false` for an unreported level, never `true` |
| `defaultErrorReporting` was required | `defaultErrorReporting` is optional, default `E_ALL` (an invalid type still throws) |

Migration: nothing to do for projects that report `E_ALL` and do not use `@`. Code that relied on `@` throwing anyway
must check the result itself (e.g. `file_get_contents()` returns `false`). A lower `defaultErrorReporting`, e.g.
`E_ALL & ~E_DEPRECATED`, now silences those levels instead of throwing; remove the key to report everything.

---

## [v4.41.0] – 2026-10-08

Area release for `src/form/` (the last step of the standard completion plan): `HtmlTagAttribute` gets named
constructors like `HtmlText`, the form classes nobody extends are `final`, the extension points are documented, and
plain text values of the form markup (placeholders, names, ids, option keys, CSS classes, links) are escaped. The
rendered HTML is byte-identical for normal input. Search your project for `new HtmlTagAttribute`,
`valueIsEncodedForRendering`, `extends ToggleField`, `extends MultiToggleField`, `extends MultiSelectOptionsField`,
`extends FormInfo`, `extends FormSubHeadline`, `extends NullField`, `extends .*Rule` (of a concrete rule),
`extends .*Renderer` (of a concrete renderer), `FormSubHeadline(`, `->getField(` / `->removeField(` in `catch` blocks
and placeholders, CSS classes or cancel links that contain `&amp;` or other entities.

### ⚠️ `HtmlTagAttribute`: named constructors instead of `valueIsEncodedForRendering`

The public constructor with the flag `valueIsEncodedForRendering` is gone: whether the value is plain text or HTML is
the name of the method, like `HtmlText::fromText()` / `fromHtml()`.

| Before | After |
|:--|:--|
| `new HtmlTagAttribute(name: 'title', value: $text, valueIsEncodedForRendering: false)` | `HtmlTagAttribute::fromText(name: 'title', text: $text)` (escaped when rendered; `string\|int`) |
| `new HtmlTagAttribute(name: 'title', value: $encoded, valueIsEncodedForRendering: true)` | `HtmlTagAttribute::fromHtml(name: 'title', html: $encoded)` (output as it is; a `"` in the value is still rejected) |
| `new HtmlTagAttribute(name: 'disabled', value: null, valueIsEncodedForRendering: true)` | `HtmlTagAttribute::fromName(name: 'disabled')` (no value) |
| `new HtmlTagAttribute('class', 'a', true)` (positional) | `HtmlTagAttribute::fromText(name: 'class', text: 'a')` |

Migration: `valueIsEncodedForRendering: false` -> `fromText()`; `true` with a value that is encoded already (the result
of `HtmlEncoder::encode()`, `FormField::renderValue()`) or trusted markup -> `fromHtml()`; `true` with a plain text
that happened to need no escaping (class names, `type`, ids) -> `fromText()` (the output is the same, and it stays
right when the text changes); `null` -> `fromName()`. `fromText()` takes no `null`: use `fromName()` for an attribute
without a value. The public property `HtmlTagAttribute::$value` (`string|int|null`) is unchanged.

### ⚠️ Plain text values of the form markup are escaped

The renderers passed their values with `valueIsEncodedForRendering: true`, so a plain text with `&` or `<` was output
as it was and one with `"` threw an `InvalidArgumentException` when the form was rendered. The renderers use
`fromText()` for everything that is plain text now: field `name` and `id`, option keys, `placeholder`, CSS classes
(`Form::addCssClass()`, `cssClasses`, `addListTagClass()`, `addCssClassForRenderer()`, the classes of `FormInfo`),
`addDataAttribute()` values, `FormControl::$cancelLink` and the `action` of the form (the sent indicator). Only
`renderValue()` (encoded by the field) is output as HTML. Nothing changes for values without special characters. A
value that you encoded yourself is encoded twice now:

| Before | After |
|:--|:--|
| `placeholder: 'Name &amp; Vorname'` | `placeholder: 'Name & Vorname'` |
| `cancelLink: '/list?a=1&amp;b=2'` | `cancelLink: '/list?a=1&b=2'` (rendered as `href="/list?a=1&amp;b=2"`) |
| a CSS class or data attribute with `&amp;` | the plain text |

The cancel text of a `FormControl` without a `cancelLabel` is `FormMessages::$cancel` as plain text (as documented),
it was output as HTML.

### ⚠️ `final` classes and extension points (form)

Everything that no project extends is `final`: `ToggleField`, `MultiToggleField`, `MultiSelectOptionsField`,
`NullField`, `FormInfo`, `FormSubHeadline`, the rules `MinLengthRule`, `MaxLengthRule`, `RegexRule`, `ValidValueRule`,
`ValidEmailAddressRule`, `MinCountRule`, `MaxCountRule`, `IntegerMinRule`, `IntegerMaxRule`, `FloatMinRule`,
`FloatMaxRule`, `DecimalMinRule`, `DecimalMaxRule` and all renderers except `InputFieldRenderer` and the abstract
`DefaultOptionsRenderer` (`BooleanFieldListRenderer`, `CheckboxItemRenderer`, `CheckboxOptionsRenderer`,
`DefaultCollectionRenderer`, `DefaultComponentRenderer`, `DefaultFormRenderer`, `DefinitionListRenderer`,
`FileFieldRenderer`, `FormControlRenderer`, `FormInfoRenderer`, `HiddenFieldRenderer`, `LegendAndListRenderer`,
`NumericFieldRenderer`, `RadioOptionsRenderer`, `SelectOptionsRenderer`, `TextAreaRenderer`, `ToggleFieldRenderer`).
Nothing in the Actra projects extends them: a project that does, writes its own rule on `StringRule` /
`IntegerRule` / ..., its own renderer on `FormRenderer` and sets it with `setRenderer()`, or uses the rule inside its
own rule. `FileField`, `DateField` and `FormOptions` were already `final`.

Documented extension points (PHPDoc "Extension point: ..."): `Form`, `TextField`, `TextAreaField`,
`SelectOptionsField`, `CheckboxOptionsField`, `RadioOptionsField`, `BooleanField`, `IntegerField` (parent of
`NumericField`), `FormControl` and `InputFieldRenderer` (parent of `NumericFieldRenderer`); abstract bases stay abstract
(`FormComponent`, `FormField`, `FormCollection`, `FormRenderer`, `FormRule`, the typed rule bases, `TextualField`,
`OptionsField`, `FormFieldListener`). New `@internal`: `ToggleChildren`, `BooleanFieldListRenderer`,
`CheckboxItemRenderer`, `HiddenFieldRenderer`, `NumericFieldRenderer`, `ToggleFieldRenderer`. `FormSubHeadline` and
`FormInfoRenderer` / `DefinitionListRenderer` have `readonly` properties.

### ⚠️ `Form::getField()`, `Form::removeField()`: `LogicException`, `FormSubHeadline`: level 1 to 6

`getField()` of a component that is no `FormField`, and `removeField()` of a name that is no field, threw a plain
`Exception` (`The requested component x is not an instance of FormField`); they throw a `LogicException` naming the
form and the component. `catch (Exception)` still catches it. `new FormSubHeadline(headingLevel: 0 or 7, ...)` threw
nothing and rendered `<h7>`: it throws an `InvalidArgumentException` for a level outside 1 to 6.

### Fixed

- `FileFieldRenderer`: the name of the remove button of an uploaded file was output without encoding (the field name
  is chosen by the project, but a name with `"` made broken HTML).
- Plain text with special characters in the attributes of the form markup, see above.

## [v4.40.0] – 2026-10-08

Area release for `src/exception/` and `src/datacheck/`: the exception handler can be tested without `exit`, production
error output shows fixed texts only, the debug page escapes everything, the IP whitelist fails closed, and every
PHPStan baseline entry of both areas is gone (the baseline is empty now). Search your project for `IpTypeEnum::`,
`extends ExceptionHandler`, `->getContext()->core`, `new ExceptionHandlerContext`, `sendNotFoundHttpResponseAndExit`,
`sendHttpResponseAndExit`, `extends NotFoundException`, `extends PhpException`, `new DomainValidator`,
`Sanitizer::trimmedString`, `Validator::ipv4` and error messages that your API clients read.

### ⚠️ Production errors show fixed texts

`NotFoundException` and `UnauthorizedException` messages (`Unknown key ID`, `Path variable 1 is missing`, ...) and the
codes of other exceptions (SQLSTATE codes) were part of the JSON / text answer in production. Production now answers
with the text and the status code of the kind of error; the message stays in the debug answer. Error pages (HTML)
never showed the message. A missing error page file showed its path in production (`Missing error html file /var/...`):
now it shows the short text and logs the path (debug mode still shows it).

| Before (production, JSON) | After |
|:--|:--|
| `{"error":{"code":0,"message":"Internal Server Error"}}` (code of the exception, `HY000` for PDO) | `"code":500` |
| `{"error":{"code":404,"message":"Path variable 1 is missing"}}` | `"message":"Not Found"` |
| `{"error":{"code":401,"message":"Unknown key ID"}}` | `"message":"Unauthorized"` |

Values that a project added to its `htmlReplacementCollection` are no longer part of the JSON `data` (only the debug
details are).

### ⚠️ `ExceptionHandler`: hooks return a response

`handleException()` sends `createResponse()` and ends the script; the four hooks create the response instead of
sending it. `$contentType` and `$registeredInstance` are gone (a second `register()` is recognised through PHP's own
exception handler). Nobody in the Actra projects extends the handler.

| Before | After |
|:--|:--|
| `protected function sendDefaultHttpResponseAndExit(Throwable $t): void` (also `...NotFound...`, `...Unauthorized...`, `sendDebugHttpResponseAndExit`) | `protected function createDefaultResponse(Throwable $t): HttpResponse` (`createNotFoundResponse()`, `createUnauthorizedResponse()`, `createDebugResponse()`) |
| `final protected function sendHttpResponseAndExit(HttpStatusCodeEnum, string, string\|int, string $htmlFileName): void` | `final protected function createErrorResponse(..., array $additionalInfo = []): HttpResponse` |
| `$this->contentType` | `$this->getContentType()` |
| `$this->getContext()->core` | `getContext()->errorDocsDirectory`, `->copyright`, `->availableLanguages`, `->createTemplateEngine` |
| `new ExceptionHandlerContext(logger:, cspNonce:, cspPolicySettings:, isDebug:, core:, httpRequest:)` | `new ExceptionHandlerContext(logger:, cspNonce:, cspPolicySettings:, isDebug:, httpRequest:, errorDocsDirectory:, copyright:, availableLanguages:, createTemplateEngine:)` (only `Core` creates it) |

New: `createResponse(Throwable): HttpResponse` (public), `HttpResponse::getContentString()`. Error pages: `requestedFileName`,
`language`, `langRoot`, `copyright`, `cspNonce`, `charset`, `robots` are escaped text now (they were HTML); `csrfField`
is still HTML. The debug page values (`errorMessage`, `errorFile`, `backtrace`, `vardump_*`) are escaped text and plain
in the JSON `data` (the dumps were HTML-entity encoded there).

### ⚠️ `final` classes and extension points (exception)

`NotFoundException` and `PhpException` are `final`. `ExceptionHandler` and `UnauthorizedException` (extended by
`UnauthorizedAccessRightException` and `UnauthorizedIpAddressException`) are documented extension points.
`UnauthorizedException::__construct()` takes `string $message`. New `@internal` helpers: `ErrorKindEnum`,
`ErrorOutputFormatEnum`, `ErrorPageRenderer`, `ErrorPageValues`, `ErrorResponseFactory`, `ExceptionDebugInfo`.

### ⚠️ `IpTypeEnum`: cases in upper case

| Before | After |
|:--|:--|
| `IpTypeEnum::ip` / `::ipv4` / `::ipv6` | `IpTypeEnum::IP` / `::IPV4` / `::IPV6` |

### ⚠️ `datacheck`: types and `final`

`Sanitizer`, `Validator`, `IpValidator`, `DomainValidator`, `TldValidator` and the other validators / sanitizers are
`final` (static, pure functions without state). The arguments are typed.

| Before | After |
|:--|:--|
| `Sanitizer::domain($input)`, `::integer($input)`, `::float($input)` (untyped) | `string`, `float\|int\|string`, `float\|int\|string` |
| `Validator::ipv4(mixed $input)`, `::ipv6(mixed $input)` | `string $input` |
| `class X extends IpValidator` / `DomainValidator` / `TldValidator` | not possible (`final`) |

### ⚠️ `IpValidator::isInWhitelist()` fails closed

An address that is no valid IP address is never in the whitelist. Before, `isInWhitelist([''], '')`,
`isInWhitelist(['garbage'], 'garbage')` and `isInWhitelist([' 10.0.0.1'], ' 10.0.0.1')` were `true` (the entries were
compared as text first). A missing `REMOTE_ADDR` together with an empty entry in a whitelist was a way in.

### ⚠️ `DomainSanitizer` reduces the input to the host

`Sanitizer::domain()` (and `BaseView::getInputDomain()`) removed the scheme, `www.`, spaces, zero-width characters,
`?` and one trailing slash, and kept the rest. It keeps only the host now: user info (only after a scheme), port, path,
query and fragment are dropped. Without a scheme `user@example.com` is kept as typed (it is no URL; `DomainValidator`
rejects it). `www.ch` stays `www.ch`.

| Before | After |
|:--|:--|
| `example.com?x=1` -> `example.comx=1` | `example.com` |
| `example.com///` -> `example.com//` | `example.com` |
| `example.com/path` -> `example.com/path` | `example.com` |
| `example.com:8080` -> `example.com:8080` | `example.com` |
| `https://user:pw@www.example.com:8080/a/b/` -> `user:pw@example.com:8080/a/b` | `example.com` |

### Fixed

- TLD list updated to IANA version 2026100800 (2026-10-08): `MERCK` and `WEB` added, `BENTLEY`, `DUNLOP`, `GOO`,
  `JUNIPER`, `KERRYLOGISTICS`, `LANCASTER`, `LIPSY`, `PRAMERICA`, `REDSTONE` and `WOLTERSKLUWER` removed (no longer
  delegated).
- `FloatSanitizer`: `'-5'` and `'-100'` gave `0.0`, `'0123'` and `'05'` gave `0.0` (only the first character was
  checked); `''` raised a PHP warning; `'1E400'` and a string of 400 digits gave `INF`; `'-'`, `'.'`, `','`, `'E5'`,
  `'1E'` and `'1E-'` were accepted. All are a `RuntimeException` now except the first group, which are the numbers
  they look like. `IntegerSanitizer`: `'1e400'` says `Value is not suitable as INT.` (was out of range); the float
  `2^63` (`9.2233720368547758E18`) was converted to `PHP_INT_MIN` with a warning, it is out of range now.
- `Sanitizer::trimmedString()` threw a `TypeError` for `int`, `float` and `bool` (its documented argument types);
  `5` is `'5'`, `true` is `'1'`, `false` is `''`.
- `DomainSanitizer`: `www.` is added again only if the `www.` prefix was removed and one label is left (`https://www.ch`
  and `www.ch` give `www.ch`, `foo.www.bar` is unchanged; before, `www.` anywhere in the text counted); the result of
  `preg_replace()` is checked before it is used.
- `DomainValidator`: a top-level domain alone (`academy`, `xn--p1ai`) was a valid domain (the check for two labels
  compared an array with a number); a domain with an internationalized top-level domain (`пример.рф`) was rejected
  (the TLD is checked in its punycode form now).
- `ZipCodeValidator`: the pattern for `DE` was not anchored: `abc 10115`, `10115 abc` and `10115-x` were valid.
- The exception handler showed the message, file and stack trace of an exception as HTML in the debug page: a message
  with `<script>` ran in the browser (debug mode only). Now escaped.

## [v4.39.0] – 2026-10-08

Area release for `src/html/` and `src/layout/`: the HTML classes are `final` (two documented extension points),
`HtmlDocument` renders without `Core` and `RequestHandler`, values of the request are escaped before they reach the
template, tag and attribute names are validated and every PHPStan baseline entry of the area is gone. The rendered HTML
is byte-identical for normal input. Search your project for `new HtmlDocument`, `HtmlEncoder::encodeArray`,
`encodeObject`, `HtmlReplacement::`, `extends HtmlTag`, `extends HtmlText`, `extends HtmlTagAttribute`, `->value =` on
attributes, `getActiveHtmlId` and `new NavigationItem`.

### ⚠️ `HtmlDocument`: constructor with `HtmlDocumentSettings`, `final`

`ContentHandler` creates the document, projects only use it (`BaseView::getHtmlDocument()`). The constructor takes the
values it reads instead of `RequestHandler` and `Core`, so a page can be rendered in a test without them. The public
properties and methods (`replacements`, `templateDirectory`, `contentFileDirectory`, `contentFileName`, `templateName`,
`setActiveHtmlId()`, `isActiveHtmlIdSet()`, `listActiveHtmlIds()`, `render()`) are unchanged. `listActiveHtmlIds()`
returns `array<int, string>`; `getActiveHtmlId()` throws an `OutOfBoundsException` for a key that is not set (was a PHP
warning that the error handler turned into an exception).

| Before | After |
|:--|:--|
| `new HtmlDocument(requestHandler: $rh, cspNonce: $nonce, core: $core, templateEngine: $engine, csrfTokenSource: $csrf)` | `new HtmlDocument(settings: new HtmlDocumentSettings(viewDirectory: ..., fileGroup: ..., fileTitle: ..., fileName: ..., languageCode: ..., copyright: ..., robots: ...), cspNonce: $nonce, templateEngine: $engine, csrfTokenSource: $csrf)` |
| `class HtmlDocument` | `final class HtmlDocument` |
| `getActiveHtmlId(9)` without that key: error | `OutOfBoundsException` |

### ⚠️ `HtmlTag` and `HtmlTagAttribute`: names are validated, `final`

The tag name is output as it is, so a name with `>`, a space or a quote could inject HTML. `HtmlTag` accepts letters, digits
and `-` (starting with a letter); `HtmlTagAttribute` letters, digits and `_ : . -` (starting with a letter, `_` or
`:`; `data-id`, `aria-label`, `xml:lang`, `viewBox` work). Anything else is an `InvalidArgumentException`.
`valueIsEncodedForRendering: true` says that the value is encoded already and is output as it is; a value with a
double quote (which would end the attribute) is now an `InvalidArgumentException` with that flag instead of broken
HTML. `HtmlTagAttribute::$value` is `readonly`. `HtmlTag`, `HtmlTagAttribute` and `HtmlText` are `final`; the
`htmlTagAttributes` argument is a `list<HtmlTagAttribute>`.

| Before | After |
|:--|:--|
| `new HtmlTag(name: 'div" onclick="x', ...)`: output as it is | `InvalidArgumentException` |
| `new HtmlTagAttribute(name: 'a b', ...)` | `InvalidArgumentException` |
| `new HtmlTagAttribute(name: 'title', value: 'a"b', valueIsEncodedForRendering: true)`: `title="a"b"` | `InvalidArgumentException`; pass `valueIsEncodedForRendering: false` or `HtmlEncoder::encode()` the value |
| `$attribute->value = 'x'` | not possible (`readonly`) |
| `class X extends HtmlTag` / `HtmlText` / `HtmlTagAttribute` | not possible (`final`) |

### ⚠️ `HtmlEncoder`: `encodeArray()` and `encodeObject()` are removed, `final`

Both changed the object they got in place, took untyped values and nothing used them. Encode the values when you add
them (`HtmlDataObject::addText()`, `HtmlReplacementCollection::addText()`) or call `HtmlEncoder::encode()` for each value.
`HtmlEncoder` is `final`; it stays static (pure functions without state). `encodeKeepQuotes()` is documented as for the
text between tags only (quotes stay as they are); never use it for an attribute value.

| Before | After |
|:--|:--|
| `HtmlEncoder::encodeArray($array, keepQuotes: false)` | `array_map(HtmlEncoder::encode(...), $array)` (flat array of scalars) |
| `HtmlEncoder::encodeObject($object, keepQuotes: false)` | removed |

### ⚠️ `HtmlReplacement`: named constructors are `from…()`

`HtmlReplacement` is `final readonly` and its named constructors follow `naming.md`. Most code uses
`HtmlReplacementCollection` and never calls them.

| Before | After |
|:--|:--|
| `HtmlReplacement::htmlText($t)` | `HtmlReplacement::fromHtmlText($t)` |
| `HtmlReplacement::html($s)` / `::text($s)` | `HtmlReplacement::fromHtml($s)` / `::fromText($s)` |
| `HtmlReplacement::bool($b)` / `::int($i)` / `::float($f)` | `HtmlReplacement::fromBool($b)` / `::fromInt($i)` / `::fromFloat($f)` |
| `HtmlReplacement::dataObject($o)` | `HtmlReplacement::fromDataObject($o)` |
| `HtmlReplacement::textCollection($c)` | `HtmlReplacement::fromTextCollection($c)` |
| `HtmlReplacement::htmlDataObjectCollection($c)` | `HtmlReplacement::fromHtmlDataObjectCollection($c)` |

### ⚠️ `NavigationItem`: the `href` is checked, `final`

`href` is output into an attribute as it is. A `javascript:` (or `data:`, `vbscript:`, `file:`) URL, or a value with a
control character, a double quote or an angle bracket, is an `InvalidArgumentException`; relative URLs, `http`,
`https`, `mailto` and `tel` are allowed. `title`, `svgPath` and the CSS classes stay trusted HTML of the application
(documented). `NavigationItem` and `NavigationItemCollection` are `final`.

| Before | After |
|:--|:--|
| `new NavigationItem(href: 'javascript:void(0)', ...)` | `InvalidArgumentException` |
| `class X extends NavigationItem` / `NavigationItemCollection` | not possible (`final`) |

### ⚠️ `final` classes and extension points

`final`: `HtmlDocument`, `HtmlTag`, `HtmlTagAttribute`, `HtmlText`, `HtmlEncoder`, `HtmlReplacement`,
`HtmlReplacementCollection`, `HtmlTextCollection`, `HtmlDataObjectCollection`, `DetailDataObject`, `NavigationItem`,
`NavigationItemCollection`. Extension points (documented in the class comment): `HtmlElement` (only for the components
of `src/form/`; extend `FormComponent` or one of its subclasses) and `HtmlDataObject` (a subclass fills its properties
in its constructor, as `DetailDataObject` does). Nothing in `actra/backend` extends one of the `final` classes.
`HtmlDocumentSettings` is new (`final readonly`).

### Fixed

- **Values of the request reached the template as HTML:** `HtmlDocument` added `bodyClassName` (`body-` + the requested
  file title), `requestedFileName` (the requested file name), `language`, `robots` and `copyright` as HTML. A URL with a
  `"` could break out of `<body class="{bodyClassName}">` when a view shows a page for such a name. They are escaped now
  (identical for the usual names).
- **Path traversal through the request:** a file group or file title with a `..` segment, an absolute path, a backslash
  or a null byte is a 404; with a route like `/${fileGroup}/${fileName}` it could reach an `.html` file outside the
  content directory.
- `HtmlEncoder::encode()` and `encodeKeepQuotes()` returned an empty string for text with invalid UTF-8 (the whole
  value was lost); the bad bytes are replaced by U+FFFD now (`ENT_SUBSTITUTE`). The same applies to `HtmlText`,
  `HtmlTagAttribute` and everything that calls the encoder.
- `HtmlReplacementCollection::addInt()` handed the template a `float` (`7.0`; the text output was the same):
  it is an `int` now.
- `NavigationItemCollection::$isActive` stayed `true` once an item was active; each `prepareForRenderer()` call
  decides again.

## [v4.38.0] – 2026-10-08

Area release for `src/api/` (the cURL client): no static state any more (the shared cURL handle and the registry of
requests are replaced by `CurlClient`), safe by default (certificate and host name are always verified, TLS 1.2 or
newer, only `http` / `https`, no redirects, credentials only over HTTPS, a limit for the size of the response, header
injection is rejected) and every class is `final`. The usual code (`CurlGetRequest::create()`, `createWithJsonBody()`,
`setHttpHeader()`, `setTimeoutInSeconds()`, `useTokenAuthentication()`, `execute()`, `rawResponseBody`, `hasErrors()`,
`getJsonResponse()`) keeps working after the rename of the factories (first entry below). Search your project for `setCurlOption`, `removeCurlOption`,
`disableSslCheck`, `createFromPreparedCurlHandle`, `extends AbstractCurlRequest`, `setTimeoutInSeconds`,
`useBasicHttpAuthentication`, `useTokenAuthentication`, `CurlHeadRequest`, `prepare`, `rawResponseBody` and `http://` URLs of API
calls.

### ⚠️ Factories: `prepare…()` is `create…()`

Named constructors are called `create…()` (naming.md). Every public static factory of the six request classes is renamed,
the suffix stays, there are no aliases. Search your project case-insensitively for `prepare` in the files that use a
`Curl…Request` (`CurlGetRequest::prepare`, `->prepareWith`, `::prepareJsonApiRequest`) and replace `prepare` with
`create`; do not touch `Core::prepareHttpResponse()` or `prepareSelect()`.

| Before | After |
|:--|:--|
| `CurlGetRequest::prepare()` | `CurlGetRequest::create()` |
| `CurlHeadRequest::prepare()` | `CurlHeadRequest::create()` |
| `CurlDeleteRequest::prepare()` | `CurlDeleteRequest::create()` |
| `CurlPostRequest::prepareWithPostBody()` | `CurlPostRequest::createWithPostBody()` |
| `CurlPostRequest::prepareWithXmlBody()` | `CurlPostRequest::createWithXmlBody()` |
| `CurlPostRequest::prepareWithJsonBody()` | `CurlPostRequest::createWithJsonBody()` |
| `CurlPostRequest::prepareJsonApiRequest()` | `CurlPostRequest::createJsonApiRequest()` |
| `CurlPostRequest::prepareWithPlainTextBody()` | `CurlPostRequest::createWithPlainTextBody()` |
| `CurlPutRequest::prepareWith…()` / `prepareJsonApiRequest()` (the same five) | `CurlPutRequest::createWith…()` / `createJsonApiRequest()` |
| `CurlPatchRequest::prepareWith…()` / `prepareJsonApiRequest()` (the same five) | `CurlPatchRequest::createWith…()` / `createJsonApiRequest()` |
| `CurlPatchRequest::prepareWithoutBody()` | `CurlPatchRequest::createWithoutBody()` |

```php
// Before
$response = CurlPostRequest::prepareWithJsonBody(requestTargetUrl: $url, jsonString: $json)->execute();
// After
$response = CurlPostRequest::createWithJsonBody(requestTargetUrl: $url, jsonString: $json)->execute();
```

### ⚠️ `setCurlOption()`, `removeCurlOption()` and `disableSslCheck()` are removed

Raw cURL options let a caller weaken every setting of the client (the old protected list did not contain `CURLOPT_PROTOCOLS`,
`CURLOPT_FOLLOWLOCATION`, `CURLOPT_PROXY`, ...), and `disableSslCheck()` switched off the certificate and host name
check. The client sets what it needs and nothing can be changed from outside. A server with a certificate that is not
trusted by the server your code runs on must get a trusted certificate (or its CA must be installed on the machine /
`curl.cainfo` in `php.ini`); the check cannot be switched off any more.

| Before | After |
|:--|:--|
| `$request->setCurlOption(CURLOPT_..., $value)` | removed |
| `$request->removeCurlOption(CURLOPT_...)` | removed |
| `$request->disableSslCheck()` | removed |

### ⚠️ Static state is gone: `execute()` and `CurlClient`

`AbstractCurlRequest::$curlHandle` (a cURL handle shared by all requests of the process) and `$instances` are removed.
`CurlClient` keeps a handle for its lifetime: send several requests to the same server through one client to reuse the
connection. `$request->execute()` still works and uses a client of its own. A request can be sent more than once now
(it was a `LogicException` before).

| Before | After |
|:--|:--|
| `$request->execute()` | unchanged (new client per call) |
| - | `$client = new CurlClient(); $client->send(request: $request)` or `$request->execute(curlClient: $client)` |
| second `execute()` of a request: `LogicException` | allowed |

### ⚠️ The request target is validated

`create…()` throws an `InvalidArgumentException` if the URL is no absolute `http://` or `https://` URL with a host name,
contains spaces, control characters or backslashes, or has a user name or password. Before, `file://`, `ftp://`,
`gopher://` and so on were handed to cURL, and an invalid URL showed up as a cURL error in the response. The message does
not contain the URL (a query string may carry a token). User name and password do not belong into a URL (they end up in
logs): use `useBasicHttpAuthentication()`.

| Before | After |
|:--|:--|
| `CurlGetRequest::create('ftp://example.com/')`: cURL error at `execute()` or a transfer | `InvalidArgumentException` |
| `CurlGetRequest::create('https://user:pass@example.com/')` | `InvalidArgumentException`, use `useBasicHttpAuthentication('user:pass')` |
| `CurlGetRequest::create('not a url')`: `CURLE_URL_MALFORMAT` in the response | `InvalidArgumentException` |

### ⚠️ Credentials are only sent over HTTPS

`useBasicHttpAuthentication()` and `useTokenAuthentication()` throw a `LogicException` if the URL is `http://` (a server
on this machine - `localhost`, `127.x.x.x`, `[::1]` - is allowed). Basic authentication and bearer tokens over plain
HTTP can be read by everybody on the way. Use `https://`. The token must consist of visible ASCII characters (no space,
no line break; header injection), the basic credentials must not be empty or contain control characters
(`InvalidArgumentException`). API keys that you send with `setHttpHeader()` are not checked for the protocol: use `https://`.

| Before | After |
|:--|:--|
| `create('http://api.example.com/')->useTokenAuthentication($token)` | `LogicException`; use `https://` |

### ⚠️ Headers are validated, protected headers are case-insensitive

`setHttpHeader()` throws an `InvalidArgumentException` if the name is no valid header name (letters, digits and
``!#$%&'*+.^_`|~-``) or the value contains a line break, `\0` or another control character (tab is allowed). Before, a
value like `"x\r\nBcc: ..."` added headers to the request (header injection). The messages name the header, never the
value. `Content-Type` and `Content-Length` cannot be set in any spelling (`content-type` was possible, then two
different `Content-Type` headers were sent). A header you set again (in any spelling) replaces the earlier one. The
`Content-Length` header is no longer set by hand, cURL sends it for the body.

### ⚠️ Timeouts of 0 are rejected

`setTimeoutInSeconds(0, 0)` meant "wait forever" for cURL. Both timeouts must be at least 1 second
(`InvalidArgumentException`, was no check); a connect timeout longer than the request timeout is still a
`LogicException`. The defaults are unchanged (10 seconds each).

### ⚠️ Limit for the size of the response, TLS 1.2 or newer

A response body larger than 32 MiB (`AbstractCurlRequest::DEFAULT_MAX_RESPONSE_SIZE_IN_BYTES`) is aborted: `hasErrors()`
is `true`, `errorCode` is the new `CurlResponse::ERROR_RESPONSE_TOO_LARGE` (901) and `rawResponseBody` is `false`. A
server that announces a larger `Content-Length` is refused before the transfer, a streamed response is cut at the limit.
Change it per request with `setMaxResponseSizeInBytes()`. The client also refuses servers that only speak TLS 1.0 / 1.1.
Redirects were never followed and still are not (neither is a redirect to another protocol): with
`acceptRedirectionResponseCode()` a 301 / 303 is no error and the target is in the `Location` header of the response.

### ⚠️ `CurlResponse`: `final readonly`, new constructor, headers, exceptions for failed requests

`CurlResponse::createFromPreparedCurlHandle()` is removed (`CurlClient` builds the response; the constructor is public
for tests). The properties (`rawResponseBody`, `curlInfo`, `responseHttpCode`, `totalRequestTime`, `errorCode`,
`errorMessage`) are unchanged; `rawResponseBody` is `false` if the transfer failed (no connection, timeout, too large).
New: `$headers`, `getHeader($name)` (first value, name in any case, `null` if missing) and `getHeaderValues($name)`.
`getJsonResponse()` and `getXmlResponse()` of a failed request (no body) throw a `RuntimeException` (was a `TypeError`
because of `false`); `getXmlResponse()` loads nothing from the network (`LIBXML_NONET`).

| Before | After |
|:--|:--|
| `CurlResponse::createFromPreparedCurlHandle($handle, $accept)` | removed, use `CurlClient::send()` |
| `class CurlResponse` | `final readonly class CurlResponse` |
| `CurlHeadRequest`: `rawResponseBody` is the raw header text | `rawResponseBody` is `''`, the headers are in `getHeader()` / `$headers` |
| `getJsonResponse()` after a failed request: `TypeError` | `RuntimeException` |

### ⚠️ Request classes are `final`, `AbstractCurlRequest` is not an extension point

`CurlGetRequest`, `CurlPostRequest`, `CurlPutRequest`, `CurlPatchRequest`, `CurlDeleteRequest` and `CurlHeadRequest` are
`final` (their constructors were private already). `AbstractCurlRequest` stays the type to accept both in signatures; its
protected constructor takes the `RequestMethodEnum` and the URL now (`__construct(RequestMethodEnum $method, string
$requestTargetUrl)`), and it is not meant to be extended by projects. The factories are renamed (see above).
`CurlPostRequest`, `CurlPutRequest` and `CurlPatchRequest` take `array<array-key, mixed> $postData` (scalars, `null`,
objects, nested arrays; a resource is an `InvalidArgumentException` now).
New getters for what is sent: `getMethod()`, `getUrl()`, `getHttpHeaders()`, `getBody()`, `getConnectTimeoutInSeconds()`,
`getRequestTimeoutInSeconds()`, `getMaxResponseSizeInBytes()`, `isRedirectionResponseCodeAccepted()`. Debug output
(`print_r()`, `var_dump()`) of a request shows method, host and flags only, no URL, header or body.

### ⚠️ New and internal classes

New `final`: `CurlClient` (public); `@internal`: `CurlBodyTypeEnum`, `CurlHeader` (value of `getHttpHeaders()`),
`CurlAuthentication`, `CurlAuthenticationMethodEnum`, `CurlTargetUrl`, `CurlFormEncoder`, `CurlOptionsBuilder`,
`CurlResponseCollector`, `CurlErrorEvaluator`, `CurlResponseError`.

### Fixed

- **A status code that `HttpStatusCodeEnum` does not know was no error:** `429`, `418`, `599` ... gave `responseHttpCode ===
  HTTP_UNKNOWN` and `hasErrors() === false`, the request looked successful. The check uses the number now
  (`errorCode` 900 for every status from 300 to 599), `responseHttpCode` is still `HTTP_UNKNOWN` for them.
- `setHttpHeader('content-type', ...)` bypassed the protection of the body headers (see above).
- `getJsonResponse()` / `getXmlResponse()` after a failed transfer threw a `TypeError` (see above).
- The error message of `CURLE_SSL_PEER_CERTIFICATE` (60, a certificate that cannot be verified) says that the check is
  always on.

## [v4.37.0] – 2026-10-08

Area release for `src/auth/`, `src/security/` and `src/session/`: passwords are hashed with `password_hash()` (Argon2id)
and old salt-and-SHA-256 passwords are upgraded at the next login, the login gives the session a new ID, the session
handler finally rejects session IDs it did not issue (PHP's strict mode was a no-op for it), the Microsoft ID token is
checked more completely (`iss`, strict JWT format, key cache that cannot lock everybody out), and the area is `final`
(extension points: `AuthUser`, `Authenticator`, `MicrosoftAuthenticator`, `AuthWebToken`, `AbstractSessionHandler`).
Search your project for `Password`, `->salt`, `createWithSalt`, `HASH_ALGORITHM`, `extends AuthUser`,
`extends Authenticator`, `extends MicrosoftAuthenticator`, `extends AuthWebToken`, `MicrosoftIdToken`, `jwtArray`,
`base64Header`, `->secret`, `extends AbstractSessionHandler`, `extends FileSessionHandler`, `->fingerprint`,
`checkSessionIdAgainstSidBitsPerChar`, `extends AccessRightCollection`, `extends CspPolicySettings`, `new SessionSettings(`
and `ssoMicrosoft.log`.

### ⚠️ `Password`: `password_hash()` instead of salt and SHA-256

`Password::generateNew()` returns a password with an empty `salt` and an Argon2id hash (`password_hash()`, with
`PASSWORD_DEFAULT` on a PHP without Argon2) in `hash`: the hash string carries its own salt, algorithm and costs. The
column for the hash needs at least 255 characters (an Argon2id hash has about 100); the salt column must accept an
empty string. Existing rows stay valid: a password with a non-empty salt is verified as before (now with
`hash_equals()`), `Password::isLegacy()` / `needsRehash()` tell that it is outdated, and the `Authenticator` stores a
new hash after the next successful password login (`AuthUser::rehashPassword()`). Remove `createWithSalt()` and
`HASH_ALGORITHM` from your code; `Password` is `final readonly`.

| Before | After |
|:--|:--|
| `Password::HASH_ALGORITHM` | removed |
| `Password::createWithSalt(salt: $salt, rawPassword: $raw)` | removed (`new Password(salt:, hash:)` for stored values, `generateNew()` for new ones) |
| `generateNew()`: random 16 character `salt`, `sha256` hash | empty `salt`, `password_hash()` hash |
| `isValid()` compared with `===` | `password_verify()` (new) or `hash_equals()` (legacy) |
| - | `isLegacy()`, `needsRehash()`, `Password::spendVerificationTime()` |

Passwords that are checked often and have high entropy (API keys: `Password::generateNew(rawPassword: $secret)`) now cost
one Argon2id verification (about 50 ms, 64 MB) per check: use `SecretTokenHash` for them (see below). `MyAuthUser`-like classes that call
`Password::generateNew(rawPassword: 'unused')` for users without password pay that for every user they load: use a
constant hash instead.

### `SecretTokenHash` for API keys and other random tokens

New `final readonly` classes `SecretTokenHash` and `GeneratedSecretToken` for secrets the application generates (API keys,
reset links, remember-me tokens): `SecretTokenHash::generate()` returns a `GeneratedSecretToken` with `$secret` (32
random bytes, base64url without padding, show it once) and `$hash` (SHA-256 as 64 lowercase hex characters, store
`$hash->hash`); `new SecretTokenHash(hash: $stored)` restores it (`InvalidArgumentException` for another format) and
`isValid(secret:)` compares with `hash_equals()`. A fast hash is right for such secrets (high entropy, checked on every
request); `Password` (Argon2id) stays for passwords that humans choose.

Projects that stored API keys as `Password` (e.g. `actra/backend`: `DbAuthApiKeyRepository`) should switch, because the
Argon2id check now costs about 50 ms and 64 MB per request. Either re-issue the keys, or migrate on next use: keep the old
columns, verify a presented key with the old `Password` once, and on success store `SecretTokenHash::fromSecret()` of it
in a new column and clear the old salt and hash; check the new column first.

### ⚠️ `AuthUser`: persist the upgraded password hash

`AuthUser` has the new abstract method `dbUpdatePassword(Password $newPassword): void` (store `salt` and `hash`; do not
reset the wrong password attempts). The protected `changePassword()` is removed (it only changed the property, without
persisting); `rehashPassword(string $rawPassword)` replaces it and is called by the `Authenticator`. `$ipWhitelist` is a
`list<string>`.

Before:

```php
final class MyAuthUser extends AuthUser
{
    protected function dbIncreaseWrongPasswordAttempts(): void { /* ... */ }
    protected function dbConfirmSuccessfulLogin(): int { /* ... */ }
}
```

After:

```php
final class MyAuthUser extends AuthUser
{
    protected function dbIncreaseWrongPasswordAttempts(): void { /* ... */ }
    protected function dbConfirmSuccessfulLogin(): int { /* ... */ }

    protected function dbUpdatePassword(Password $newPassword): void
    {
        // UPDATE auth_user SET passwordSalt=?, passwordHash=? WHERE ID=?
    }
}
```

### ⚠️ `AuthWebToken` and `MicrosoftIdToken`

`AuthWebToken` (extension point for other identity providers) no longer exposes the raw segments: `jwtArray`,
`base64Header`, `base64Payload`, `base64Secret`, `secret` and `jwtString` are removed; subclasses have the protected
`encodedHeader`, `encodedPayload` and `signature` (the decoded signature), `header` and `payload` stay public. The
protected `jsonDecode()` is now `decodeJsonObject()`. A malformed token (not exactly three segments, invalid base64url,
invalid JSON, JSON that is no object) throws an `UnauthorizedException` (before: a token with more than three segments was
accepted by ignoring the rest, invalid JSON threw a `JsonException`). `getUserName()` throws an `UnauthorizedException`
when the token has no `email`.

`MicrosoftIdToken` is `final`; `parseKeySet()` is removed. New constructor argument `keySetSource:` (default: Microsoft
over HTTPS, a `JsonWebKeySetSource` for tests). Changes in the checks and the key cache:

- The claim `iss` must be `https://login.microsoftonline.com/<tenant>/v2.0` (new check; `aud`, `tid` and `nonce` as
  before, compared with `hash_equals()`, and they must be strings; the time claims must be numbers).
- The tenant ID must be a GUID or a domain name (`InvalidArgumentException`), because it goes into a URL and a file name.
- The key cache is `ssoMicrosoftKeys-<tenantId>.json` in the cache directory (before: `ssoMicrosoftKeys.json`, shared by
  all tenants). The old file can be deleted; the first login downloads the keys again.
- The key set is only written to the cache if it parses, atomically and with mode 0600. An unknown key ID triggers a
  download at most every 5 minutes. The download verifies certificate and host name, does not follow redirects and has a
  timeout and a size limit.

### ⚠️ `MicrosoftAuthenticator`

`microsoftIdTokenLogin()` and `redirectToMicrosoftLogin()` have the same signatures. The log of failed token checks moved
to `LogFile` (`<logDirectory>ssoMicrosoft/Y/m/d/ssoMicrosoft-<time>-<random>.log`, before: one file
`<logDirectory>ssoMicrosoft.log`) and contains the exception class and message only: the raw ID token, the nonce and the
email are no longer written (a token in a log file is a credential until it expires). The login redirect URL encodes its
parameters (`redirect_uri` was inserted as it was). Only expected failures (`UnauthorizedException`,
`InvalidArgumentException`, `RuntimeException`) fail the login; a `TypeError` is a bug and propagates.

### ⚠️ `AuthSession::logIn()` regenerates the session ID

`logIn()` gives the session a new ID and deletes the old session (before: the ID stayed, so an ID known to an attacker
before the login stayed valid: session fixation). A project that stored the session ID (`getSessionId()`) before the
login must read it again. The ID written to the log of the login attempt is the one before the login.

### ⚠️ Session handler

- `AbstractSessionHandler` implements `SessionUpdateTimestampHandlerInterface`: `validateId()` lets PHP's strict mode work.
  **PHP ignores the strict mode for a handler that cannot tell whether an ID exists**, so before this release any
  well-formed session ID in the cookie (set by an attacker as cookie on a sibling domain) was accepted as the session ID.
  Now a subclass has to implement the new abstract method `sessionExists(string $id): bool` (`FileSessionHandler` checks
  the session file).
- Removed: the public properties `$name` and `$fingerprint` (nothing read them), `setTrustedUserAgent()` is private, the
  protected `checkSessionIdAgainstSidBitsPerChar()` is replaced by a fixed check of the characters PHP accepts in an ID
  (the ini setting `session.sid_bits_per_character` is deprecated). `getTrustedRemoteAddress()`, `getTrustedUserAgent()`,
  `getSessionCreated()`, `getId()`, `regenerateId()` and `changeCookieSameSiteTo…()` stay.
- `session.use_only_cookies=1` and `session.use_trans_sid=0` are set (the ID never comes from the URL or the request).
- A session without a time of last activity, without trusted address or without trusted user agent is replaced by a new
  one (before: it was kept, or `getTrustedRemoteAddress()` threw an `UnexpectedValueException` on every request). When a
  session is replaced, the old data is removed first; if the ID could not be changed, a `RuntimeException` is thrown instead
  of continuing with the ID of the untrusted session.
- `FileSessionHandler` is `final`; it creates the save path with mode 0700 (before: 0777 minus umask) and throws a
  `RuntimeException` if that fails. `SessionSettings` is `final` and rejects a lifetime below one second and a negative
  garbage collection probability or a divisor below one (`InvalidArgumentException`).

### ⚠️ `final` classes, new internal classes

`AccessRightCollection` (private constructor, `list<string>`), `UnauthorizedAccessRightException`,
`UnauthorizedIpAddressException`, `Password`, `MicrosoftIdToken`, `IdTokenTimeClaimsValidator` (the leeway must not be
negative), `CspPolicySettings`, `FileSessionHandler` and `SessionSettings` are `final`. Nothing in `actra/backend` extends
them. `AuthSessionKeyEnum` is `@internal`. New: the interface `JsonWebKeySetSource` and `MicrosoftKeySetSource`, and the
`@internal` classes `CachedKeySet`, `JsonWebKeySetParser`, `LoginAttempt`, `MicrosoftLoginUri`, `MicrosoftTenantId`.

### Fixed

- Session fixation through the cookie (see above) and through the login (the ID is regenerated).
- The CSP header put the host of the request into the policy as it was: a `Host` header with `;` or spaces could add
  directives. A host with other characters than letters, digits, `.`, `:`, `-`, `[` and `]` becomes `invalid.invalid`.
- `CsrfHiddenFieldRenderer` now HTML-encodes the token (the base64 tokens of `SessionCsrfTokenSource` are unchanged).
- A broken key cache file (e.g. an HTML error page from a failed download) made every Microsoft login fail until the file
  was deleted by hand; it is replaced by a new download now. A token with an unknown key ID made the server download the
  key set on every request.
- A wrong password for an unknown user was answered faster than one for a known user (user enumeration by response time):
  the check is done against a dummy hash now.
- The login of a user with an outdated hash upgrades it; a failed login never touches the stored password.

## [v4.36.0] – 2026-10-08

Area release for `src/mailer/` (derived from PHPMailer, the licence notices and `gpl-3.0.txt` / `lgpl-3.0.txt` are
unchanged): enums instead of string and int constants, `final` classes, the I/O of `SmtpMailer` and `MailMailer` behind
small interfaces, injectable time, random id and server name, TLS that fails closed, no `Bcc` header in SMTP messages and
fixes for header injection and several crashes found while characterizing the MIME output. The extension points of the
area are `AbstractMail` (a project mail extends it, as `TextMail` and `HtmlMail` do) and `AbstractMailer`; everything
else is `final` or an interface. Search your project for `MailerConstants::`, `MailerFunctions`, `MailerException`,
`catch (`, `new SmtpMailer(`, `new MailMailer(`, `charSet:`, `encoding:`, `priority:`, `MailerFileAttachment(`,
`MailerStringAttachment(`, `->lastReply`, `->log`, `sendData(`, `extends AbstractMailer`, `extends TextMail`,
`extends HtmlMail`, `MailMimeHeader` and `MailMimeBody`.

### ⚠️ Charset, encoding, priority and content type are enums

`MailerConstants::CHARSET_*`, `ENCODING_*`, `PRIORITY_*`, `CONTENT_TYPE_*` and the `*_LIST` arrays are removed. The
values of the enums are the old strings and numbers. The mail classes, `MailerFileAttachment` and the address and header
methods take the enums; an invalid value is a `TypeError` now (before: a `MailerException` with the list of allowed
values). `MailerConstants` keeps `CRLF`, `MAIL_MAX_LINE_LENGTH`, `MAX_LINE_LENGTH` and `STD_LINE_LENGTH` (no sets of
values) and is `final`.

| Before                                      | After                                |
|:--------------------------------------------|:-------------------------------------|
| `MailerConstants::CHARSET_UTF8` / `_ASCII`  | `MailerCharsetEnum::UTF8` / `ASCII`  |
| `MailerConstants::ENCODING_7BIT`            | `MailerEncodingEnum::SEVEN_BIT`      |
| `MailerConstants::ENCODING_8BIT`            | `MailerEncodingEnum::EIGHT_BIT`      |
| `MailerConstants::ENCODING_BASE64`          | `MailerEncodingEnum::BASE64`         |
| `MailerConstants::ENCODING_BINARY`          | `MailerEncodingEnum::BINARY`         |
| `MailerConstants::ENCODING_QUOTED_PRINTABLE` | `MailerEncodingEnum::QUOTED_PRINTABLE` |
| `MailerConstants::PRIORITY_HIGH` / `NORMAL` / `LOW` | `MailerPriorityEnum::HIGH` / `NORMAL` / `LOW` |
| `MailerConstants::CONTENT_TYPE_*`           | `MailerContentTypeEnum::TEXT_PLAIN`, `TEXT_HTML`, `MULTIPART_*` (only used by the mailer itself) |

Before:

```php
new TextMail(
    ...,
    textBody: $text,
    encoding: MailerConstants::ENCODING_BASE64,
    priority: MailerConstants::PRIORITY_HIGH,
);
new MailerFileAttachment(path: $path, encoding: MailerConstants::ENCODING_QUOTED_PRINTABLE);
```

After:

```php
new TextMail(
    ...,
    textBody: $text,
    encoding: MailerEncodingEnum::BASE64,
    priority: MailerPriorityEnum::HIGH,
);
new MailerFileAttachment(path: $path, encoding: MailerEncodingEnum::QUOTED_PRINTABLE);
```

The SMTP login has one method (`AUTH LOGIN`), so there is no enum for it.

### ⚠️ `MailerException` is a `RuntimeException`, SMTP errors are `MailerException`

`MailerException` extends `RuntimeException` (before: `Exception`) and is `final`. `SmtpMailer` throws `MailerException`
for every delivery problem (before: `RuntimeException` for connection and server answers, `Exception` for commands with
line breaks). A `catch (MailerException)` now also catches the SMTP errors; a `catch (RuntimeException)` or
`catch (Exception)` still does. The messages say what is wrong and never contain passwords, the content of a message or
(new) the addresses and header values that were rejected: `Unexpected answer of the SMTP server to the password: code 535
instead of 235.` (before: `Unexpected server response code 535`).

### ⚠️ `AbstractMailer`: new abstract method and optional constructor arguments

A mailer of your own (`extends AbstractMailer`) implements the new `headerHasBcc(): bool`: `true` if the transport
removes the `Bcc` header itself (`mail()`), `false` for SMTP (see "Fixed"). The constructor has three more optional
arguments (`Clock $clock`, `MimeIdGenerator $mimeIdGenerator`, `ServerNameResolver $serverNameResolver`) for tests; the
methods `getClock()` and `createUniqueId()` are new. `AbstractMailer` is a documented extension point. `MailMimeHeader` and
`MailMimeBody` are `@internal` and `final` (their constructors changed, a mailer only reads `getMimeHeader()` and
`getMimeBody()`).

### ⚠️ `SmtpMailer` and `MailMailer`: `final`, new arguments, TLS fails closed

Both are `final`. New optional arguments at the end: `SmtpMailer(..., SmtpTransport $transport, Clock $clock,
MimeIdGenerator $mimeIdGenerator, ServerNameResolver $serverNameResolver)` and `MailMailer(string $serverAddress,
MailFunction $mailFunction, Clock $clock, ...)`; `SmtpMailer::sendData()` is private (it never worked outside `sendMail()`).
`SmtpMailer` throws an `InvalidArgumentException` for a host name that is no host name or IP address (spaces, line breaks)
and for a port outside 1 to 65535.

With `useTls: true` (the default) the delivery now stops if STARTTLS is refused or the TLS handshake fails (before: the
result of `stream_socket_enable_crypto()` was ignored, the mailer went on and sent the credentials and the message
without encryption if the handshake had failed). The certificate chain and the host name `$hostName` are verified
(`verify_peer`, `verify_peer_name`, no self-signed certificates), TLS 1.2 or 1.3 is required. A server with a
self-signed certificate or an old TLS version needs a valid certificate; there is no switch to turn the verification off.
`useTls: false` still sends everything, also the password of `AUTH LOGIN`, unencrypted: use it only for a server on the
same host (e.g. a local development mail catcher).

`$log` of `SmtpMailer` holds the lines of the last delivery only (it grew with every delivery before) and shows
`(hidden)` instead of the base64 user name and password. `$lastReply` is empty at the start of a delivery.

### ⚠️ `MailerFunctions` is removed

The internal helper class is split by purpose (all `@internal`, static and pure):

| Before                                                          | After                                                       |
|:----------------------------------------------------------------|:------------------------------------------------------------|
| `MailerFunctions::encodeHeaderText()`                           | `MailerHeaderEncoder::encodeText()`                         |
| `MailerFunctions::encodeHeaderPhrase()`                         | `MailerHeaderEncoder::encodePhrase()`                       |
| `MailerFunctions::secureHeader()`                               | `MailerHeaderEncoder::secure()`                             |
| `MailerFunctions::wrapText()`                                   | `MailerTextWrapper::wrap()`                                 |
| `MailerFunctions::encodeString()`, `has8bitChars()`             | `MailerContentEncoder::encode()`, `has8bitChars()`          |
| `MailerFunctions::mbPathinfo()`, `filenameToType()`             | `MailerFileName::baseName()` / `extension()`, `MailerMimeTypes::getByFileName()` |
| `MailerFunctions::validateAddress()`, `punyEncodeDomain()`      | private in `MailerAddress`                                  |
| `MailerFunctions::fileIsAccessible()`                           | private in `MailerFileAttachment`                           |
| `MailerFunctions::isShellSafe()`, `textLine()`, `stripTrailingWsp()` | private in `MailMailer`, removed, removed              |

`MailerMimeTypes`, `MailerHeader` and `MailerHeaderCollection` are `@internal` and `final`.

### ⚠️ Attachments: `MailerAttachment` interface, stricter file names and types

`AbstractMail::addAttachment()` takes the new interface `MailerAttachment` (implemented by `MailerFileAttachment` and
`MailerStringAttachment`, both `final readonly`; callers do not change). `MailerAttachmentCollection::list()` returns a
list (before: an array keyed by file name). `MailerStringAttachment::$encoding` and `MailerFileAttachment::$encoding` are
`MailerEncodingEnum`. The collection and the address collection are `final`.

- The file name is reduced to its last path segment and loses control characters: `../../etc/passwd` is sent as
  `passwd` (before: as given, including the directories).
- The type must be `type/subtype` (`text/plain`, `application/vnd.api+json`); parameters (`text/plain; charset=utf-8`)
  and line breaks throw a `MailerException` (before: the string went into the `Content-Type` header unchecked).
- A `MailerFileAttachment` for a directory throws (before it was accepted and failed when sending).
- The content of a `MailerStringAttachment` is no longer trimmed (before: leading and trailing white space and `\0`
  bytes of the data were removed); only an empty content throws.

### ⚠️ Custom headers: the name is validated

`AbstractMail::addCustomHeader()` accepts only names of printable ASCII characters without a colon and without white
space (`X-Request-Id`); `X-A: b` and `X A` throw a `MailerException` (before: the colon went into the header line). The
signature is unchanged.

### ⚠️ `MailerAddress`, `MailerAddressCollection`: `final`, no address in the messages

Both are `final` (`MailerAddress` also `readonly`). The `defaultCharSet` arguments of `getFormattedAddressForMailer()`,
`getHeaderString()` and `listAsCommaSeparatedString()` are `MailerCharsetEnum`. The exception messages no longer contain
the address (personal data in logs): `Invalid To address.`, `Missing @-sign in the To address.`, `The address is already
a recipient (Cc).` (before: `Invalid address (To): anna@example.com`, `Address exists already: anna@example.com`).
`MailerAddress` throws a `MailerException` for a domain that cannot be punycode encoded (before: a `TypeError`).

### ⚠️ `TextMail` and `HtmlMail` are `final`

Nothing in `actra/backend` extends them. A project mail extends `AbstractMail` (the constructor and `setTextBody()` /
`setHtmlBody()` stay protected).

### ⚠️ `getServerName()`: invalid server address and host names from DNS

The host name of the server (message id, `EHLO`) is a plain host name (letters, digits, `.`, `-`) or the server address.
A reverse DNS entry with other characters is ignored (the owner of the address range controls it: line breaks would have
reached the `EHLO` command), an empty or invalid `serverAddress` gives `localhost` (before: an empty name and a PHP
warning from `gethostbyaddr()`).

### ⚠️ `mail()`: sender parameter only for ASCII

`MailMailer` passes `-f<sender>` only if the sender consists of ASCII letters, digits and `@_.-` (before: `ctype_alnum()`
of the current locale). Other senders (`a+b@example.com`) still fall back to the default sender of the server.

### Fixed

- **`Bcc` was in the header of SMTP messages:** every recipient saw the blind copies. `SmtpMailer` messages have no `Bcc`
  header any more (the blind recipients stay `RCPT TO`); `MailMailer` keeps it, `sendmail` removes it.
- **Attachments were sent twice** in messages with inline images and attachments: the related part of the HTML and the
  inline images contained the normal attachments too (with a `Content-ID`).
- **Header injection:** the type of an attachment (`text/plain\r\nBcc: ...`) could add header lines to a part; it is
  validated now (see above). Line breaks in subject and names were already removed, in addresses and custom headers they
  were already rejected (covered by tests now).
- **Subjects and names crashed with a `TypeError`** if more than a third of the text is non-ASCII (`Привет`, `äöü`, Greek,
  CJK): the base64 encoding of multibyte text passed a float to `mb_substr()`.
- **Long encoded headers threw `Invalid header name or value`:** values that need several encoded words (a long name or
  subject with umlauts in a `mail()` message with its 63 character lines, a subject longer than 998 characters in SMTP)
  were rejected because of their folds. The folds `\r\n ` of encoded words are valid now; every other line break in a
  header is still rejected.
- **Dot stuffing in `DATA`:** a line that was split because it is longer than 998 characters was stuffed by the
  start of the rest of the line, not by the start of the part that is sent, so a part that starts with `.` could end the
  message early (`\r\n.\r\n`); every sent line that starts with a dot is stuffed now.
- **Single part messages with a line of 1000 or more characters** and the encoding `7bit`, `8bit` or `binary` were sent
  as they are (SMTP broke the line at an arbitrary place); they are sent quoted-printable now, as the parts of a
  multipart message already were.
- **A server that closes the connection** (`TypeError` from `substr()`) is a `MailerException` that names the command.
- No `uniqid()` / `mt_rand()` fallback for the ids: the unique id comes from `random_bytes()` or the delivery fails.

### New

- Interfaces for testing and for your own transport: `SmtpTransport` (`StreamSmtpTransport` is the default),
  `MailFunction` (`NativeMailFunction`), `MimeIdGenerator` (`RandomMimeIdGenerator`), `ServerNameResolver`
  (`ReverseDnsServerNameResolver`). `Clock` (`FixedClock` in tests) was already there. Hand-written doubles are in
  `tests/Double/mailer/`.
- Not changed, but good to know: `AbstractMail::$wordWrap` stays a public property; all addresses are lower cased (also
  the local part); `X-Mailer: PHP/<version>` is still sent; the line breaks of a text body are encoded as `=0A` in
  quoted-printable (valid, as before).

## [v4.35.0] – 2026-10-08

Area release for `src/table/` and `src/pagination/`: sort directions as enum, `final` classes, escaped labels, URL
encoded link parameters, no state in the head renderer, typed columns. The extension points of the area are
`DbResultTable`, `SmartTable`, `AbstractTableColumn`, `TableHeadRenderer`, `TableFilter` and `AbstractTableFilterField`
(documented in their class comments); everything else is `final`. Search your project for `SORT_ASC`, `SORT_DESC`,
`OPPOSITE_SORT_DIRECTION`, `getCurrentSortDirection(`, `isOrderAble`, `orderAscending`, `totalAmountMessage_`,
`additionalLinkParameters`, `renderColumnHead(`, `renderActionLinks(`, `FilterOption(`, `OptionsColumn(`,
`new TableHelper`, `extends ... Column`, `extends TextFilterField` and for `Pagination::render(`.

### ⚠️ Sort direction is `TableSortDirectionEnum`

`TableHelper::SORT_ASC`, `SORT_DESC` and `OPPOSITE_SORT_DIRECTION` are removed, `DbResultTable::getCurrentSortDirection()`
returns the enum. The values in the sort links (`?sort=<table>|<column>|ASC`) and in the session are unchanged.

Before:

```php
if ($table->getCurrentSortDirection() === TableHelper::SORT_DESC) {
    $opposite = TableHelper::OPPOSITE_SORT_DIRECTION[$table->getCurrentSortDirection()];
}
```

After:

```php
if ($table->getCurrentSortDirection() === TableSortDirectionEnum::DESC) {
    $opposite = $table->getCurrentSortDirection()->opposite();
}
```

### ⚠️ `OptionsColumn` and `TableHelper::createOptionsColumn()`: argument names, encoded labels

The arguments are named like the ones of every other column (`isSortable`, `sortAscendingByDefault`). The labels of the
options are text now and encoded; HTML is given as `HtmlText::fromHtml()`. The type of the options is
`array<int|string, HtmlText|string>`. A value of the column that is no key renders as encoded text as before; a column
value of another type than `int` / `string` (e.g. `float`, `bool`) no longer crashes `array_key_exists()`.

Before:

```php
new OptionsColumn(
    identifier: 'status',
    label: 'Status',
    options: ['active' => '<b>Active</b>', 'blocked' => 'Blocked'],
    isOrderAble: true,
    orderAscending: false,
);
```

After:

```php
new OptionsColumn(
    identifier: 'status',
    label: 'Status',
    options: ['active' => HtmlText::fromHtml(html: '<b>Active</b>'), 'blocked' => 'Blocked'],
    isSortable: true,
    sortAscendingByDefault: false,
);
```

### ⚠️ `ActionsColumn`: labels are encoded, `renderActionLinks()` is gone

The `label` of `addEditActionLink()` and `addDeleteLink()` is text and encoded (`<b>Edit</b>` shows the tags). The link
target and the `linkHtml` of `addIndividualActionLink()` stay HTML of the application (never user input); `[column]`
placeholders are still replaced by the encoded value of the row. Use an individual link for a label with HTML.
The protected method `renderActionLinks()` is removed, the class is `final`.

Before:

```php
$actionsColumn->addEditActionLink(linkTarget: 'edit/[ID]/', label: '<i class="icon"></i> Edit');
```

After:

```php
$actionsColumn->addIndividualActionLink(
    identifier: ActionsColumn::EDIT,
    linkHtml: '<a href="edit/[ID]/" class="edit"><i class="icon"></i> Edit</a>',
);
```

### ⚠️ `FilterOption`: encoded label, `label` is an `HtmlText`

The `label` argument accepts `string` (text, encoded, also the `value` attribute) or `HtmlText`; the property `$label` is
an `HtmlText` (before: `string`). `OptionsFilterField` takes `list<FilterOption>` (the runtime `LogicException` for other
elements is gone, PHPStan checks it), throws an `InvalidArgumentException` for two options with the same identifier or a
`defaultValue` that is no option, and forgets a stored option that does not exist anymore (before: `getWhereCondition()`
failed with an undefined offset and the page stayed broken until the session ended). The button labels of `TableFilter`
(`submitButtonLabel`, `resetLinkLabel`) are text and encoded.

Before:

```php
new FilterOption(identifier: 'new', label: '<b>New</b>', whereCondition: $condition);
```

After:

```php
new FilterOption(identifier: 'new', label: HtmlText::fromHtml(html: '<b>New</b>'), whereCondition: $condition);
```

### ⚠️ Link parameters are URL encoded where the link is built

`DbResultTable::addAdditionalLinkParameter()` keeps names and values as given (before: it encoded them, so
`DbResultTable::$additionalLinkParameters` held the encoded values); the sort links and `Pagination::render()` encode
them (`http_build_query()`). The `|` separators and `&` of the links are unchanged. The identifiers of table and column
in the sort links and the list identifier in the page links are URL encoded too (no change for `[A-Za-z0-9_]`). A
parameter named like the own one (`page` in the page links, `sort` in the sort links) is ignored (before it replaced
the page number). If you call `Pagination::render()` yourself with values that are already encoded, pass them as they
are now.

Before:

```php
Pagination::render(..., additionalLinkParameters: ['q' => urlencode($query)]); // href="?page=2|list&q=..." unencoded otherwise
```

After:

```php
Pagination::render(..., additionalLinkParameters: ['q' => $query]);
```

### ⚠️ `TableHeadRenderer::renderColumnHead()` gets the table, `SortableTableHeadRenderer` has no state

`renderColumnHead()` has a second argument `SmartTable $smartTable` (the renderer no longer remembers the table in a
property: it was uninitialized without `render()` and shared between tables). The cell around the content is
`renderHeadCell(columnCssClasses:, contentHtml:)` now. `SortableTableHeadRenderer` is `final` (configure it through its
public properties), `TableHeadRenderer` is a documented extension point.

Before:

```php
protected function renderColumnHead(AbstractTableColumn $abstractTableColumn): string
```

After:

```php
protected function renderColumnHead(AbstractTableColumn $abstractTableColumn, SmartTable $smartTable): string
```

### ⚠️ `SmartTable`: renamed properties

`$totalAmountMessage_oneResult` is `$totalAmountMessageOneResult`, `$totalAmountMessage_numResults` is
`$totalAmountMessageNumResults`.

### ⚠️ `final` classes and removed protected members

`final` now: `TableHelper`, `TableItem`, `TableItemCollection`, `Pagination`, `TablePaginationRenderer`,
`SortableTableHeadRenderer`, `ActionsColumn`, `BooleanColumn`, `CallbackColumn`, `DateColumn`, `DefaultColumn`,
`FileSizeColumn`, `OptionsColumn`, `StripHtmlTagsColumn`, `FilterOption`, `TextFilterField`, `DateFilterField`,
`OptionsFilterField` (their `protected` properties and `setValue()` are `private`). A project with its own column type
extends `AbstractTableColumn`. Nothing in `actra/backend` extends one of them.

### ⚠️ Missing column and invalid values throw

`TableItem::getRawValue()` of a column the row does not have throws an `InvalidArgumentException` naming the columns of
the row (before: PHP warning and `null`, so an exception through the error handler). `TableItem::getScalarValue()` is new
(a scalar or `null`, else `UnexpectedValueException`). `DateColumn` throws an `UnexpectedValueException` for a value that
is no date (before: `DateMalformedStringException`). `DbResultTable` and `Pagination::render()` throw an
`InvalidArgumentException` for less than one item per page (before: a `DivisionByZeroError` or a broken query).

### Fixed

- `TableHelper::createTable()` without `tableHeadRenderer:` threw a `TypeError`: it takes the plain
  `TableHeadRenderer` now.
- Page links: `Pagination::render()` put values of `additionalLinkParameters` unencoded and unescaped into the `href`
  (an HTML attribute); the links are built with `http_build_query()` now. The links of a current page beyond the last
  page (a stored page of a result that got smaller) no longer lead to pages that do not exist (previous goes to the last
  page, next is disabled); a current page below 1 has no previous link. The list of pages is no longer built by a loop
  over all pages (millions of entries).
- Cell values and filter values that look like placeholders (`[pagination]`, `[filter]`, `[footer]`) were replaced by
  the pagination or filter HTML of the `DbResultTable`: all placeholders are replaced in one pass over the template now.
- `?page=` with a number whose offset does not fit into an integer ended in a `TypeError`: such a page is ignored.
- `ActionsColumn`: a value that contains the placeholder of another column (`[secret]`) got it replaced by that column
  (one pass now); a hide value (`hideValue`) is compared with the text of the column, so a number column (`ID` = `42`
  with `hideValue: '42'`) hides the delete link (before: the link stayed because `42 === '42'` is false); columns that
  hold arrays are skipped instead of crashing.
- `FileSizeColumn` threw a `TypeError` for numeric strings (a `BIGINT` as the database delivers it) and says which
  column holds something else; `StripHtmlTagsColumn` threw a `TypeError` for `NULL` and numbers (empty cell and text).
- The filter fields encode the identifier in the `name` / `id` attributes and the date value; `DateFilterField` throws a
  `LogicException` (not an error about `null`) when asked for a condition without a date; `TextFilterField` throws when
  the column expression cannot be normalized; the filter form action and reset link encode the identifiers.

## [v4.34.0] – 2026-10-08

Area release for `src/db/`: no static state (connection pool, duplicate check of the settings and query log are gone),
validated settings, typed parameters (`list<float|int|string|null>`), `final` classes, no bound values in exception
messages, `DbQuery` throws `InvalidArgumentException`. `FrameworkDb` stays the extension point of the area. Search your
project for `FrameworkDb::getInstance(`, `new DbSettings(`, `identifier:`, `extends FrameworkDb`, `lastInsertId(`,
`ExecuteAndFetch(`, `DbQueryLogList`, `getQueryLog(`, `createInQuery(`, `prepare(`, `prepareSelect(`, `new DbSelectStmt(`,
`DbQuery::SORT_`, `DbRuntimeException`, `extends DbQuery` and for the types `DbRuntimeException`, `DbQuery`,
`DbSelectStmt`, `DbSettings` in `extends`.

### ⚠️ `FrameworkDb`: no connection pool, constructor takes connection parameters

`FrameworkDb::getInstance()` and the static registry per identifier are removed, the constructor is public and takes
`DbConnectionParameters` (the MySQL settings are converted with `DbConnectionParameters::forMysql()`). One object is one
connection: create it once and pass it on, or keep it in an accessor of your own, as `actra/backend` does with
`DB::get()`. A second connection to the same database is possible now (before, the second `new` with the same
identifier threw a `LogicException`).

Before:

```php
$db = FrameworkDb::getInstance(dbSettings: $dbSettings);

class DB extends FrameworkDb
{
    public static function get(): DB
    {
        return DB::$instance ??= new DB(dbSettings: $dbSettings);
    }
}
```

After:

```php
$db = new FrameworkDb(connectionParameters: DbConnectionParameters::forMysql(dbSettings: $dbSettings));

class DB extends FrameworkDb
{
    public static function get(): DB
    {
        return DB::$instance ??= new DB(
            connectionParameters: DbConnectionParameters::forMysql(dbSettings: $dbSettings),
        );
    }
}
```

A project that wants several named connections holds its own `array<string, FrameworkDb>`. Tests can connect to an
in-memory database: `new FrameworkDb(connectionParameters: new DbConnectionParameters(dsn: 'sqlite::memory:'))`.

### ⚠️ `DbSettings`: `final readonly`, no `identifier`, validated

The argument `identifier` and the static check for duplicate identifiers are removed (the identifier only served the
connection pool). The values are validated and an `InvalidArgumentException` (before: `LogicException`, which it
extends) names the wrong setting: host name and database name must not be empty or contain `;` or control characters
(they end up in the DSN), the charset only letters, digits and `_` (`utf-8` is still rejected with the old message), the
time names language must look like a MySQL locale (`de_CH`, `sr_RS@latin`) or be `null`. The init command quotes the
locale: `SET lc_time_names='de_CH', sql_safe_updates=1`. `sqlSafeUpdates` stays on by default.

Before:

```php
new DbSettings(identifier: 'main', hostName: $host, databaseName: $name, userName: $user, password: $password);
```

After:

```php
new DbSettings(hostName: $host, databaseName: $name, userName: $user, password: $password);
```

### ⚠️ `FrameworkDb::lastInsertId()` throws, `getLastInsertId()` returns the `int`

The override `lastInsertId(): int` broke the contract of `PDO::lastInsertId(): string|false`. `lastInsertId()` now
throws a `LogicException` that names the replacement, so every old call fails at its first use instead of silently
getting a `string`. The new `getLastInsertId(): int` throws an `UnexpectedValueException` if the driver returns
something else than an integer.

Before: `$id = $db->lastInsertId();` (`int`). After: `$id = $db->getLastInsertId();`

### ⚠️ `FrameworkDb`, `DbSelectStmt`: typed parameters, `prepare()` options

- Values of `select()`, `selectRows()`, `selectRow()`, `execute()`, `DbQuery` and `DbQueryData` are
  `list<float|int|string|null>` (PHPStan, no change at runtime). PDO binds every value of `execute(array)` as a string:
  a `false` became the empty string. Convert booleans to `0` / `1`.
- `prepare()` and `prepareSelect()` take `array $options = []` instead of `$options = null`; the workaround for the old
  PHP bug is gone. Before: `$db->prepare(query: $sql, options: null)`, after: leave the argument out.
- `select()` returns `list<stdClass>`, `execute()` and the others throw `DbRuntimeException`; `prepare()` wraps a
  failure into `DbRuntimeException` too (it did before, now also the `false` result).
- `createInQuery(array $paramArr)` is `createInQuery(array $values)` and throws an `InvalidArgumentException` for an empty
  list (`IN ()` is no valid SQL; before the database failed with a syntax error).
- `select()` and `execute()` are one code path with `prepareSelect()`: the time of the query log starts after
  `prepare()`.

### ⚠️ Query log per connection

`DbQueryLogList` was a static list for all connections of the process (`DbQueryLogList::add()` / `getLog()`). It is a
`final` instance class now, owned by the `FrameworkDb` (second constructor argument `queryLog:`, e.g. with a `Clock` for
tests); `FrameworkDb::getQueryLog()` returns its `list<DbQueryLogItem>`. `DbQueryLogItem::getExecutionTime()` throws a
`LogicException` for a query that is not finished (before: a meaningless number); items in the log are always finished.

Before: `DbQueryLogList::getLog()`. After: `$db->getQueryLog()`.

### ⚠️ `DbSelectStmt`: `executeAndFetch()`, `final`, log as argument

- `ExecuteAndFetch()` is renamed to `executeAndFetch()` (PHP method names are case-insensitive: the old call keeps
  working at runtime, the name in your code should change).
- The constructor argument `bool $logQuery` is `?DbQueryLogList $queryLog`; create the statement with
  `FrameworkDb::prepareSelect()`.
- The class is `final readonly`.

### ⚠️ `DbRuntimeException`: no bound values in the message, `final`

The message contained every bound value (`SQL-Parameters: "…", "…"`), i.e. personal data in the log and error pages.
It names the number of values only: `SQL-Parameters: 2 bound values (not shown)` (`none` without values; before:
`-none-`). The SQL string is still part of the message. The message of the database driver can contain values itself
(e.g. a duplicate entry): do not show it to users. `getCode()` is still the driver error code of a `PDOException`.
The class is `final`; the constructor (`$parameters:`) and `getPrevious()` are unchanged.

### ⚠️ `DbQuery`: `final`, `InvalidArgumentException`, no `SORT_*` constants

- `DbQuery` is `final` (nothing in yuf or `actra/backend` extends it). Tests that stubbed it use
  `DbQuery::createFromSqlQuery(query: 'SELECT id FROM item')`.
- Every invalid query, part or parameter count throws an `InvalidArgumentException` (before: `LogicException`, its
  parent class: `catch (LogicException)` keeps working).
- `DbQuery::SORT_ASC` and `SORT_DESC` are removed; `addOrderPart(ascending: bool)` is unchanged (the internal
  `DbSortDirectionEnum` has the two directions).
- `getTotalAmount()` throws an `UnexpectedValueException` if the count query returns no row and reads the count with
  `DbRow::getInt()`.
- `createFromSqlQuery()` and `addOrderPart()` etc. take `list<float|int|string|null>` parameters.
- The SQL of the query and of the added parts is trusted (documented); only values are bound.

### ⚠️ Other classes

`DbQueryData` and `DbQueryLogItem` are `final` too. `DbConnectionParameters` (`final readonly`), `DbStatementExecutor`, `DbQuerySectionEnum` and
`DbSortDirectionEnum` are new (the last three `@internal`). `TableHelper::createDbResultTable(params:)` is typed as
`list<float|int|string|null>`.

### Bug fixes (no code change needed)

- A failed `select()` / `execute()` no longer puts the bound values into the exception message (see above).
- `DbQueryLogItem::getExecutionTime()` of an unfinished item returned a negative number.
- The password of `DbSettings` and `DbConnectionParameters` is marked `#[SensitiveParameter]`: it does not show up in
  stack traces of these constructors.

## [v4.33.0] – 2026-10-08

Area release for `src/phone/` (port of libphonenumber, licence notice kept): typed metadata, no static caches, error
codes and number length results as enums, bug fixes. The consumer API is `PhoneNumber::createFromString()`,
`PhoneRenderer` and `PhoneParseException`; every other class of `src/phone/` is `@internal` now. Search your project for
`PhoneParseException::`, `->getCode()` on a `PhoneParseException`, `extends PhoneNumber`, `extends PhoneRenderer`,
`PhoneValidator`, `PhoneParser`, `PhoneMetaData`, `PhoneMatcher` and `PhoneConstants`.

### ⚠️ `PhoneParseException`: error as enum, `final`, based on `InvalidArgumentException`

The constants of the exception are replaced by `PhoneParseErrorEnum` (same numbers); the exception has the property
`error`. The error code of a number that is not possible (`createFromString()`) was `-1` without constant, it is
`PhoneParseErrorEnum::NOT_POSSIBLE` now. `getCode()` still returns the same numbers; `catch (PhoneParseException)` and
`catch (Exception)` keep working.

Before:

```php
try {
    $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: 'CH');
} catch (PhoneParseException $exception) {
    if ($exception->getCode() === PhoneParseException::EMPTY_STRING) {
        // ...
    }
}
```

After:

```php
try {
    $phoneNumber = PhoneNumber::createFromString(input: $input, defaultCountryCode: 'CH');
} catch (PhoneParseException $exception) {
    if ($exception->error === PhoneParseErrorEnum::EMPTY_STRING) {
        // ...
    }
}
```

| Before                                                         | After                                      |
|:---------------------------------------------------------------|:-------------------------------------------|
| `PhoneParseException::EMPTY_STRING` (0)                        | `PhoneParseErrorEnum::EMPTY_STRING`        |
| `PhoneParseException::INVALID_COUNTRY_CODE` (1)                | `PhoneParseErrorEnum::INVALID_COUNTRY_CODE` |
| `PhoneParseException::NOT_A_NUMBER` (2)                        | `PhoneParseErrorEnum::NOT_A_NUMBER`        |
| `PhoneParseException::TOO_SHORT_AFTER_IDD` (3)                 | `PhoneParseErrorEnum::TOO_SHORT_AFTER_IDD` |
| `PhoneParseException::TOO_SHORT_NSN` (4)                       | `PhoneParseErrorEnum::TOO_SHORT_NSN`       |
| `PhoneParseException::TOO_LONG` (5)                            | `PhoneParseErrorEnum::TOO_LONG`            |
| code `-1` (number is not possible for its country)            | `PhoneParseErrorEnum::NOT_POSSIBLE`        |

`new PhoneParseException(message: …, code: …)` is now `new PhoneParseException(message: …, error: …)`.

### ⚠️ `PhoneNumber` and `PhoneRenderer` are `final`; the other classes are `@internal`

`PhoneNumber` (already `readonly`) and `PhoneRenderer` cannot be extended anymore. Their signatures are unchanged:
`PhoneNumber::createFromString()`, `getNationalSignificantNumber()`, `PhoneRenderer::renderInternalFormat()` and
`renderInternationalFormat()` keep working as before.

The classes behind them are `final` and `@internal` and changed without further notice:

- `PhoneValidator`: `isPossibleNumber()` is an instance method (`new PhoneValidator(metaDataRepository: …)`);
  `testNumberLength()` returns `PhoneLengthResultEnum` instead of the int constants `IS_POSSIBLE`, `TOO_SHORT`, `TOO_LONG`,
  `IS_POSSIBLE_LOCAL_ONLY`, `INVALID_LENGTH` (same numbers as backed values).
- `PhoneParser::getInstance()`, `PhoneMetaData::getForRegion()` and `getForRegionOrCallingCode()`: no static cache and no
  singleton anymore. `PhoneMetaDataRepository` loads the metadata and keeps it as long as the repository lives;
  `PhoneMetaDataLoader` narrows the generated arrays once into the readonly value objects `PhoneMetaData`, `PhoneDesc`
  and `PhoneFormat`.
- `PhoneConstants::FROM_NUMBER_WITH_PLUS_SIGN`, `FROM_NUMBER_WITH_IDD`, `FROM_DEFAULT_COUNTRY` are
  `PhoneCountryCodeSourceEnum`; `PhonePatterns::REGEX_FLAGS` is gone (`PhoneConstants::REGEX_FLAGS`);
  `PhoneNumberNormalizer` is new (was private in `PhoneParser`).

Use `PhoneNumber::createFromString()` instead of the internal classes. Each call loads the metadata files of the
regions it needs (plain PHP arrays, OPcache holds them; about 30 µs per call), nothing is cached between calls.

### Bug fixes (no code change needed)

- The internal format lost the Italian leading zero: an Italian landline `02 1234 5678` was rendered as
  `+39.212345678`, which reads as a different (invalid) number. It is `+39.0212345678` now and parsed back to the same
  number. `PhoneNumberField` stores that format: values already stored for Italian (and Vatican) landlines are not
  repaired.
- Characters at the end of a number that are neither a digit nor a letter were not stripped (the pattern used Java
  syntax that PCRE does not know). `044 668 18 00;`, `044 668 18 00!`, `044 668 18 00,` and `"044 668 18 00"` are valid
  numbers now (they were `NOT_A_NUMBER`). The cut position is a character position, so numbers in full-width digits
  work too.
- A number with an unknown country calling code in the constructor of `PhoneNumber` crashed the validation with a
  `TypeError`; it is "not possible" now.
- After the `＋` (full-width plus) of an unknown country calling code, the error message is the right one.

## [v4.32.0] – 2026-10-08

### Class files with the right case

`CsvFile`, `SimpleXmlExtended`, `FrameworkDb` and `SmtpMailer` were renamed in v4.18.0, but their files kept the old
case in the repository (`CSVFile.php`, `SimpleXMLExtended.php`, `FrameworkDB.php`, `SMTPMailer.php`). On a
case-sensitive file system (Linux) the autoloader of v4.18.0 to v4.31.0 does not find these four classes. The files
have the right names now. No code change needed.

Area release for `src/common/`: `final` classes, strict types instead of `mixed` and `false`, no SQL built from search
text, protection against CSV injection and path traversal, error codes of the email validation as enum. Search your
project for `getBooleanQuery(`, `addWildcardToString(`, `utf8Encode`, `new CsvFile(`, `lastErrorCode`, `->addArray(`,
`->addXml(`, `FileHandler::getExtension(`, `StringUtils::` with the named arguments listed below, and for `extends` of
the classes in the list of final classes.

### ⚠️ `SearchHelper::getBooleanQuery()` and `addWildcardToString()` are removed

Both were deprecated since v4.6.0. `getBooleanQuery()` put the search words into the SQL (a `?` in the search text broke
the query, `%` and `_` were wildcards, field names were not checked); `addWildcardToString()` did not escape `%` and
`_`. The replacements bind every user value as parameter:

Before:

```php
$dbQuery->addWherePart(
    wherePart: $searchForm->searchHelper->getBooleanQuery(
        spaceSeparatedFieldNames: 'person.firstName person.lastName',
        queryText: $searchQuery,
    ),
    parameters: [],
);
```

After:

```php
$data = SearchHelper::createBooleanQuery(
    spaceSeparatedFieldNames: 'person.firstName person.lastName',
    queryText: $searchQuery,
);
$dbQuery->addWherePart(wherePart: $data->query, parameters: $data->params);
```

`addWildcardToString($text)` is replaced by `createSqlFilters()` (table filters), `createSqlSearch()` (words over
columns) or `createBooleanQuery()`; none of them needs a wildcard added by hand.

### ⚠️ `CsvFile`: formula protection, renamed argument, `final`

A text cell that starts with `=`, `+`, `-`, `@`, a tab or a carriage return and is no number now gets a leading `'`, so
a spreadsheet application does not run it as formula (OWASP, CSV injection). Numbers and numeric strings (`-5`, `+5`,
`1e3`) are written as they are. This applies to the headers, too.

Before: `=SUM(A1:A2)` is written as `=SUM(A1:A2)`; `+41 79 000 00 00` as `"+41 79 000 00 00"`.

After: `'=SUM(A1:A2)` and `"'+41 79 000 00 00"`.

`new CsvFile(…, protectAgainstFormulas: false)` switches it off for data that is known to be safe. The argument
`utf8Encode` (it only wrote the byte order mark) is renamed:

```php
// Before
new CsvFile(fileName: 'export.csv', headersList: $headers, utf8Encode: false);
// After
new CsvFile(fileName: 'export.csv', headersList: $headers, addByteOrderMark: false);
```

`CsvFile` is `final`. The cells are typed (`array<array-key, bool|float|int|string|null>`), so a cell with an object or
array no longer reaches `fputcsv()` untyped. `delimiter` and `enclosure` must be one character each (an
`InvalidArgumentException` in the constructor; before, `fputcsv()` failed with a `ValueError` when the file was written).
`createTemporaryFile()` creates the file readable by the owner only (mode 0600, via `tempnam()`), still in the temporary
directory with the suffix `.csv`, and throws a `RuntimeException` if the file cannot be written (before the errors were
ignored or a `TypeError`). `pushDownloadAndExit()` removes the temporary file at the end of the script (before, every
download left a copy of the data in the temporary directory). `stringToArray()` trims the first line, too (a `\r` of a
Windows line ending stayed in the last cell of the header row).

### ⚠️ `StringUtils`: parameter names and exceptions

Parameters named `$str`, `$newStr`, `$tokens` and the like are renamed (named arguments), `final`:

| Before | After |
|:-------|:------|
| `afterFirst(str:, after:)`, `beforeFirst(str:, before:)`, `beforeLast(str:, before:)`, `afterLast(str:, after:)` | `…(string:, …)` |
| `between(str:, start:, end:)` | `between(string:, start:, end:)` |
| `insertBeforeLast(str:, beforeLast:, newStr:)` | `insertBeforeLast(string:, beforeLast:, newString:)` |
| `tokenize(stringToSplit:, tokenToSplitString:)` | `tokenize(string:, delimiters:)` |
| `explode(tokens:, str:)` | `explode(separators:, string:)` |

`utf8ToPunycodeEmail()` and `punycodeToUtf8Email()` throw an `InvalidArgumentException` if the domain cannot be
converted (before: an address without domain, `user@`, or a `ValueError` for an empty domain). `explode()` throws an
`InvalidArgumentException` for an empty separator (was a `ValueError`). `tokenize()` and `explode()` return `list<string>`;
`tokenize()` no longer uses the process-wide state of `strtok()`.

Bug fixes (results change only for the inputs that were wrong before):

- `between()` returns `null` if `$start` is not contained (it returned text after a wrong position).
- `insertBeforeLast()` returns the string unchanged if the delimiter is not contained (it appended the new text and the
  delimiter).
- `breakUp()` cuts within the first `$atIndex` characters (it always used 50).
- `formatBytes(0.5)` gives `0.5 B` (a warning and `512 ` before); the unit is found without floating-point rounding.
- `randomString()` returns exactly the required length (3 or 4 characters minimum before: `randomString(1, true)` had
  three) and shuffles with `random_int()` instead of `str_shuffle()` (a predictable generator).

`urlify()` still depends on the locale of the PHP process for characters like `ü` (`iconv` transliteration).

### ⚠️ `FileHandler`

- `FileHandler::getExtension()` returns `string` (was `false|string`, but never `false`). A name without a dot gives an
  empty string; before it returned the name without its first character (`README` gave `EADME`).

  ```php
  // Before
  $extension = FileHandler::getExtension(filename: $name);
  $contentType = ContentType::createFromFileExtension($extension === false ? '' : $extension);
  // After
  $contentType = ContentType::createFromFileExtension(FileHandler::getExtension(filename: $name));
  ```
- `FileHandler::removeFile()` throws an `InvalidArgumentException` if `$token` contains other characters than letters,
  digits, `.`, `_` and `-`, or the extension of `$filename` other characters than letters and digits (before, a token or
  file name with `../` removed files outside of `$directory`). The directory may be given with or without trailing
  separator, a directory is never removed.
- `renderFileSize()` of a directory gives `0 KB` (it gave the size of the directory entry).
- `FileHandler` is `final readonly`.

### ⚠️ `ValidatedEmailAddress`: error codes as enum, resolver

- `lastErrorCode` is a `?EmailAddressErrorEnum` (before a string, and reading it without an error was an `Error`: the
  property was uninitialized); `lastErrorMessage` is `''` without an error; `validatedValue` is `''` for an invalid
  syntax (before uninitialized). The values of the enum are the old codes.

  ```php
  // Before
  if ($address->lastErrorCode === 'noDnsRecords') { … }
  // After
  if ($address->lastErrorCode === EmailAddressErrorEnum::NO_DNS_RECORDS) { … }
  ```

  | Before | After |
  |:-------|:------|
  | `'emptyValue'`, `'atCharacterError'`, `'invalidDomainName'` | `EMPTY_VALUE`, `AT_CHARACTER`, `INVALID_DOMAIN_NAME` |
  | `'invalidSyntax'`, `'invalidCharacters'` | `INVALID_SYNTAX`, `INVALID_CHARACTERS` |
  | `'dns_get_record'`, `'noDnsRecords'`, `'fsockopen'`, `'notResolvable'` | `DNS_GET_RECORD`, `NO_DNS_RECORDS`, `FSOCKOPEN`, `NOT_RESOLVABLE` |

- The DNS and port 25 check moved into the interface `MailDomainResolver` (default `SystemMailDomainResolver`, passed as
  second constructor argument `mailDomainResolver:`), so the check is testable and a project can replace it.
  `SystemMailDomainResolver` does not connect to addresses of private and reserved ranges any more (an entered domain
  could point to the internal network): such a domain without MX record is `NOT_RESOLVABLE`. New classes:
  `EmailAddressErrorEnum`, `EmailAddressError`, `MailDomainResolver`, `SystemMailDomainResolver`.
- `ValidatedEmailAddress` is `final`.
- Bug fix: an address without domain (`anna@`) no longer throws a `ValueError` (a server error in a form), it is
  invalid with `INVALID_DOMAIN_NAME`.

### ⚠️ `SimpleXmlExtended`

- `addArray()` and `addXml()` return `void` (they always returned `true`). `addCdata()` takes
  `string|int|float|bool|null`. `addArray()` throws an `InvalidArgumentException` for a value that is no text, number,
  boolean, `null`, array or object (a resource; before it failed with a `TypeError` or wrote garbage).
- `SimpleXmlExtended` is `final`.
- Bug fixes: `convertXmlToArray()` no longer raises PHP warnings for invalid XML (it returns `false`, as before), does
  not load network resources, and converts every empty element below the root to `''` (the first empty child stayed
  `[]`); `addArray(includeNull: false)` also leaves out `null` in nested arrays.

### `JsonUtils`

- `JsonUtils` is `final`. `decodeFile()` throws a `RuntimeException` (was `Exception`) for a missing or unreadable file.
  `decodeJsonString()` and `decodeFile()` throw an `UnexpectedValueException` for JSON that is neither object nor array
  (`"text"`, `5`, `null`; was a `TypeError`).
- `minify()` removes all whitespace outside of strings: the part after the last string, comment or line break kept its
  whitespace before. A `//` comment at the end of the text without line break is removed (its text was kept, which made
  the JSON invalid). No code change needed.

### `UrlHelper`, `LogFile`, other classes

- `UrlHelper` is `final`. `generateAbsoluteUri()` throws an `InvalidArgumentException` for a URI that cannot be parsed
  (was a `TypeError`) and gives `//host/path` the protocol of the request (`https://host/path`; before unchanged). It
  does **not** check the target: never pass an unchecked value from the user to `HttpResponse::redirectAndExit()` (open
  redirect), allow only relative paths or whitelisted hosts.
- `LogFile` files are named `<name>-<His>-<16 hex characters>.log` (before `<name>-<uniqid>.log`; `uniqid()` is not
  random enough and the names did not sort).
- `SearchHelper` is `final`; the column names of `createSqlSearch()` and the field names of `createBooleanQuery()` are
  matched with the end of the string only (`"name\n"` was valid); `checkDate()` catches only an invalid date.
- `BooleanSearchOperatorEnum` is `@internal`. `TimeOfDay` and `CountryCodeEnum` are unchanged.

### ⚠️ `final` classes

`CsvFile`, `FileHandler`, `JsonUtils`, `SearchHelper`, `SimpleXmlExtended`, `StringUtils`, `UrlHelper` and
`ValidatedEmailAddress` are `final`. Nothing in `actra/backend` extends them. `MailDomainResolver` is the only extension
point of the area.

## [v4.31.0] – 2026-10-08

Area release for `Core`, `src/core/`, `src/request/` and `src/response/`: typed environment settings, an interface for
the logger, no secrets in the error log, `final` classes, strict types instead of `mixed` and `false`. Search your
project for `Core::config(`, `new Logger(`, `ErrorHandler::register`, `extends` of the classes in the list below,
`HttpResponseContent`, `getFirstRoute`, `getFirstLanguage` and for code that reads the error log.

### ⚠️ `Core::config()` is removed, the environment settings are typed

`Core` reads `.env.php` into an `EnvironmentSettings` object, available as `$core->environmentSettings`
(`public readonly`). The six keys `Core` needs (`defaultErrorReporting` int, `defaultTimeZone` string,
`allowedDomains` list of strings, `logEmailRecipient` string, `debug` bool, `robots` string) are checked once at the
start: a missing key or a wrong type throws an `UnexpectedValueException` naming the key (before: `TypeError` or a PHP
warning later). The own keys of a project (used as given, e.g. the flat key `'mailer.hostname'`) are read with the typed
getters `getString()`, `getInt()`, `getBool()` and `getStringList()`; they throw an `UnexpectedValueException` naming
the key and the expected type for a missing key or a wrong type, `has()` tells if a key exists. The static
`Core::config(key)` and `Core::$config` (`mixed`) are gone.

Before:

```php
final class EnvSettings
{
    public static function getMailerHostname(): string
    {
        return Core::config(key: 'mailer.hostname');
    }
}
```

After (the project passes `$core->environmentSettings` to its settings class; no static access):

```php
final readonly class EnvSettings
{
    public function __construct(private EnvironmentSettings $environmentSettings) {}

    public function getMailerHostname(): string
    {
        return $this->environmentSettings->getString(key: 'mailer.hostname');
    }
}
```

Also: `Core` is `final` and registers the autoloader for the yuf classes before it reads the environment file (the
autoloader path of the application follows once the directories exist); the directories are created with a check
(`RuntimeException` if one cannot be created); a missing `$_SERVER['DOCUMENT_ROOT']` is an `UnexpectedValueException`;
`Core::$cspPolicySettings` is `public private(set)` (read access unchanged).

### ⚠️ `Logger` is an interface, `FileLogger` writes the files

Before:

```php
use actra\yuf\core\Logger;

$logger = new Logger(logEmailRecipient: '', logDirectory: $dir, httpRequest: $core->httpRequest);
$core->prepareHttpResponse(logger: $logger, routeCollection: $routes);
```

After:

```php
use actra\yuf\core\FileLogger;

$logger = new FileLogger(logEmailRecipient: '', logDirectory: $dir, httpRequest: $core->httpRequest);
$core->prepareHttpResponse(logger: $logger, routeCollection: $routes);
```

`Logger` has `logException()` and `logMessage()`: implement it for another destination (tests use
`RecordingLogger`). `lastIssueIsNew()` stays on `FileLogger`. New optional constructor argument `maxLogSize:` (default
10 MB, `0` for no limit). A missing log directory throws an `InvalidArgumentException` (was `Exception`); the
directory may be given without trailing separator.

### ⚠️ The error log contains no secrets

The entry of an issue no longer dumps `$_SERVER`, `$_GET`, `$_POST`, `$_FILES` and `$_COOKIE`. It contains:

- the request line (method and path, **without query string**), host, IP address, user agent and referrer (without
  query string and fragment); control characters are replaced by `?`;
- these server variables only (allow-list): `REQUEST_METHOD`, `SERVER_PROTOCOL`, `HTTPS`, `HTTP_HOST`, `SERVER_NAME`,
  `SERVER_PORT`, `REMOTE_ADDR`, `HTTP_USER_AGENT`, `HTTP_ACCEPT_LANGUAGE`, `CONTENT_TYPE`, `CONTENT_LENGTH`,
  `SCRIPT_NAME` (not `REQUEST_URI`, `QUERY_STRING`, `HTTP_COOKIE`, `HTTP_AUTHORIZATION` or the environment of the
  server);
- the query and post parameters; the value of a parameter whose name contains `password`, `token`, `secret`, `csrf`,
  `key` or `auth` (case-insensitive, at any depth, an array as a whole) is replaced by `***`;
- the uploaded files (name, type, size, error code; no temporary path);
- the names of the cookies, never their values (session ID).

Tools that parse the log (`$_SERVER = Array`, `$_COOKIE = Array`) have to be adapted: the sections are now called
`Server variables (allow-list):`, `Query parameters (secrets masked) =`, `Post parameters (secrets masked) =`,
`Uploaded files =` and `Cookie names =`. The debug page (debug mode) is unchanged.

### ⚠️ `ErrorHandler::register()` is an instance method

`ErrorHandler` is `final` and `@internal`; the static registration guard is gone (`Core` registers it once).

Before: `ErrorHandler::register();` After: `new ErrorHandler()->register();`

### ⚠️ `final` classes and extension points

`final`: `Core`, `ContentHandler`, `ContentType`, `MimeType`, `Language`, `LanguageCollection`, `LocaleHandler`, `Route`,
`RouteCollection`, `RequestHandler`, `HttpResponse`, `InputParameter`, `InputParameterCollection`, `JsonRequestBody`,
`ErrorHandler`, `HttpErrorResponseContent`, `HttpSuccessResponseContent`. A project that extends one of them composes
instead (nothing in `actra/backend` extends them). The extension points stay: `BaseView` (abstract), the interfaces
`ViewFactory` and `Logger`. `ContentType`, `MimeType`, `Language`, `Route`, `InputParameter` and `JsonRequestBody` are
`readonly`.

`HttpResponseContent` is a `final readonly` class with a public constructor (`new HttpResponseContent(content:)`);
`HttpErrorResponseContent` and `HttpSuccessResponseContent` no longer extend it, they only provide the static factories
that return it (so code that type-hints `HttpResponseContent` keeps working).

The type constants of `ContentType` (`HTML`, `JSON`, …) and `MimeType` stay constants: `ContentType::$type` is any file
extension (`pdf`, `zip`, …) and `MimeType::$value` any of about 600 MIME types, so they are no fixed sets.

### ⚠️ Changed exceptions and signatures

| Before | After |
|:-------|:------|
| `RouteCollection::getFirstRoute()` on an empty collection: `TypeError` (`false`) | `LogicException` |
| `LanguageCollection::getFirstLanguage()` on an empty collection: `TypeError` | `LogicException` |
| `LocaleHandler::getText()` for a missing key: `Exception` | `OutOfBoundsException` (message unchanged) |
| language file with a text that is no string | `UnexpectedValueException` when the file is loaded |
| `LocaleHandler::getText(replacements:)` untyped | `array<string, string>` |
| `ContentHandler::setContentType()` for a type without charset: `Exception` | `InvalidArgumentException` |
| `JsonRequestBody::fromString()` for JSON that is no object (`[1]`, `"x"`, `null`): `TypeError` | `InvalidArgumentException` |
| `JsonRequestBody::getOptionalArray()`, `getRequiredArray()` | documented as `list<mixed>` |
| `HttpResponse::createHtmlResponse()` with a policy and `nonce: null`: `TypeError` | `LogicException` |
| `RequestHandler::$language` public, writable | `public private(set)` |
| `RequestHandler::$defaultRoutesByLanguage` `?RouteCollection` | `RouteCollection` (never `null`): `?->` becomes `->` |
| request of "/" without any default route: `Error` | `LogicException` ("set `isDefaultForLanguage: true` on a route…") |
| `BaseView::setContentByXmlObject()` with an XML object that cannot be converted: `TypeError` | `LogicException` |
| `HttpResponse::createResponseFromFilePath()` for a file whose time or size cannot be read | `RuntimeException` |

New: `FileLogger`, `EnvironmentSettings`, `RequestHandler::findRouteForRootRequest()` (the route that "/" is redirected
to), `HttpResponse::getHeader()`, `listHeaders()` and the public property `httpStatusCode`, an optional `clock:`
(`Clock`) of the three `HttpResponse::create…()` factories (Last-Modified and Expires).

### ⚠️ `HttpResponse`: a 304 is sent by `sendAndExit()`

Before, the factories (`createHtmlResponse()`, `createResponseFromString()`, `createResponseFromFilePath()`) sent a 304
response and ended the script from inside the constructor when the request had the current version
(`If-None-Match`, `If-Modified-Since`). Now the response has the status 304 (and no content headers) and
`sendAndExit()` sends it without a body (before: the body was sent, too). Code that creates a response and does more work
before `sendAndExit()` now runs that work for a 304, too.

### Bug fixes (no code change needed)

- `HttpResponse::createResponseFromFilePath()` answers a missing file with 404 (was 403).
- The ETag of the responses is a SHA-256 hash instead of MD5 (clients load the content once more).
- `Route::loadLocalizedText()` of a route without language does nothing (was a PHP error).
- HTML responses send `Content-Language` of the request language (`RequestHandler::$language`), or no such header
  without a language. `ContentType::createHtml()` hard-coded `de` before; it has an optional `languageCode:` now (default
  `null`), and `HttpResponse::createHtmlResponse()` an optional `languageCode:`.
- `FileLogger` rotates a ticket file of the maximum size to the next free `<file>.<number>` without errors for
  unreadable directories (`RuntimeException`) and no longer compares numbers as strings.
- `LocaleHandler::loadLanguageFile()` of an unreadable file throws a `RuntimeException`.

## [v4.30.0] – 2026-10-08

The session is an object now: `Core` creates one `Session` per request and passes it explicitly to everything that needs
it. There is no static session state any more (`AbstractSessionHandler::getSessionHandler()`, `AuthSession`,
`CsrfToken`, `FormNameRegistry`, the static `saveToSession()` / `getFromSession()` of `DbResultTable`) and nothing in
yuf reads `$_SESSION` except `NativeSessionStorage` and the session handler. Search your project for `$_SESSION`,
`AuthSession::`, `CsrfToken::`, `AbstractSessionHandler::`, `FormNameRegistry`, `new Form(`, `new TableFilter(`,
`createDbResultTable`, `SearchHelper::create`, `SessionFileUploadStorage`, `resetInstance` and for every class that
extends `Authenticator`, `MicrosoftAuthenticator`, `AuthUser`, `AbstractSessionHandler` or `ExceptionHandler`.

### ⚠️ Users are logged out once, tables lose their state

All data of yuf moves below one key, `$_SESSION['yuf']`, in sections (`SessionSectionEnum`). Existing sessions keep the
old keys, which are ignored: **every user is logged out once** and **tables, table filters, search forms and upload
lists start from their defaults**. The preferred language is forgotten, too (it was a top-level key).

| Before (top-level key) | After |
|:-----------------------|:------|
| `sessionCreated`, `trustedRemoteAddress`, `trustedUserAgent`, `lastActivity`, `preferredLanguage` | `yuf.handler.<same key>` |
| `auth_userSession` (`isLoggedIn`, `authSessionId`) | `yuf.auth` (`isLoggedIn`, `authSessionId`) |
| `csrftoken` | `yuf.csrf.token` |
| `table[<identifier>]` (`sort_column`, `sort_direction`, `pagination_page`) | `yuf.tables[<identifier>]` (`sortColumn`, `sortDirection`, `paginationPage`) |
| `columnFilter[<filter>_<field>]` | `yuf.tableFilters.fields[<filter>_<field>]` |
| `tableFilter[<filter>]` (own filters through `TableFilter::getFromSession()`) | `yuf.tableFilters.filters[<filter>]` |
| `searchHelper[<instance>]` | `yuf.search[<instance>]` |
| `<pointer>` (upload pointers, top level) | `yuf.uploads[<pointer>]` |

Project data stays where it is (top level) and cannot collide with `yuf`: the key `yuf` is reserved and `Session::set()`
throws an `InvalidArgumentException` for it. Code that reads the old keys directly (`$_SESSION['csrftoken']`, …) has to
use the new API.

### ⚠️ Static → instance

| Before | After |
|:-------|:------|
| `AbstractSessionHandler::register()`, `enabled()`, `getSessionHandler()` | removed. `$core->session` (`?Session`, `null` with `individualSessionHandler: false`), `$core->sessionHandler`, `$this->context->session`, `$this->context->sessionHandler` |
| `AbstractSessionHandler::clearUserData()` (static) | `$session->clearUserData()` (keeps only `yuf.handler`) |
| `$handler->setPreferredLanguage()`, `getPreferredLanguageCode()` | removed: `SessionPreferredLanguage(session:)` with `set()` / `getCode()` (`RequestHandler` uses it) |
| `AuthSession::logIn()`, `logOut()`, `isLoggedIn()`, `getAuthSessionId()` (static) | `final readonly class AuthSession(Session $session)`: the same methods as instance methods, `$this->context->authSession` (`null` without session); new `getSessionId()` |
| `CsrfToken::getToken()`, `validateToken()` | `CsrfTokenSource::getToken()`, `isValid()` (`new SessionCsrfTokenSource(session: $session)`, `$context->formContext->csrfTokenSource`) |
| `CsrfToken::getToken(forceNew: true)` | `SessionCsrfTokenSource::renew()` |
| `CsrfToken::renderAsHiddenPostField()` | `CsrfHiddenFieldRenderer::render(csrfTokenSource:)` (empty string for `null`) |
| `CsrfToken::getFieldName()`, `CsrfToken::CSRFTOKENSTORAGE` | `CsrfTokenSource::FIELD_NAME` (`'csrftoken'`) |
| `DbResultTable::saveToSession()`, `getFromSession()` (static) | removed; the table, filter and fields keep their state through `TableSessionState` (`@internal`) |
| `FormNameRegistry` | removed (no check for duplicate form names) |
| `SmartTable`, `TableFilter`, `AbstractTableFilterField` identifier registries | removed (no `LogicException` for duplicate identifiers) |
| single-instance guards of `AuthUser` and `Authenticator`, `AuthUser::resetInstance()` | removed (several instances are allowed, no reset needed in tests) |
| `HttpResponse::redirectAndExit(setSameSiteCookieTemporaryToLax: true)` | `sameSiteLaxSessionHandler: $core->sessionHandler` (the handler that changes the cookie) |

### ⚠️ Changed constructors and methods

| Class / method | Change |
|:---------------|:-------|
| `Form::__construct()` | new required first argument `context:` (`$this->context->formContext`); `csrfTokenSource:` removed (a project that needs another source builds its own `FormContext`) |
| `Form::validate()`, `Form::isSent()` | `input:` is optional again (default: `FormInput::fromHttpRequest()` of the request of the context, post data for a POST form, query string for a GET form); passing it still works |
| `CsrfTokenField::__construct()` | `tokenSource:` is required (no session default) |
| `Authenticator::__construct()` | new required `authSession:` after `httpRequest:`; subclasses pass it on (`$this->context->authSession`, `null` without session: no login possible) |
| `MicrosoftAuthenticator::__construct()` | new required `authSession:` after `httpRequest:` and `sessionHandler:` after `maxAllowedWrongPasswordAttempts:` (`$this->context->sessionHandler`, for the SameSite change of the redirect) |
| `AuthSession::getAuthSessionId()` | throws a `LogicException` when nobody is logged in (before: the stored ID of a logged-out session, `0` after a logout, `UnexpectedValueException` without ID) |
| `ViewContext::__construct()` | new required `session:`, `sessionHandler:`, `authSession:` (all `?`) and `formContext:` after `httpRequest:` |
| `RequestHandler::__construct()` | new required `session:` (`?Session`, last) |
| `HtmlDocument::__construct()` | new required `csrfTokenSource:` (last; created by `ContentHandler`) |
| `ExceptionHandler` | new `setSession(session:, csrfTokenSource:)`, called by `Core` once (like `setRequestHandler()`); `ExceptionHandlerContext` is unchanged |
| `AbstractSessionHandler` / `FileSessionHandler` | constructors unchanged; the data of the handler is in `yuf.handler`; `register()`, `enabled()`, `getSessionHandler()`, `clearUserData()`, `setPreferredLanguage()`, `getPreferredLanguageCode()` removed |
| `SessionFileUploadStorage::__construct()` | new required first argument `session:` |
| `SessionFileUploadStorage::forHttpRequest()` | new required first argument `session:` |
| `DbResultTable::__construct()` | new required `session:` after `httpRequest:` |
| `TableHelper::createDbResultTable()` | new required `session:` after `httpRequest:` |
| `TableFilter::__construct()` | new required `session:` and `csrfTokenSource:` (`?CsrfTokenSource`) after `httpRequest:`; the fields take the session from their filter |
| `SearchHelper::create()` | new required `session:` |
| `HtmlDocument` / `ExceptionHandler` placeholder `csrfField` | rendered through `CsrfHiddenFieldRenderer`; empty without session |

### Before / after

A view with a form:

```php
// before
$form = new Form(name: 'order');
$input = FormInput::fromHttpRequest(httpRequest: $this->context->httpRequest, methodPost: true);
if ($form->validate(input: $input)) {
    // …
}

// after
$form = new Form(context: $this->context->formContext, name: 'order');
if ($form->validate()) { // reads the request of the context
    // …
}
```

Form names must be unique per page: yuf no longer checks it (the name is the sent indicator).

A table with a filter:

```php
// before
$httpRequest = $this->context->httpRequest;
$table = TableHelper::createDbResultTable(
    identifier: 'users',
    db: $db,
    selectQuery: $sql,
    templateEngine: $this->context->templateEngine,
    httpRequest: $httpRequest,
    tableFilter: new TableFilter(identifier: 'usersFilter', httpRequest: $httpRequest),
);

// after
$httpRequest = $this->context->httpRequest;
$session = $this->context->session; // Session, not null: tables remember sorting, page and filter in the session
$table = TableHelper::createDbResultTable(
    identifier: 'users',
    db: $db,
    selectQuery: $sql,
    templateEngine: $this->context->templateEngine,
    httpRequest: $httpRequest,
    session: $session,
    tableFilter: new TableFilter(
        identifier: 'usersFilter',
        httpRequest: $httpRequest,
        session: $session,
        csrfTokenSource: $this->context->formContext->csrfTokenSource,
    ),
);
```

Table, filter and search identifiers must be unique per page, too: they are the keys of the state in the session. Two
tables with the same identifier share sorting and page.

An own `AuthUser` singleton: the guard of `AuthUser` is gone, build the singleton on the `AuthSession` that you get
passed in:

```php
// before
final class MyAuthUser extends AuthUser
{
    public static function get(): MyAuthUser
    {
        return MyAuthUser::$instance ??= MyAuthUser::createFromId(id: AuthSession::getAuthSessionId());
    }
}
$user = MyAuthUser::get();

// after
final class MyAuthUser extends AuthUser
{
    private static ?MyAuthUser $instance = null;

    public static function get(AuthSession $authSession): MyAuthUser
    {
        return MyAuthUser::$instance ??= MyAuthUser::createFromId(id: $authSession->getAuthSessionId());
    }
}
$user = MyAuthUser::get(authSession: $this->context->authSession);
```

(Better: create the user once in the bootstrap or view factory and pass it to what needs it, instead of a static
`get()`.) Tests no longer need `AuthUser::resetInstance()` or reflection on `Authenticator`.

An own `Authenticator` and the login:

```php
// before
final class MyAuthenticator extends Authenticator
{
    public function __construct(HttpRequest $httpRequest)
    {
        parent::__construct(httpRequest: $httpRequest, maxAllowedWrongPasswordAttempts: 5);
    }
}
$authenticator = new MyAuthenticator(httpRequest: $this->context->httpRequest);
if (AuthSession::isLoggedIn()) { … }
AuthSession::logOut();

// after
final class MyAuthenticator extends Authenticator
{
    public function __construct(HttpRequest $httpRequest, AuthSession $authSession)
    {
        parent::__construct(httpRequest: $httpRequest, authSession: $authSession, maxAllowedWrongPasswordAttempts: 5);
    }
}
$authenticator = new MyAuthenticator(
    httpRequest: $this->context->httpRequest,
    authSession: $this->context->authSession,
);
if ($this->context->authSession->isLoggedIn()) { … }
$this->context->authSession->logOut();
```

Own `$_SESSION` data (cart, order data, flash messages, `requestedPageAfterLogin`, breadcrumbs) goes through the
`Session`. Projects must not use `$_SESSION` any more:

```php
// before
$_SESSION['requestedPageAfterLogin'] = $uri;
$page = $_SESSION['requestedPageAfterLogin'] ?? '/';
unset($_SESSION['requestedPageAfterLogin']);

// after
$session = $this->context->session; // ?Session
$session?->set(key: 'requestedPageAfterLogin', value: $uri);
$page = $session?->getString(key: 'requestedPageAfterLogin') ?? '/';
$session?->remove(key: 'requestedPageAfterLogin');
```

`Session` stores strings, numbers, booleans, `null` and arrays of these (an object throws an
`InvalidArgumentException`: store its data as array); `getString()`, `getInt()`, `getFloat()`, `getBool()` and
`getArray()` return `null` for a missing key or another type and never write. A PHPStan rule against `$_SESSION`
(`disallowedSuperGlobals`) can enforce this in a project.

Tests and scripts use `new Session(storage: new ArraySessionStorage())` instead of a prepared `$_SESSION`.

A bootstrap file that registered the session handler itself: nothing to do, `Core::prepareHttpResponse()` creates the
handler, the `Session` and the `FormContext`; `$core->sessionHandler`, `$core->session` and `$core->formContext` are
available afterwards.

### ⚠️ Forms and table filters without session

`Core` with `individualSessionHandler: false` has no session: `$core->session`, `$context->session` and
`$context->authSession` are `null` and the `FormContext` has no `CsrfTokenSource`. A form then has **no CSRF field and
does not check a token**, and a `TableFilter` without token source accepts its posted input without a token. CSRF needs
an ambient credential (the session cookie) that the browser sends along on its own; without a session there is nothing
to abuse. Before, such a form rendered a token field that was never accepted (a POST form could not be submitted
without `removeCsrfProtection()`), and a table filter never accepted its input. A project that relied on the failure
(or that sends the session ID in another way) builds its own `FormContext` with a `CsrfTokenSource`. The `csrfField`
placeholder of pages and error pages is empty without session (before: empty until something wrote to `$_SESSION`,
then a token field that was never accepted). Tables, `SearchHelper` and the upload storage need a `Session` (an
`ArraySessionStorage` keeps their state for the request).

### Bug fixes (no change needed unless noted)

- No writes on read: `isLoggedIn()`, `getToken()` of an existing token, the first request of a table, a filter or a search
  form write nothing into the session any more (before: `isLoggedIn = false`, empty arrays for every filter field, the
  default sorting and page, the defaults of the search fields). Defaults are returned and only stored when they change
  (a choice of the user). A `SearchHelper` default that changes in your code now applies until the user made a choice.
- The preferred language is only written for a route with an explicit `language`. Before, a route without language
  overwrote it with the first available language.
- `AuthSession::getAuthSessionId()` throws when logged out (⚠️ see above).
- An empty or non-string stored CSRF token counts as missing. Checking a posted token never creates a token: without a
  stored token every posted token is invalid (before, the check created a token and failed).
- Upload pointers live in `yuf.uploads` and cannot overwrite or delete other session data any more (a pointer named
  `table` or `csrftoken` did).
- `DbResultTable::getCurrentSortDirection()` before the table was filled returns the default direction (before: a
  `TypeError`), `getCurrentSortColumn()` returns the default sort column; both are `null` / `ASC` for a table without
  columns. `getCurrentPaginationPage()` is `1` without a stored page.
- The debug page of `ExceptionHandler` shows the session through `Session::export()`.
- `getId()` and `regenerateId()` of the session handler are available as `Session::getId()` / `regenerateId()`.

---

## [v4.29.0] – 2026-10-08

`HttpRequest` is an immutable instance now, created once per request by `Core` and passed explicitly to everything that
needs it. Nothing in yuf reads `$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES` or `php://input` after
`HttpRequest::fromGlobals()` any more, and there is no static state in the request path. Every static call has to be
replaced, see the table. Search your project for `HttpRequest::`, `RequestBody::`, `FormInput::fromGlobals`,
`new InputParameter(`, `forCurrentRequest`, `SearchHelper::getInstance`, `createDbResultTable`, `new TableFilter(`,
`redirectAndExit`, `UrlHelper::generateAbsoluteUri` and for every class that extends `Authenticator`,
`AbstractSessionHandler`, `AbstractMailer` or `DbResultTable`.

### ⚠️ `HttpRequest`: static getters become instance methods

Where to get the instance: `$this->context->httpRequest` in a view, `$core->httpRequest` in a bootstrap file after
`new Core(…)`, `HttpRequest::fromGlobals()` in a script without `Core` (CLI, cron: throws an
`UnexpectedValueException` without a request method, request URI and host). Everything else gets the request from its
caller (argument or constructor).

| Before (static)                                    | After (instance)                                                          |
|:---------------------------------------------------|:--------------------------------------------------------------------------|
| `HttpRequest::getRequestMethod()`                  | `$httpRequest->getMethod()` (also `HEAD` and `OPTIONS` now)               |
| `HttpRequest::getUri()`                            | `$httpRequest->getUri()`                                                  |
| `HttpRequest::getPath()`                           | `$httpRequest->getPath()`                                                 |
| `HttpRequest::getQuery()`                          | `$httpRequest->getQuery()` (the raw query string)                         |
| `HttpRequest::getProtocol()` (string)              | `$httpRequest->getProtocol()` (`ProtocolEnum`, `->value` is the string)   |
| `HttpRequest::PROTOCOL_HTTP` / `PROTOCOL_HTTPS`    | `ProtocolEnum::HTTP` / `ProtocolEnum::HTTPS`                              |
| `HttpRequest::isSsl()`                             | `$httpRequest->isSsl()`                                                   |
| `HttpRequest::getHost()`                           | `$httpRequest->getHost()`                                                 |
| `HttpRequest::getPort()`                           | `$httpRequest->getPort()` (0 if unknown)                                  |
| `HttpRequest::getUrl(protocol: 'https')`           | `$httpRequest->getUrl(protocol: ProtocolEnum::HTTPS)`                     |
| `HttpRequest::getRemoteAddress()`                  | `$httpRequest->getRemoteAddress()`                                        |
| `HttpRequest::getUserAgent()`                      | `$httpRequest->getUserAgent()`                                            |
| `HttpRequest::getReferrer()`                       | `$httpRequest->getReferrer()`                                             |
| `HttpRequest::listBrowserLanguagesByQuality()`     | `$httpRequest->listBrowserLanguagesByQuality()`                           |
| `HttpRequest::getBearer()` (`false\|string`)       | `$httpRequest->getBearerToken()` (`?string`)                              |
| `HttpRequest::getCookies()`                        | `$httpRequest->getCookie(name: …)` (`?string`)                            |
| `HttpRequest::getInputString(keyName:)`            | `getQueryString(name:)` or `getPostString(name:)`                         |
| `HttpRequest::getInputInteger(keyName:)`           | `getQueryInteger(name:)` or `getPostInteger(name:)`                       |
| `HttpRequest::getInputFloat(keyName:)`             | `getQueryFloat(name:)` or `getPostFloat(name:)`                           |
| `HttpRequest::getInputArray(keyName:)`             | `getQueryArray(name:)` or `getPostArray(name:)`                           |
| `HttpRequest::getInputValue(keyName:)`             | `getQueryArray()` / `getQueryString()`, `getPostArray()` / `getPostString()` |
| `HttpRequest::hasScalarInputValue(keyName:)`       | `getQueryString(name:) !== null` / `getPostString(name:) !== null`; `hasQueryValue()` / `hasPostValue()` check only that the key exists |
| `HttpRequest::getFile(name:)`                      | `$httpRequest->getFile(name:)`: the normalized entry, `null` for a field with several files |
| `HttpRequest::getFiles(name:)`                     | `$httpRequest->getFiles(name:)`                                           |
| `RequestBody::getData()`                           | `$httpRequest->getBody()`                                                 |

New: `getHeader(name:)` (case-insensitive), `getServerName()`, `getServerAddress()`, `getQueryParameters()`,
`getPostParameters()`, `getRawFiles()`, `getServerVariables()` and `listCookies()` (the last five for forms, the error log
and the debug page). The constructor takes everything as named arguments (`new HttpRequest(host: 'example.com', …)`), so
tests need no superglobals. The static caches of host, protocol, languages and input are gone: `HttpRequest` is `final
readonly`.

`RequestBody` is removed (`JsonRequestBody` no longer extends it): replace `RequestBody::getData()` with
`$httpRequest->getBody()`.

### ⚠️ No merged input: query or post

`getInput…()` merged `$_GET` and `$_POST` (the post value won). Decide per value where it comes from, as it is in the
form (`method="post"`) or the link (`?page=2`). Numeric keys (`?5=x`) are no longer renumbered by a merge.

### ⚠️ Numbers are strict

`getQueryInteger()`, `getPostInteger()`, `getQueryFloat()` and `getPostFloat()` return a number only for a complete
number: integers `-?\d+` within the integer range, floats `-?\d+(\.\d+)?([eE][+-]?\d+)?` and finite. Before, `(int)` and
`(float)` casts were used. The results change as follows (`null` is a missing, malformed or out of range value):

| Value       | `getInputInteger()` before | `getQueryInteger()` now | `getInputFloat()` before | `getQueryFloat()` now |
|:------------|:---------------------------|:------------------------|:-------------------------|:----------------------|
| `'12'`      | `12`                       | `12`                    | `12.0`                   | `12.0`                |
| `'12abc'`   | `12`                       | `null`                  | `12.0`                   | `null`                |
| `''`        | `0`                        | `null`                  | `0.0`                    | `null`                |
| `'abc'`     | `0`                        | `null`                  | `0.0`                    | `null`                |
| `'1.5'`     | `1`                        | `null`                  | `1.5`                    | `1.5`                 |
| `'1,5'`     | `1`                        | `null`                  | `1.0`                    | `null`                |
| `'1e3'`     | `1000`                     | `null`                  | `1000.0`                 | `1000.0`              |
| `'+5'`      | `5`                        | `null`                  | `5.0`                    | `null`                |
| `'99999999999999999999'` | `PHP_INT_MAX`   | `null`                  | `1.0E+20`                | `1.0E+20`             |
| `'1e999'`   | `0`                        | `null`                  | `INF`                    | `null`                |

A present but empty or non-numeric value was indistinguishable from `0`; now it is `null`. Check every call that used
`?? 0` or the number as an ID.

### ⚠️ `Core::$httpRequest` and the other users of the request

`Core` creates the request in its constructor (`public readonly HttpRequest $httpRequest`) and answers a request with an
unknown method (`TRACE`, …) with `405` before anything else (`UnsupportedRequestMethodException`). A bootstrap file uses
`$core->httpRequest`:

```php
// before
$ip = HttpRequest::getRemoteAddress();
$sessionHandler = new FileSessionHandler(sessionSettings: new SessionSettings(), defaultSavePath: $path);

// after
$core = new Core(envFilePath: $envFilePath, copyrightYear: 2026);
$ip = $core->httpRequest->getRemoteAddress();
$sessionHandler = new FileSessionHandler(
    httpRequest: $core->httpRequest,
    sessionSettings: new SessionSettings(),
    defaultSavePath: $path,
);
```

### ⚠️ Changed constructors and methods

Every call has to pass the request (all arguments are named, so the position does not matter, except where a required
parameter moved before the optional ones).

| Class / method | Change |
|:---------------|:-------|
| `RequestHandler::__construct()` | new first argument `httpRequest:` |
| `ViewContext::__construct()` | new first argument `httpRequest:` (`$this->context->httpRequest`); `getJsonRequestBody()` reads `getBody()` |
| `InputParameter::__construct()` | new required `source:` (`InputSourceEnum::QUERY` or `POST`) after `name:` |
| `BaseView::getInputString()`, `getInputInteger()`, `getInputFloat()`, `getInputArray()`, `getInputDomain()` | unchanged names; they read from the `source` of the declared parameter; the check of required parameters uses it, too |
| `HttpResponse::createHtmlResponse()`, `createResponseFromString()`, `createResponseFromFilePath()` | new required `httpRequest:` (conditional requests) |
| `HttpResponse::redirectAndExit()` | new required second argument `httpRequest:` (before `httpStatusCode:`) |
| `UrlHelper::generateAbsoluteUri()` | new required `httpRequest:` |
| `CspPolicySettings::getHttpHeaderDataString()` | new required `httpRequest:` |
| `CsvFile::pushDownloadAndExit()` | new required `httpRequest:` |
| `FileHandler::output()` | new first argument `httpRequest:` (before `forceDownload:`) |
| `Logger::__construct()` | new required `httpRequest:` after `logDirectory:` (the log contains its data instead of the superglobals) |
| `ExceptionHandlerContext::__construct()` | new required `httpRequest:` (last) |
| `AbstractSessionHandler::__construct()`, `FileSessionHandler::__construct()` | new first argument `httpRequest:` (remote address, user agent, session cookie) |
| `Authenticator::__construct()`, `MicrosoftAuthenticator::__construct()` | new first argument `httpRequest:`; subclasses pass it on (the remote address of the login) |
| `AbstractMailer::__construct()`, `SmtpMailer::__construct()` | new first argument `serverAddress:` (`$httpRequest->getServerAddress()`) instead of `$_SERVER['SERVER_ADDR']`; `MailMailer` takes it, too |
| `AbstractMail::send()` | `$abstractMailer` is required (before: default `new MailMailer()`): `$mail->send(abstractMailer: new MailMailer(serverAddress: $httpRequest->getServerAddress()))` |
| `FormInput::fromGlobals()` | removed: `FormInput::fromHttpRequest(httpRequest:, methodPost:)` |
| `Form::validate()`, `Form::isSent()` | `input:` is required (before: the current request) |
| `FileField::__construct()` | `storage:` is required and the third argument (after `label:`); use `SessionFileUploadStorage::forHttpRequest(httpRequest:)` |
| `SessionFileUploadStorage::forCurrentRequest()` | removed: `forHttpRequest(httpRequest:)` |
| `TableFilter::__construct()` | new required `httpRequest:` after `identifier:` (the fields take it from their filter) |
| `DbResultTable::__construct()` | new required `httpRequest:` after `templateEngine:` |
| `TableHelper::createDbResultTable()` | new required `httpRequest:` after `templateEngine:` |
| `SearchHelper::getInstance(instanceName:)` | `SearchHelper::create(instanceName:, httpRequest:, valueSource:)`, no static registry any more; `valueSource` is where the values of the search fields come from |

The parameters of tables, filters and the search helper come from fixed sources: sorting, page, `find` and `reset` from
the query string; the filter values of a `TableFilter` from the posted data (only with a valid CSRF token and the
method `POST`, as before); the values of the `SearchHelper` from `valueSource`.

### Before / after

A view with input parameters:

```php
// before
$inputParameters->add(inputParameter: new InputParameter(name: 'page', isRequired: false));
$page = $this->getInputInteger(keyName: 'page') ?? 1;
$ip = HttpRequest::getRemoteAddress();

// after
$inputParameters->add(
    inputParameter: new InputParameter(name: 'page', source: InputSourceEnum::QUERY, isRequired: false),
);
$page = $this->getInputInteger(keyName: 'page') ?? 1; // unchanged: reads the query string
$ip = $this->context->httpRequest->getRemoteAddress();
```

A form in a view:

```php
// before
if ($form->validate()) {
    // …
}

// after
$input = FormInput::fromHttpRequest(httpRequest: $this->context->httpRequest, methodPost: true);
if ($form->validate(input: $input)) {
    // …
}
```

A table:

```php
// before
$table = TableHelper::createDbResultTable(
    identifier: 'users',
    db: $db,
    selectQuery: $sql,
    templateEngine: $this->context->templateEngine,
    tableFilter: new TableFilter(identifier: 'usersFilter'),
);

// after
$httpRequest = $this->context->httpRequest;
$table = TableHelper::createDbResultTable(
    identifier: 'users',
    db: $db,
    selectQuery: $sql,
    templateEngine: $this->context->templateEngine,
    httpRequest: $httpRequest,
    tableFilter: new TableFilter(identifier: 'usersFilter', httpRequest: $httpRequest),
);
```

A static helper of your project: it must not read the request, its caller passes what it needs in.

```php
// before
final class LinkHelper
{
    public static function absolute(string $path): string
    {
        return 'https://' . HttpRequest::getHost() . $path;
    }
}
$url = LinkHelper::absolute(path: '/login.html');

// after
final class LinkHelper
{
    public static function absolute(string $host, string $path): string
    {
        return 'https://' . $host . $path;
    }
}
$url = LinkHelper::absolute(host: $this->context->httpRequest->getHost(), path: '/login.html');
```

A bootstrap file (`index.php`): use `$core->httpRequest` after `new Core(…)`; before `Core` exists (and in CLI scripts),
`HttpRequest::fromGlobals()`.

### Bug fixes (no change needed)

- HTTPS detection: the `HTTPS` server variable decides if it exists (`on` or `1`, case-insensitive; `off` means
  http, also on port 443). Only without it, port 443 means https. Before, `ON` was http and `off` on port 443 https.
- Browser languages (`listBrowserLanguagesByQuality()`): all languages in quality order (before, a second language with
  the same quality was dropped), every language once, no `*` and no empty codes, spaces around `;q=` are allowed
  (`de, en; q=0.5`), qualities are compared exactly (`0.57` before `0.56`) and clamped to 0…1.
- `getFiles()` returns the files of a field with one file and of one with several (`field[]`) alike, and `[]` for a
  missing field or a manipulated structure. Before, it threw a `TypeError` for one file and failed for a missing field.
- Bearer token: header name and scheme `Bearer` are case-insensitive, an empty token is `null` (before: `''`), and it
  works without `getallheaders()` (fallback to `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`).
- A missing `REQUEST_METHOD`, `REQUEST_URI` or host is an `UnexpectedValueException` in `fromGlobals()` (before: a
  warning "Undefined array key" later); a missing `SERVER_PORT` is port 0 and a missing `QUERY_STRING` is empty. An unknown
  request method is an `UnsupportedRequestMethodException` (before: a `ValueError`), answered with 405.
- `UrlHelper::generateAbsoluteUri()` ignores the query string of the request when it takes the directory (a "/" in the
  query shifted the directory).
- The debug page of `ExceptionHandler` shows `$_FILES` (before: always empty because of a typo in the variable name).
- `SearchHelper` checks `reset` and `find` the same way everywhere (query string, key exists).
- `checkDateRangeFilter()` has typed array arguments and results (`array{minDate: string, maxDate: string}`).

Remaining reads of superglobals: `HttpRequest::fromGlobals()`, `Core` (`$_SERVER['DOCUMENT_ROOT']`) and the session
handler (removes an invalid session cookie from `$_COOKIE`, because `session_start()` reads it from there).

---

## [v4.28.0] – 2026-10-08

### ⚠️ `LogFile` is an instance class only

The static methods `LogFile::info()`, `LogFile::debug()` and `LogFile::error()` and the static registry of open log files
are removed. `LogFile` is `final`. Constructor, log file path and line format are unchanged.

```php
// before
LogFile::info(logDirectory: $logDirectory, logFileName: 'import', message: 'started');

// after
$logFile = new LogFile(logDirectory: $logDirectory, group: 'info', logFileName: 'import');
$logFile->write(line: 'started');
```

The old methods reused one file per group and name within a request. Keep one `LogFile` instance for that and call
`write()` on it.

`new LogFile(…)` now throws a `RuntimeException` (with the path) if a directory cannot be created or the log file cannot
be opened. Before, `write()` silently did nothing if the file could not be opened. No code change is needed.

---

## [v4.27.0] – 2026-10-08

### Own template tags

Projects can register their own template tags: implement `actra\yuf\template\tag\TemplateTag` and pass the tags as the new
last argument of `Core::prepareHttpResponse()`. The tags are known to views, snippets, tables and the error pages. The
argument is optional, existing projects need no change.

```php
$core->prepareHttpResponse(
    routeCollection: $routes,
    templateTags: [new PriceTag()],
);
```

`{tst:price value='article.price'}` and `<tst:price value="article.price">…</tst:price>` then call `PriceTag::render()`.
The tag returns HTML and escapes values itself (`TemplateTagContext::escape()`). A tag name that is used by a built-in tag,
by another own tag or is `if`, `else` or `for` throws an `InvalidArgumentException` in `prepareHttpResponse()`. See
"Own template tags" in `README.md`.

---

## [v4.26.0] – 2026-10-08

yuf renders with the new template engine (`TemplateEngine`, see v4.24.0) and the old engine is deleted. The syntax of
the templates is unchanged; the changes below are the removed features, the stricter rules and the signatures that now
take the engine. Check every template and every call of the changed methods. Compiled templates of the old engine in
the cache directory are no longer used (see the last section).

### ⚠️ Output of plain strings is escaped by the engine

Before, the engine never escaped: values were encoded when they entered the replacements. Now `text`, `print`,
`options` and the `vars` values of `lang` escape every string, `int`, `float` and `Stringable` with
`htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. Of the replacements, only `addHtml()` / `HtmlText::fromHtml()`
(and the texts of language files and non-`.html` snippets) are output as they are; `addText()` / `fromText()` are
escaped once, as before (the engine does not escape twice). A plain string reaches the engine only through
`new TemplateData(...)`, an `ArrayObject` or an object you pass yourself.

Before: `$data = new ArrayObject(['name' => $name]);` with `{tst:text value='name'}` output `$name` unescaped.
After: the same output is escaped. Pass `HtmlText::fromHtml(html: …)` (or prepare the value with `addHtml()`) only for
HTML built by your own code. Escaping is for HTML text and quoted attributes: values in `<script>` or `<style>` are not
escaped for these contexts; use `data-*` attributes or JSON prepared by the view.

`null` is output as `''`, a boolean as `'1'` / `''` (as before). An array or an object (without `__toString()`) is a
`TemplateException` in `text`; `print` outputs an escaped `print_r()` dump and formats every `DateTimeInterface` as
`Y-m-d H:i:s` (before: only `DateTime`).

### ⚠️ `if`: comparison rules

`if` no longer compares with PHP's loose `==`. `ne` is always the inverse of `eq`.

| `against` | `eq` is true for the value |
|:--|:--|
| `null` | `null`, `''`, `[]`, `false`, `0`, `0.0` (not `'0'`) |
| `""` | `null`, `''`, `false` (not `[]`, not `0`) |
| `true` | the value is truthy in PHP |
| `false` | the value is falsy in PHP |
| anything else | the value is a string, `int`, `float` or `Stringable` and equals `against` as string (`1` equals `"1"`) |

Changes against before:

- Against any other value (`against="abc"`, `"1"`, `"0"`): `null`, booleans and arrays never equal it (before, `true`
  equalled `'abc'` and `false` / `0` equalled `'0'`). Compare against `true` / `false` to test for booleans.
- `gt`, `ge`, `lt`, `le` compare numbers; a non-numeric value or `against` (`null`, `''`, `[]`, a boolean, `'abc'`) is
  a `TemplateException` (before: PHP's comparison of mixed types).
- `in` compares texts, `against` is split at spaces (as before); `null`, booleans and arrays are never in the list.
- `operator` is optional (default `eq`), the operator is case-insensitive, an unknown operator is a `TemplateException`.
  An apostrophe in `against` works (it was a `ParseError`).
- `<tst:else>` must directly follow `</tst:if>` with only whitespace in between; text in between or an `else` without
  `if` is a `TemplateException` (before: accepted).

### ⚠️ Removed tags and features

None of these is used by the templates of yuf, the example or `actra/backend`.

| Removed | Replacement |
|:--|:--|
| `elseif` (was broken) | nested `if` / `else` |
| `for2`, `forgroup` | `for`; groups and counters prepared by the view |
| `option`, `checkbox`, `radio`, `checkboxOptions`, `radioOptions`, `formComponent`, `formAddRemove` | the form components of `src/form/`, rendered by the view and added with `addHtml()` |
| `if compare="hasSnippet"` | a boolean from the view |
| `for` attributes `groups`, `classfirst`, `classlast`, `class` and the `_count` value | prepared by the view |
| raw `{var}`, `{var.prop}`, `${var.prop}` inside `for` | `{tst:text value='var.prop'}` (escaped) |
| selectors with method calls and arguments (`a.method(x)`) | a getter or a value prepared by the view |
| PHP code in templates (`<?php`, `<?=`, `<?`) | values from the view |

Before: `<tst:for value="rows" var="row">{row.name}</tst:for>`. After:
`<tst:for value="rows" var="row">{tst:text value='row.name'}</tst:for>`.

### ⚠️ Stricter syntax and other behaviour of the tags

- A closing tag that does not match the opening tag (`<tst:if …></tst:for>`) is a `TemplateException` with file and
  line (before: accepted).
- `<?php`, `<?=` and `<?` (also `<?xml`) in a template are a `TemplateException`; an XML declaration comes from the
  view.
- `<tst:text value="x">` without `/` and without a closing tag is "not closed" (before: self-closing).
  `<tst:text value="x"></tst:text>` works now (the closing tag was left in the output).
- `for`: the variable `var` only exists inside the body and no longer removes an outer value with the same name.
  Iterates arrays, `Traversable`, the public properties of an object and `null` (empty).
- Selectors: array or `ArrayAccess` key, public property, public getter `getX()` / `isX()` / `hasX()` (also without a
  property `x`), public method without arguments. A missing value, an unknown part or a missing top-level value is a
  `TemplateException` (file and line) instead of a plain `Exception`.
- `loadSubTpl`: a missing data key or file is a `TemplateException` (before: `TypeError` / `Exception`).
- `lang`: `vars` works again, it names an array (`vars='names'` with `['name' => 'Anna']` replaces `[NAME]`); the values
  are escaped, the language text is output as it is. A missing key is a `TemplateException`.
- `snippet`: a name that leaves the snippets directory (`..`, absolute path, symbolic link out) is a
  `TemplateException`; a missing snippet is an error for `.html` and other files.
- `options`: `selected` is optional, may be an array, and keys and labels are escaped.
- `date` uses the `Clock` (the system clock) and works as inline tag too.
- Template errors are `actra\yuf\template\TemplateException` (message `<reason> in <file> on line <n>`), no longer a plain
  `Exception`. A template that throws no longer leaves an output buffer open.

### ⚠️ `HtmlSnippet::render()` needs the template engine

`HtmlSnippet::render()` read `Core::get()` to create an engine. It takes the engine of the request now.

Before: `$html = $snippet->render();`. After (in a view):

```php
$html = $snippet->render(templateEngine: $this->context->templateEngine);
```

### ⚠️ `Pagination`, `TablePaginationRenderer`, `TableFilter`, `DbResultTable`

| Before | After |
|:--|:--|
| `Pagination::render(listIdentifier:, totalAmount:, currentPage:, entriesPerPage: …)` | `Pagination::render(listIdentifier:, totalAmount:, currentPage:, templateEngine:, entriesPerPage: …)` |
| `TablePaginationRenderer::render(dbResultTable:, …)` | `render(dbResultTable:, templateEngine:, …)` |
| `TableFilter::render()` | `render(templateEngine:)` |
| `new DbResultTable(identifier:, db:, dbQuery:, …)` | `new DbResultTable(identifier:, db:, dbQuery:, templateEngine:, …)` |
| `TableHelper::createDbResultTable(identifier:, db:, selectQuery:, params: …)` | `createDbResultTable(identifier:, db:, selectQuery:, templateEngine:, params: …)` |

`DbResultTable::render()` is unchanged: it passes its `templateEngine` to the pagination and the filter. In a view, pass
`$this->context->templateEngine`.

### ⚠️ `ViewContext`, `HtmlDocument`, `ContentHandler::processRequest()`

- `new ViewContext(…, locale:, templateEngine:)`: new required argument `templateEngine`, also readable as
  `$this->context->templateEngine` in a view.
- `new HtmlDocument(requestHandler:, cspNonce:, core:, templateEngine:)`: new required argument.
- `ContentHandler::processRequest(requestHandler:, localeHandler:, core:, templateEngine:)`: new required argument.

Only code that creates these objects itself (tests, own factories) is affected: `Core` does it for the application.

### ⚠️ `Core::get()` removed

The static accessor is gone; `Core` keeps its guard (a second `new Core()` throws a `LogicException`). Pass the
`Core` or the value you need (`$core->cacheDirectory`, `$core->snippetsDirectory`, …). New: `Core::createTemplateEngine(localeHandler:)` creates the engine of a request (cache in the
cache directory, the snippets directory of the `Core`, the system clock). For templates outside of a view, create an engine like that and call
`$engine->render(templateFile:, data: new TemplateData(values: […]))` (`TemplateData::fromReplacements()` for an
`HtmlReplacementCollection`).

### ⚠️ `LocaleHandler::get()`, `register()` and `isRegistered()` removed

Before: `LocaleHandler::register(localeHandler: $handler)` stored the instance and set the system locale. After: create
the handler (`new LocaleHandler(language:, availableLanguages:)`; views get it as `$this->context->locale`) and call
`$handler->applySystemLocale()` for the system locale (`setlocale()`; `Core` does it for the request). `lang` tags read
the texts from the `LocaleHandler` of the engine.

The error pages (`ExceptionHandler`) use a `LocaleHandler` of their own: the global texts of the default route of the
requested language. Before, the one of the request was used if it existed, with the texts of the requested view; an
error page that uses `lang` with a text of the view's own language file needs it in `global.lang.php` now.

### ⚠️ `LogFile` needs the log directory

`LogFile` read `Core::get()->logDirectory`. The directory is the first argument now.

Before: `LogFile::info(logFileName: 'sync', message: '…')`, `new LogFile(group: 'info', logFileName: 'sync')`.
After: `LogFile::info(logDirectory: $core->logDirectory, logFileName: 'sync', message: '…')`
(`debug()`, `error()` the same), `new LogFile(logDirectory: $core->logDirectory, group: 'info', logFileName: 'sync')`.

### ⚠️ The old template classes are removed

`actra\yuf\template\customtags\*`, `actra\yuf\template\htmlparser\*` and `actra\yuf\template\template\*` (the old
`TemplateEngine`, `DirectoryTemplateCache`, `TemplateTag`, `TagNode`, …) are deleted, with no replacement classes of
the same name. Code that used the old engine directly uses `actra\yuf\template\TemplateEngine` (see above). Own tags are
planned for v4.27.0.

### Old compiled templates

The compiled templates of the old engine in the cache directory (`*.php` files named like the template path, e.g.
`<cacheDirectory>app/view/frontend/templates/default.php`) are no longer used and can be deleted. The new engine writes below `<cacheDirectory>v<format version>/`,
so an upgrade never runs the compiled code of an older version.

## [v4.25.0] – 2026-10-08

### ⚠️ The replacement API says what it does: `fromHtml()` / `fromText()`, `addHtml()` / `addText()`

"Encoded" meant "already HTML, output as it is"; "unencoded" meant "plain text, escaped when rendered". The new names
say what the caller passes. Behaviour and rendered HTML are unchanged. The old names are removed (no aliases).

| Before | After |
|:--|:--|
| `HtmlText::encoded(textContent: …)` | `HtmlText::fromHtml(html: …)` |
| `HtmlText::unencoded(textContent: …)` | `HtmlText::fromText(text: …)` |
| `HtmlReplacementCollection::addEncodedText(identifier:, content:)` | `addHtml(identifier:, html:)` |
| `HtmlReplacementCollection::addUnencodedText(identifier:, content:)` | `addText(identifier:, text:)` |
| `HtmlReplacement::encodedText(content: …)` | `HtmlReplacement::html(html: …)` |
| `HtmlReplacement::unencodedText(content: …)` | `HtmlReplacement::text(text: …)` |
| `HtmlDataObject::addTextElement(propertyName:, content:, isEncodedForRendering: true)` | `addHtml(propertyName:, html:)` |
| `HtmlDataObject::addTextElement(propertyName:, content:, isEncodedForRendering: false)` | `addText(propertyName:, text:)` |
| `new DetailDataObject(name:, value:, isEncodedForRendering:)` | `new DetailDataObject(name:, value:, isHtml:)` |

`HtmlReplacement::html()` and `text()` accept `null` now (the replacement is then `null`, like `addHtml()` /
`addText()` of the collection); before, `null` was a `TypeError`.

Before:

```php
$replacements->addEncodedText(identifier: 'intro', content: '<b>Welcome</b>');
$replacements->addUnencodedText(identifier: 'name', content: $customer->name);
$label = HtmlText::unencoded(textContent: $customer->name);
$object->addTextElement(propertyName: 'city', content: $city, isEncodedForRendering: false);
```

After:

```php
$replacements->addHtml(identifier: 'intro', html: '<b>Welcome</b>');
$replacements->addText(identifier: 'name', text: $customer->name);
$label = HtmlText::fromText(text: $customer->name);
$object->addText(propertyName: 'city', text: $city);
```

Migration (mechanical, in this order):

1. `addEncodedText(` → `addHtml(` and its argument `content:` → `html:`.
2. `addUnencodedText(` → `addText(` and `content:` → `text:`.
3. `HtmlText::encoded(textContent:` → `HtmlText::fromHtml(html:`; `HtmlText::unencoded(textContent:` →
   `HtmlText::fromText(text:`.
4. `addTextElement(…, content: X, isEncodedForRendering: true)` → `addHtml(…, html: X)`;
   `…, isEncodedForRendering: false)` → `addText(…, text: X)`.
5. `HtmlReplacement::encodedText(content:` → `HtmlReplacement::html(html:`; `unencodedText(content:` →
   `HtmlReplacement::text(text:`.
6. `DetailDataObject`: argument `isEncodedForRendering:` → `isHtml:`.

Afterwards review every `addHtml()` and `fromHtml()`: they output the string as it is. User data (names, free text,
anything from a request or a database) must use `addText()` / `fromText()`. Only HTML built by your own code belongs in
`addHtml()` / `fromHtml()`. The planned new template engine (v4.26.0) escapes everything else.

### ⚠️ `HtmlReplacement::object()` replaced by `HtmlReplacement::dataObject()`

`HtmlReplacement::object(?stdClass $object)` is now `HtmlReplacement::dataObject(?HtmlDataObject $htmlDataObject)`, so
all object data passes through `HtmlDataObject` (`addText()` escapes, `addHtml()` is explicit) and nothing reaches the
templates unescaped by accident. `HtmlReplacementCollection::addDataObject()` is unchanged for callers.

Before: `HtmlReplacement::object(object: $htmlDataObject->data)`. After:
`HtmlReplacement::dataObject(htmlDataObject: $htmlDataObject)`. Code that built a plain `stdClass` must use
`HtmlDataObject` instead.

## [v4.24.0] – 2026-10-08

### New template engine (not used yet)

`src/template/` has a new template engine next to the old one: `TemplateEngine`, `TemplateData`, `TemplateException`,
`TemplateTag`, `TemplateTagContext`, `TemplateTagCollection`, the built-in tags (`TextTag`, `LoadSubTplTag`, `LangTag`,
`SnippetTag`, `PrintTag`, `DateTag`, `OptionsTag`), `TemplateCache` and `DirectoryTemplateCache`. Nothing in yuf uses
it yet: `Core`, `ContentHandler`, `HtmlDocument`, `HtmlSnippet`, `Pagination` and `TableFilter` still render with the
old engine, and the old classes are unchanged. No action is needed. A later release (planned as v4.26.0) switches yuf
to the new engine and removes the old one; that release lists the changes for templates and views.

## [v4.23.0] – 2026-10-08

### ⚠️ `RequestHandler::get()` and `RequestHandler::register()` removed

The request data is no longer reachable through a static accessor. `Core::prepareHttpResponse()` creates the
`RequestHandler` itself (constructor `(routeCollection, availableLanguages, allowedDomains)`, then `resolveRoute()`).
Views read the request data from their `ViewContext`; everything else gets the `RequestHandler` passed.

Before:

```php
$route = RequestHandler::get()->route;
$id = RequestHandler::get()->pathVars;
```

After (in a view):

```php
$route = $this->context->route;
$pathVars = $this->context->pathVars;
```

Elsewhere, pass the `RequestHandler` (or the `Route`) as argument. `HtmlSnippet::createForCurrentView()` now needs the
route as first argument, because it read `RequestHandler::get()->route`:

```php
HtmlSnippet::createForCurrentView(route: $this->context->route, snippetName: 'menu');
```

### ⚠️ `ContentHandler::get()`, `isRegistered()` and `register()` removed

`Core` creates the `ContentHandler` and calls the new `processRequest(requestHandler:, localeHandler:, core:)`.
`getHtmlDocument()` throws a `LogicException` until `processRequest()` has been called.

Before:

```php
ContentHandler::get()->setContent(contentString: $json);
if (ContentHandler::isRegistered()) { /* ... */ }
```

After (in a view):

```php
$this->context->content->setContent(contentString: $json);
```

### ⚠️ `HtmlDocument` constructor

`new HtmlDocument(cspNonce:)` became `new HtmlDocument(requestHandler:, cspNonce:, core:)`. Views keep using
`$this->context->getHtmlDocument()`.

### ⚠️ `ExceptionHandlerContext` needs `core:`

The exception handler reads the error docs directory, the copyright year and the available languages from it instead of
`Core::get()`.

Before:

```php
new ExceptionHandlerContext(logger: $logger, cspNonce: $cspNonce, cspPolicySettings: $settings, isDebug: $debug);
```

After:

```php
new ExceptionHandlerContext(
    logger: $logger,
    cspNonce: $cspNonce,
    cspPolicySettings: $settings,
    isDebug: $debug,
    core: $core,
);
```

### `ExceptionHandler`: `register()` returns the instance, new setters

`ExceptionHandler::register()` returns the registered handler (was `void`). `setRequestHandler()` and
`setContentHandler()` (each only once) give it the request data; `Core::prepareHttpResponse()` calls them. An exception
before the request handler exists still renders the error page with the language `en` and the language root `/`.

### `RequestHandler`: two phases

The constructor no longer checks the domain or resolves the route; `resolveRoute()` does (it throws a `NotFoundException`
as the constructor did, and a `LogicException` when called twice). `route`, `fileTitle`, `fileExtension` and `pathVars`
are only set afterwards. The language, the path parts, the file name and the default routes are available right after
the constructor, so the error page of an unknown route keeps its language and language root.

---

## [v4.22.0] – 2026-10-08

### ⚠️ `Route` needs `viewDirectory:`

The placeholder `'{default}'` and the default value are gone: `Core::get()->viewDirectory` is no longer read by
`Route`. `viewDirectory` is now the second parameter (right after `path`, because a required parameter must not follow
optional ones); this only matters for positional arguments. Calls with named arguments only need the new argument.

Before:

```php
new Route(path: '/', viewGroup: 'frontend');
new Route(path: '/', viewDirectory: '{default}', viewGroup: 'frontend');
```

After:

```php
new Route(path: '/', viewDirectory: $core->viewDirectory, viewGroup: 'frontend');
```

### ⚠️ `SessionSettings`: `savePath` is `?string`, no `'{default}'`

`null` (default) means the default directory of the session handler. A project that passed `'{default}'` or a path
containing it must pass `null` or a real path.

Before:

```php
new SessionSettings(savePath: '{default}');
new SessionSettings(savePath: '{default}/custom');
```

After:

```php
new SessionSettings();
new SessionSettings(savePath: $core->cacheDirectory . 'sessions/custom');
```

### ⚠️ `FileSessionHandler` needs `defaultSavePath:`

Used when `SessionSettings::$savePath` is `null`. Previously this was `Core::get()->cacheDirectory . 'sessions'`.

Before:

```php
new FileSessionHandler(sessionSettings: new SessionSettings());
```

After:

```php
new FileSessionHandler(
    sessionSettings: new SessionSettings(),
    defaultSavePath: $core->cacheDirectory . 'sessions',
);
```

### ⚠️ `Core::prepareHttpResponse()`: `individualSessionHandler` defaults to `null`

`null` creates the default `FileSessionHandler` (`new SessionSettings()`, save path `<cacheDirectory>sessions`);
`false` still disables sessions. Only relevant for projects that relied on the default object in a different way, for
example by passing `new FileSessionHandler(sessionSettings: ...)` themselves (see above); the behaviour of the default
is unchanged.

Before:

```php
$core->prepareHttpResponse(individualSessionHandler: new FileSessionHandler(sessionSettings: new SessionSettings()));
```

After:

```php
$core->prepareHttpResponse(); // same as individualSessionHandler: null
$core->prepareHttpResponse(individualSessionHandler: false); // no session
```

### ⚠️ `AbstractSessionHandler::setPreferredLanguage()` no longer checks the available languages

Only relevant for projects that call it themselves: they no longer get the `Exception` for a language that is not
available (`Core::get()->availableLanguages` is not read any more). `RequestHandler` checks it before and throws a
`LogicException` with the same message (previously `Exception`).

### ⚠️ `MicrosoftAuthenticator` has a constructor with directories

Subclasses must call it; the SSO log goes to `$logDirectory`, the public keys to `$cacheDirectory`
(`ssoMicrosoftKeys.json`). Both directories end with a slash (like `Core::$logDirectory` / `Core::$cacheDirectory`).

Before:

```php
final class AppAuthenticator extends MicrosoftAuthenticator
{
    public function __construct()
    {
        parent::__construct(maxAllowedWrongPasswordAttempts: 5);
    }
}
```

After:

```php
final class AppAuthenticator extends MicrosoftAuthenticator
{
    public function __construct(Core $core)
    {
        parent::__construct(
            maxAllowedWrongPasswordAttempts: 5,
            logDirectory: $core->logDirectory,
            cacheDirectory: $core->cacheDirectory,
        );
    }
}
```

### ⚠️ `MicrosoftIdToken` needs `cacheDirectory:`

Only relevant for projects that create it themselves (`MicrosoftAuthenticator` does it). The new argument comes after
`jwtString:` and before `clock:`.

Before:

```php
new MicrosoftIdToken(tenantId: $tenantId, clientId: $clientId, ssoNonce: $ssoNonce, jwtString: $jwt);
```

After:

```php
new MicrosoftIdToken(
    tenantId: $tenantId,
    clientId: $clientId,
    ssoNonce: $ssoNonce,
    jwtString: $jwt,
    cacheDirectory: $core->cacheDirectory,
);
```

### `Pagination` and `TableFilter`

No API change: the default snippet files are found relative to the class files instead of through `Core`.

---

## [v4.21.0] – 2026-10-08

### ⚠️ `LocaleHandler::register()` takes a `LocaleHandler`

Only relevant for projects that call it themselves (`Core` does it). `LocaleHandler` has a public constructor and no
longer reads the request or `Core`.

Before:

```php
LocaleHandler::register(); // read the language from RequestHandler::get()
```

After:

```php
LocaleHandler::register(
    localeHandler: new LocaleHandler(language: $requestHandler->language, availableLanguages: $availableLanguages),
);
```

### ⚠️ `Route::loadLocalizedText()` needs the `LocaleHandler`

Before:

```php
$route->loadLocalizedText(fileTitle: 'index');
```

After:

```php
$route->loadLocalizedText(fileTitle: 'index', localeHandler: $localeHandler);
```

### ⚠️ `ViewContext` has the new argument `locale:`

Only relevant for projects that create a `ViewContext` themselves (for example in tests). Views read their texts from
the context.

Before:

```php
LocaleHandler::get()->getText(key: 'title');
new ViewContext(route: $route, fileGroup: null, fileTitle: 'index', pathVars: $pathVars, content: $content);
```

After:

```php
$this->context->locale->getText(key: 'title');
new ViewContext(
    route: $route,
    fileGroup: null,
    fileTitle: 'index',
    pathVars: $pathVars,
    content: $content,
    locale: $localeHandler,
);
```

### ⚠️ `ContentHandler::register()` needs `localeHandler:`

Only relevant for projects that call it themselves (`Core` does it).

Before:

```php
ContentHandler::register(cspNonce: $cspNonce);
```

After:

```php
ContentHandler::register(cspNonce: $cspNonce, localeHandler: $localeHandler);
```

### Other changes

- `LocaleHandler` has a public constructor `(?Language $language, LanguageCollection $availableLanguages)` and the
  readonly property `$language`. An unavailable language throws a `LogicException` (was `Exception`, same message).
  `LocaleHandler::get()` and `isRegistered()` stay for the compiled templates (`{lang}` tag).
- `RequestHandler::register()` returns the created `RequestHandler` (was `void`).

---

## [v4.20.0] – 2026-10-08

### ⚠️ `Logger::register()` and `Logger::get()` are removed

`Core` hands the logger to the exception handler; there is no static accessor any more.

Before:

```php
$core->prepareHttpResponse(logger: $logger); // registered the logger statically
Logger::get()->logMessage(message: 'Something happened');
```

After:

```php
$core->prepareHttpResponse(logger: $logger);
$this->getContext()->logger->logMessage(message: 'Something happened'); // in a custom ExceptionHandler
$logger->logMessage(message: 'Something happened'); // elsewhere: keep the logger you created
```

### ⚠️ `ExceptionHandler::register()` takes an `ExceptionHandlerContext`

Only relevant for projects that call it themselves (`Core` does it). The new `ExceptionHandlerContext` bundles the
logger, the CSP nonce, the CSP policy settings and the debug flag of the request.

Before:

```php
ExceptionHandler::register(individualExceptionHandler: $handler, cspNonce: $cspNonce);
```

After:

```php
ExceptionHandler::register(
    individualExceptionHandler: $handler,
    context: new ExceptionHandlerContext(
        logger: $logger,
        cspNonce: $cspNonce,
        cspPolicySettings: $cspPolicySettings,
        isDebug: $debug,
    ),
);
```

### ⚠️ `ExceptionHandler::$cspNonce` is removed

Before: `$this->cspNonce`. After: `$this->getContext()->cspNonce` (throws a `LogicException` before `register()`).

---

## [v4.19.0] – 2026-10-08

### ⚠️ `CspNonce` is an object per request, `CspNonce::get()` is removed

`CspNonce` is a `final readonly class` with `$value`; `Core` creates one object per request with `CspNonce::create()`
and passes it on. A view reads it through its `ViewContext`.

Before:

```php
$nonce = CspNonce::get();
```

After:

```php
$nonce = $this->context->content->cspNonce->value; // in a view
$cspNonce = CspNonce::create(); // elsewhere, e.g. in a test or a script that builds its own response
```

### ⚠️ `ExceptionHandler::register()` needs `cspNonce:`

Only relevant for projects that call it themselves (`Core` does it). A custom handler that extends `ExceptionHandler`
can use `$this->cspNonce`.

Before: `ExceptionHandler::register(individualExceptionHandler: $handler);`
After: `ExceptionHandler::register(individualExceptionHandler: $handler, cspNonce: $cspNonce);`

### ⚠️ `ContentHandler` and `HtmlDocument` constructors

Before:

```php
new ContentHandler(contentType: $contentType);
new HtmlDocument();
ContentHandler::register();
```

After:

```php
new ContentHandler(contentType: $contentType, cspNonce: $cspNonce);
new HtmlDocument(cspNonce: $cspNonce);
ContentHandler::register(cspNonce: $cspNonce);
```

### ⚠️ `HtmlSnippet` adds `cspNonce` only when a nonce is passed

Snippets without a nonce no longer get the replacement `cspNonce` automatically. A snippet that renders
`{tst:text value='cspNonce'}` must get the nonce.

Before:

```php
HtmlSnippet::createForCurrentView(snippetName: 'inlineScript')->render();
```

After:

```php
HtmlSnippet::createForCurrentView(
    snippetName: 'inlineScript',
    cspNonce: $this->context->content->cspNonce,
)->render();
new HtmlSnippet(htmlSnippetFilePath: $path, replacements: $replacements, cspNonce: $cspNonce);
```

---

## [v4.18.0] – 2026-10-08

### ⚠️ Class, method and constant names follow the coding standard

Acronyms are written like words, methods are camelCase and constants UPPER_SNAKE_CASE. The old names are removed.

| Before                                                | After                                               |
|:------------------------------------------------------|:----------------------------------------------------|
| `actra\yuf\common\CSVFile`                            | `actra\yuf\common\CsvFile`                          |
| `actra\yuf\mailer\SMTPMailer`                         | `actra\yuf\mailer\SmtpMailer`                       |
| `actra\yuf\db\FrameworkDB`                            | `actra\yuf\db\FrameworkDb`                          |
| `actra\yuf\common\SimpleXMLExtended`                  | `actra\yuf\common\SimpleXmlExtended`                |
| `SimpleXmlExtended::addXML()`                         | `SimpleXmlExtended::addXml()`                       |
| `SimpleXmlExtended::addCData($cdata_text)`            | `SimpleXmlExtended::addCdata($cdataText)`           |
| `SimpleXmlExtended::addArray(include_null: …)`        | `SimpleXmlExtended::addArray(includeNull: …)`       |
| `AbstractMail::addCC()`                               | `AbstractMail::addCc()`                             |
| `AbstractMail::addBCC()`                              | `AbstractMail::addBcc()`                            |
| `MailerFunctions::stripTrailingWSP()`                 | `MailerFunctions::stripTrailingWsp()`               |
| `MailerFunctions::mb_pathinfo()`                      | `MailerFunctions::mbPathinfo()`                     |
| `MailerFunctions::wrapText(qp_mode: …)`               | `MailerFunctions::wrapText(qpMode: …)`              |
| `StringUtils::utf8_to_punycode_email()`               | `StringUtils::utf8ToPunycodeEmail()`                |
| `StringUtils::punycode_to_utf8_email()`               | `StringUtils::punycodeToUtf8Email()`                |
| `SearchHelper::createSQLFilters()`                    | `SearchHelper::createSqlFilters()`                  |
| `SearchHelper::createSQLSearch()`                     | `SearchHelper::createSqlSearch()`                   |
| `SearchHelper::getBooleanQuery(query_text: …)`        | `SearchHelper::getBooleanQuery(queryText: …)`       |
| `ActionsColumn::addIndividualActionLink(linkHTML: …)` | `ActionsColumn::addIndividualActionLink(linkHtml: …)` |
| `SmartTable::totalAmount`                             | `SmartTable::TOTAL_AMOUNT`                          |
| `SmartTable::table`                                   | `SmartTable::TABLE`                                 |
| `SmartTable::tableHeader`                             | `SmartTable::TABLE_HEADER`                          |
| `SmartTable::tableBody`                               | `SmartTable::TABLE_BODY`                            |
| `SmartTable::cells`                                   | `SmartTable::CELLS`                                 |
| `SmartTable::totalAmountMessagePlaceholder`           | `SmartTable::TOTAL_AMOUNT_MESSAGE_PLACEHOLDER`      |
| `SmartTable::amount`                                  | `SmartTable::AMOUNT`                                |
| `DbResultTable::sessionDataType` (protected)          | `DbResultTable::SESSION_DATA_TYPE`                  |
| `DbResultTable::filter` (protected)                   | `DbResultTable::FILTER`                             |
| `DbResultTable::pagination` (protected)               | `DbResultTable::PAGINATION`                         |

The values of the table constants are unchanged (they are placeholders in the HTML templates and session keys), so
existing templates and stored sessions keep working.

```php
// Before
use actra\yuf\common\CSVFile;
use actra\yuf\db\FrameworkDB;

class DB extends FrameworkDB {}

$csv = new CSVFile(/* … */);
$mail->addCC(inputEmail: 'a@example.com');
$table->tableHtml = '<tbody>' . SmartTable::tableBody . '</tbody>';

// After
use actra\yuf\common\CsvFile;
use actra\yuf\db\FrameworkDb;

class DB extends FrameworkDb {}

$csv = new CsvFile(/* … */);
$mail->addCc(inputEmail: 'a@example.com');
$table->tableHtml = '<tbody>' . SmartTable::TABLE_BODY . '</tbody>';
```

---

## [v4.17.0] – 2026-10-08

### ⚠️ Request, response and error names follow the coding standard

Acronyms are written like words and enums end with `Enum`. The old names are removed.

| Before                                          | After                                              |
|:------------------------------------------------|:---------------------------------------------------|
| `HttpRequest::getURI()`                         | `HttpRequest::getUri()`                            |
| `HttpRequest::getURL()`                         | `HttpRequest::getUrl()`                            |
| `HttpRequest::isSSL()`                          | `HttpRequest::isSsl()`                             |
| `ErrorHandler::handlePHPError()`                | `ErrorHandler::handlePhpError()`                   |
| `actra\yuf\core\HttpStatusCode`                 | `actra\yuf\core\HttpStatusCodeEnum`                |

The enum cases and values are unchanged. `HttpStatusCodeEnum` is used in `BaseView::setErrorResponseContent()`,
`HttpResponse`, `ContentHandler::$httpStatusCode`, `CurlResponse::$responseHttpCode`, `NotFoundException` and
`UnauthorizedException`.

```php
// Before
use actra\yuf\core\HttpStatusCode;

$uri = HttpRequest::getURI();
if (!HttpRequest::isSSL()) {
    $url = HttpRequest::getURL();
}
$this->setErrorResponseContent(errorMessage: 'Not found', httpStatusCode: HttpStatusCode::HTTP_NOT_FOUND);

// After
use actra\yuf\core\HttpStatusCodeEnum;

$uri = HttpRequest::getUri();
if (!HttpRequest::isSsl()) {
    $url = HttpRequest::getUrl();
}
$this->setErrorResponseContent(errorMessage: 'Not found', httpStatusCode: HttpStatusCodeEnum::HTTP_NOT_FOUND);
```

---

## [v4.16.0] – 2026-10-08

### ⚠️ Auth and session names follow the coding standard

Acronyms are written like words (`Id`, not `ID`) and enums end with `Enum`. The old names are removed.

| Before                                                       | After                                                        |
|:-------------------------------------------------------------|:-------------------------------------------------------------|
| `actra\yuf\auth\AuthMethod`                                  | `actra\yuf\auth\AuthMethodEnum`                              |
| `actra\yuf\auth\AuthResult`                                  | `actra\yuf\auth\AuthResultEnum`                              |
| `AuthUser::__construct(ID: …)`, `AuthUser::$ID`               | `AuthUser::__construct(id: …)`, `AuthUser::$id`               |
| `Authenticator::logAuthResult(userID: …, sessionID: …)`       | `Authenticator::logAuthResult(userId: …, sessionId: …)`       |
| `AuthSession::getAuthSessionID()`                            | `AuthSession::getAuthSessionId()`                            |
| `AuthSession::logIn(authSessionID: …)`                       | `AuthSession::logIn(authSessionId: …)`                       |
| `AbstractSessionHandler::getID()`, `regenerateID()`          | `AbstractSessionHandler::getId()`, `regenerateId()`          |
| `MicrosoftAuthenticator`, `MicrosoftIdToken`: `tenantID:`, `clientID:` | `tenantId:`, `clientId:`                           |

The enum cases are unchanged. `AuthSession` stores the auth session ID under the session key `authSessionId` (was
`authSessionID`). Users who are logged in when you deploy this version are logged out once
(`AuthSession::isLoggedIn()` returns `false` for a session without auth session ID) and have to log in again.

yuf calls `logAuthResult()` with named arguments, so an override with the old argument names fails with "Unknown named
parameter". Rename the arguments of the override:

```php
// Before
public function __construct(int $userId, …)
{
    parent::__construct(ID: $userId, …);
}

protected function logAuthResult(?int $userID, string $sessionID, string $ip, string $userName, AuthResult $authResult): void

$userId = $authUser->ID;
$authSessionId = AuthSession::getAuthSessionID();
AuthSession::logIn(authSessionID: $authSessionId);
$sessionId = AbstractSessionHandler::getSessionHandler()->getID();

// After
public function __construct(int $userId, …)
{
    parent::__construct(id: $userId, …);
}

protected function logAuthResult(?int $userId, string $sessionId, string $ip, string $userName, AuthResultEnum $authResult): void

$userId = $authUser->id;
$authSessionId = AuthSession::getAuthSessionId();
AuthSession::logIn(authSessionId: $authSessionId);
$sessionId = AbstractSessionHandler::getSessionHandler()->getId();
```

---

## [v4.15.0] – 2026-10-08

### ⚠️ Views get a `ViewContext`

`BaseView::__construct()` has the new first argument `ViewContext $context` and no longer reads `RequestHandler::get()`,
`ContentHandler::get()` or `JsonRequestBody::get()`. Every view has to accept the context and pass it on;
`ClassNameViewFactory` creates views with `new $className(context: $context)`.

Before:

```php
final class index extends BaseView
{
    public function __construct()
    {
        parent::__construct(requiredViewGroupName: 'frontend', …);
    }
}
```

After:

```php
final class index extends BaseView
{
    public function __construct(ViewContext $context)
    {
        parent::__construct(context: $context, requiredViewGroupName: 'frontend', …);
    }
}
```

Views of a `ViewMap` get the context as closure argument:

```php
// Before
create: fn(ViewContext $context): BaseView => new IndexView(greeting: 'Hello World'),
// After
create: fn(ViewContext $context): BaseView => new IndexView(context: $context, greeting: 'Hello World'),
```

A view reads the request data from `$this->context` (for example in a shared base view):

```php
// Before
$route = RequestHandler::get()->route;
$value = RequestHandler::get()->pathVars[1];
$contentType = ContentHandler::get()->getContentType();

// After
$route = $this->context->route;
$value = $this->context->pathVars->get(nr: 1);
$contentType = $this->context->content->getContentType();
```

`ViewContext` also has `fileGroup`, `fileTitle`, `getHtmlDocument()` and `getJsonRequestBody()`.

### ⚠️ `ViewContext` constructor changed

`ViewContext` is a `final class` (no longer `readonly`) and has the new arguments `PathVars $pathVars` and
`ContentHandler $content`. Only yuf creates it; create it yourself (for tests) like this:

```php
new ViewContext(
    route: $route,
    fileGroup: null,
    fileTitle: 'index',
    pathVars: new PathVars(values: ['index']),
    content: new ContentHandler(contentType: ContentType::createHtml()),
);
```

### ⚠️ `HtmlDocument::get()` is removed

Before:

```php
HtmlDocument::get()->replacements->addEncodedText(identifier: 'title', content: 'Hello');
```

After, in a view:

```php
$this->getHtmlDocument()->replacements->addEncodedText(identifier: 'title', content: 'Hello');
```

Outside a view: `$context->getHtmlDocument()` or `$contentHandler->getHtmlDocument()` (one instance per request).

### ⚠️ `JsonRequestBody::get()` is removed

Before:

```php
$body = JsonRequestBody::get();
```

After, in a view (an invalid body still sends the error response):

```php
$body = $this->getJsonRequestBody();
```

Outside a view: `$context->getJsonRequestBody()`, or `JsonRequestBody::fromString(json: $json)` for a JSON string
(throws an `InvalidArgumentException` for invalid JSON, an empty string is an empty object).

### `ContentHandler` and `HtmlDocument` have public constructors

`new ContentHandler(contentType: ContentType::createHtml())` and `new HtmlDocument()` can be created directly.
`ContentHandler::register()`, `ContentHandler::get()` and `RequestHandler::get()` work as before.
`HtmlDocument` still reads the current request in its constructor, so it is not yet usable without a request.
`ContentHandler::register()` throws a `LogicException` for a route without `defaultContentType` (was a `TypeError`).

---

## [v4.14.0] – 2026-10-08

### Views with constructor arguments

A `Route` can get a `ViewFactory`. `ViewMap` maps the file name to a closure that creates the view, so views can
receive their dependencies through the constructor and have any class name. Without a factory, views are created as
before (`ClassNameViewFactory`).

```php
new Route(
    path: '/',
    viewGroup: 'frontend',
    viewFactory: new ViewMap()->add(
        fileTitle: 'index',
        create: fn(ViewContext $context): BaseView => new IndexView(greeting: 'Hello World'),
    ),
);
```

The content file (`html/index.html`), the language files and `RequestHandler::$fileTitle` still use the file name.
`ClassNameViewFactory::createView()` throws a `LogicException` (before: `Exception`, same message) for a class that does
not extend `BaseView`.

### ⚠️ `Route::getPhpClassName()` is removed

Before:

```php
$className = $route->getPhpClassName();
```

After:

```php
$className = new ClassNameViewFactory()->createClassName(
    context: new ViewContext(route: $route, fileGroup: $fileGroup, fileTitle: $fileTitle),
);
```

---

## [v4.13.0] – 2026-10-08

### The duplicate route path check is part of `RouteCollection`

The check for a duplicate route path moves from the `Route` constructor to `RouteCollection::addRoute()` (and thus the
`RouteCollection` constructor), which still throws a `LogicException`. Routes with the same path in different
collections no longer throw. `Route` keeps no static state anymore. No code change needed.

---

## [v4.12.0] – 2026-10-08

Development dependency `actra/coding-standard` is now `^1.2.0`.

### ⚠️ Settings bundles and value objects without `Model` suffix

Coding standard v1.2.0: settings bundles end with `Settings`, value objects have no type suffix. The old names are
removed.

| Before                                       | After                                   |
|:---------------------------------------------|:----------------------------------------|
| `actra\yuf\db\DbSettingsModel`               | `actra\yuf\db\DbSettings`               |
| `actra\yuf\session\SessionSettingsModel`     | `actra\yuf\session\SessionSettings`     |
| `actra\yuf\security\CspPolicySettingsModel`  | `actra\yuf\security\CspPolicySettings`  |
| `actra\yuf\table\TableItemModel`             | `actra\yuf\table\TableItem`             |

The named arguments and the property are renamed as well:

```php
// Before
new FileSessionHandler(sessionSettingsModel: new SessionSettingsModel());
$core->prepareHttpResponse(cspPolicySettingsModel: new CspPolicySettingsModel());
$core->cspPolicySettingsModel;
FrameworkDB::getInstance(dbSettingsModel: $dbSettingsModel);
new CallbackColumn(…, callback: fn(TableItemModel $tableItemModel): string => …);
protected function renderCellValue(TableItemModel $tableItemModel): string

// After
new FileSessionHandler(sessionSettings: new SessionSettings());
$core->prepareHttpResponse(cspPolicySettings: new CspPolicySettings());
$core->cspPolicySettings;
FrameworkDB::getInstance(dbSettings: $dbSettings);
new CallbackColumn(…, callback: fn(TableItem $tableItem): string => …);
protected function renderCellValue(TableItem $tableItem): string
```

### ⚠️ `SelectOptionsSettings` is internal

The trait is renamed to `HasSelectOptionsPresentation` and marked `@internal`. Use `SelectOptionsField` or
`MultiSelectOptionsField`.

---

## [v4.10.1] – 2026-10-07

### 🐛 Bug Fixes

* **Security:** `AbstractSessionHandler` only reads the session ID from the cookie. With
  `SessionSettingsModel::$individualName`, a GET or POST parameter with the session name replaced the session ID of the
  cookie, so an attacker could make a victim use a session the attacker knows (session fixation). Links or forms that
  pass the session ID as a parameter no longer switch the session.

---

## [v4.10.0] – 2026-10-07

Security hardening following the Actra coding standard (`standards/security.md`).

### ⚠️ The CSRF token is never read from the URL

`Form` (`CsrfTokenField`) only accepts the posted token; the fallback to the query string (`?csrftoken=…`) is removed,
and so is `CsrfToken::renderAsGetParam()`. Tokens in URLs end up in logs, the browser history and the `Referer`
header.

```php
// Before: the token in the URL was accepted when it was not posted
$url = '?' . $form->sentIndicator . '&' . CsrfToken::renderAsGetParam();

// After: send the form with POST, the hidden field contains the token
$form = new Form(name: 'contact');
```

### ⚠️ GET forms have no CSRF token

A `Form` with `methodPost: false` no longer adds the CSRF field: a GET request must not change state, and the token
would be in the URL. The rendered HTML of GET forms has no hidden `csrftoken` field anymore. Forms that change data
must use POST (the default):

```php
// Before: a GET form that changes data, protected by the token in the URL
$form = new Form(name: 'delete', methodPost: false);

// After
$form = new Form(name: 'delete');
```

### ⚠️ `TableFilter` checks the CSRF token

The filter input is only applied when it is posted with the CSRF token of the user (the rendered filter form does
that). A filter sent with GET or without a valid token is ignored, and the previous filter stays. Links that set a
filter in the URL (`?myFilter&find&…`) do not work anymore; the reset link still works.

### ⚠️ A new CSP nonce for every request

`CspNonce::get()` returns a new random nonce for every request (the same within the request) instead of one nonce per
session. It is no longer stored in the session, also works without a session (pages without session now get a nonce in
the `Content-Security-Policy` header), and `CspNonce::SESSION_INDICATOR` is removed. HTML that is loaded later (e.g.
via AJAX) and contains inline scripts or styles with the nonce of an earlier response is blocked; render such HTML
without inline code, or with the nonce of the response that loads it.

`AbstractSessionHandler::clearUserData()` keeps the data of the session handler and the preferred language.

### ⚠️ New security headers

Every `HttpResponse` sends `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`.
Browsers no longer guess the type of a response, so files must be delivered with the correct `Content-Type`.
Override the headers with `HttpResponse::setHeader()` if a project needs other values.

---

## [v4.9.2] – 2026-10-07

### 🐛 Bug Fixes

* **Security:** `HttpResponse` always sends `Strict-Transport-Security: max-age=31536000` (one year). File responses
  used their cache `maxAge` for HSTS, so `CSVFile` and `FileHandler` downloads (`maxAge: 0`) sent `max-age=0`, which
  removes HSTS in the browser. The `maxAge` of `createResponseFromFilePath()` only sets the `Expires` header now.
* `CsrfToken::validateToken()` compares the token with `hash_equals()` (timing-safe) instead of `===`.
* The CSRF token and the CSP nonce are generated with `random_bytes()` instead of `openssl_random_pseudo_bytes()`.
* `AbstractSessionHandler` deletes the old session file when it regenerates the session ID (on login, logout and
  privilege change), so a stolen old session ID cannot be used anymore.

---

## [v4.9.1] – 2026-10-07

### 🐛 Bug Fixes

* **Security:** `IpValidator::isInWhitelist()` (IP whitelists of `BaseView` and `AuthUser`) checks ranges correctly.
  Update as soon as possible if a whitelist contains a range (`/`):
    * An IPv6 range (e.g. `2001:db8::/32`) allowed **every** IPv6 address, `::/0` also every IPv4 address, and
      `0.0.0.0/0` every IPv6 address. IPv6 ranges are supported now.
    * An IPv4 range whose address is not the network address was shifted: `192.168.1.77/24` allowed
      `192.168.1.77` to `192.168.2.76` instead of `192.168.1.0` to `192.168.1.255`.
    * An invalid range (`10.0.0.0/33`, `10.0.0.0/abc`, `10.0.0.300/8`) throws an `InvalidArgumentException` instead of a
      PHP error or a wrong result. Fix such entries in the configuration.
* IPv6 addresses in a whitelist match in any notation (`2001:0db8::0001` matches `2001:db8::1`).

---

## [v4.9.0] – 2026-10-07

### ⚙️ Backend & API

* New `FormField::hasTopFormComponent()` tells whether the field is part of a form yet.
* Reading `FormField::$topFormComponent` of a field that is not part of a form throws a `LogicException` with a hint
  to `Form::addField()` instead of the PHP `Error` "must not be accessed before initialization". The type stays
  `Form`, no code change needed.
* All overriding methods have the `#[\Override]` attribute. Subclasses in projects are not affected.

### ⚠️ `UploadedFile::getHash()` uses SHA-256 instead of SHA-1

The hash is the key of the file in `FileField::getFiles()` and the value of the remove button of an uploaded file, so the
rendered HTML changes (64 instead of 40 hex characters). Projects that use `getHash()` need no change; tests or code that
compute the hash themselves adapt:

```php
// Before
$hash = sha1($uploadedFile->path);

// After
$hash = $uploadedFile->getHash();
```

A remove request of a form that was rendered before the update is ignored; the user removes the file again.

### 🐛 Bug Fixes

* `FileField` creates the pointer to the uploaded files of the session with `random_bytes()` instead of `uniqid()`, so
  it cannot be guessed.

---

## [v4.8.2] – 2026-10-07

### 🐛 Bug Fixes

* `HttpResponse::createResponseFromFilePath()` shows CSS, JPG, GIF, PNG and MOV files in the browser again instead of
  always forcing a download (`ContentType::$forceDownloadByDefault` was `true` for all of them). Other unknown file
  types are still downloaded. To keep forcing the download, pass it explicitly:
  ```php
  // Before: forceDownload: null downloaded these files
  HttpResponse::createResponseFromFilePath(absolutePathToFile: $path, forceDownload: null, individualFileName: null, maxAge: 0);

  // After: request the download explicitly
  HttpResponse::createResponseFromFilePath(absolutePathToFile: $path, forceDownload: true, individualFileName: null, maxAge: 0);
  ```

---

## [v4.8.1] – 2026-10-07

yuf is now developed with the [Actra coding standard](https://github.com/Actra-AG/coding-standard): all files are
formatted with PHP-CS-Fixer (PER Coding Style) and checked with the strict PHPStan configuration. No code change is
needed in projects.

### 🐛 Bug Fixes

* `AuthWebToken` decodes the Base64 parts of a token strictly: a token with invalid characters is rejected with an
  `UnauthorizedException` instead of being decoded without these characters.
* `StringUtils::randomString()`, `StringUtils::generateSalt()` and the name of the temporary file of
  `CSVFile::createTemporaryFile()` use the cryptographically secure `random_int()` instead of `mt_rand()` / `rand()`.

---

## [v4.8.0] – 2026-10-07

### ⚙️ Backend & API

* **Clear the session on logout.** New `AbstractSessionHandler::clearUserData()` (static) removes all data of the user
  from the session and keeps only the data of the session handler, the preferred language and the CSP nonce. See the
  README section "Clearing the session on logout".
* `AuthSession::logOut()` calls `clearUserData()` (security fix), so a logout no longer leaves the data of the previous
  user (breadcrumb, table and search state, uploads, CSRF token, project data, …) in the session. Data that has to
  survive a logout must be written to the session after `logOut()`:
  ```php
  // Before
  $_SESSION['loginMessage'] = 'Logged out';
  AuthSession::logOut();

  // After
  AuthSession::logOut();
  $_SESSION['loginMessage'] = 'Logged out';
  ```
* `CspNonce::SESSION_INDICATOR` is public now.
* **Migration hint:** projects that clear the session themselves after the logout with a copied list of yuf's session
  keys drop that code:
  ```php
  // Before
  AuthSession::logOut();
  $_SESSION = array_intersect_key($_SESSION, array_flip(['sessionCreated', 'trustedRemoteAddress', /* … */]));

  // After
  AuthSession::logOut();
  ```

### 🐛 Bug Fixes

* `AbstractSessionHandler::getSessionHandler()` throws a `LogicException` with a hint to `register()` instead of a
  `TypeError` when no session handler is registered. A session cookie that is not a string is discarded like an invalid
  session ID, and an invalid stored CSP nonce is replaced, instead of causing a `TypeError`.
* `AuthSession::getAuthSessionID()` throws an `UnexpectedValueException` instead of a `TypeError` when the session
  contains no auth session ID.
* The German default message `FormMessages::invalidCsrfToken` says "ungültiges CSRF-Token" instead of the incomplete
  "ungültiges CSRF".

---

## [v4.7.1] – 2026-10-06

### 🐛 Bug Fixes

* **German form texts.** `FormMessages::german()` returned two English texts. They are German now:

  | Property            | Before                                    | After                                |
  |:--------------------|:------------------------------------------|:-------------------------------------|
  | `selectEmptyOption` | `-- Please select --`                     | `-- Bitte auswählen --`              |
  | `invalidOption`     | `Selected invalid value in field [field]` | `Ungültige Auswahl im Feld [field].` |

  All other German texts are unchanged. No API change; only projects (or tests) that compare these texts must adapt.

---

## [v4.7.0] – 2026-10-05

### ⚙️ Backend & API

* **Typed values in table columns.** `TableItemModel::getRow(): DbRow` returns the row of a table column (e.g. in a
  `CallbackColumn`) as `DbRow`, with the same typed getters (`getInt()`, `getNullableString()`,
  `getDateTimeImmutable()`, `getEnum()`, …) and the same `DbRowValueException` for a missing column, an unexpected
  `NULL` or a wrong type. See the README section "Typed values in table columns".
* `getRawValue()`, `renderValue()`, `$data` and all built-in columns are unchanged. `renderValue()` of an array or
  object value now throws an `UnexpectedValueException` naming the column instead of a `TypeError`.
* No breaking changes.
* **Migration hint:** in callback columns, replace `getRawValue()` and casts of `renderValue()` with the typed getters:
  ```php
  // Before
  Category::getPath(id: $tableItemModel->getRawValue(name: 'ID'));
  Category::getPath(id: (int)$tableItemModel->renderValue(name: 'ID'));

  // After
  Category::getPath(id: $tableItemModel->getRow()->getInt(column: 'ID'));
  ```
  Unlike the cast, `getInt()` throws for `NULL` or a non-integer value; use `getNullableInt()` if `NULL` is allowed.

---

## [v4.6.0] – 2026-10-05

### ⚙️ Backend & API

* **Parameterized boolean search.** New `SearchHelper::createBooleanQuery(string $spaceSeparatedFieldNames,
  string $queryText): DbQueryData` (static). It has the search semantics of `getBooleanQuery()` (quoted phrases,
  `and`/`or`/`not`, `+word`/`-word`, case-insensitive, `OR` by default, HTML tags stripped), but binds every word as
  `field LIKE ? ESCAPE '!'` parameter. See the README section "Boolean search".
* Fixes the production bug of `getBooleanQuery()`: a `?` in the search text (e.g. `?haas kap`) made
  `DbQuery::addWherePart()` throw "The amount of parameters (0) does not match the amount of "?" placeholders".
  `%`, `_` and `\` are now searched literally instead of acting as wildcards or being removed.
* Field names are validated (column names, optionally `table.column`, `db.table.column` or quoted with backticks);
  an invalid one throws an `InvalidArgumentException`. There is no `$splitFields` argument: SQL expressions as search
  field are not supported any more.
* Differences only for malformed search texts, where `getBooleanQuery()` produced invalid SQL or crashed: an
  operator without a following word is ignored, an empty word (e.g. from `""`) is skipped, an empty search text gives
  `1=1` instead of `()`. Within a quoted phrase, ` +` and ` -` are no longer turned into `and`/`not`, and a quoted
  `"and"` is searched as a word.
* `getBooleanQuery()` is deprecated and unchanged (it still interpolates the words and fails on `?`).
* `addWildcardToString()` is deprecated (it does not escape `%` and `_`) and no longer used by yuf.
* No breaking changes.
* **Migration hint:** replace `getBooleanQuery()` with `createBooleanQuery()` and pass its parameters:
  ```php
  // Before
  $dbQuery->addWherePart(
      wherePart: $searchForm->searchHelper->getBooleanQuery(
          spaceSeparatedFieldNames: 'person.firstName person.lastName',
          query_text: $searchQuery
      ),
      parameters: []
  );

  // After
  $data = SearchHelper::createBooleanQuery(
      spaceSeparatedFieldNames: 'person.firstName person.lastName',
      queryText: $searchQuery
  );
  $dbQuery->addWherePart(wherePart: $data->query, parameters: $data->params);
  ```
  Code which concatenates the result into its own SQL (`$cond .= ' AND ' . getBooleanQuery(...)`) must also append
  `$data->params` to its parameter list.

### 🐛 Fixes in table filters and `createSQLSearch()`

* **`SearchHelper::createSQLFilters()`** (used by `TextFilterField`): LIKE patterns are escaped and bound as
  `LIKE ? ESCAPE '!'`. `*` remains the wildcard; `%` and `_` typed by the user are now searched literally (before, they
  were wildcards, so `50%` also found `500`). Quoted phrases in a multi-word search work again:
  ```text
  Search text       Before                                  After
  foo "bar baz"     LIKE %foo% OR %"bar% OR %baz"%          = 'bar baz' AND LIKE %foo%
  foo 'bar baz'     LIKE %foo % OR %bar baz%                LIKE %foo% OR %bar baz%
  -"foo bar"        NOT LIKE %"foo% AND LIKE %bar"%         NOT LIKE %foo bar%
  %foo%             LIKE %foo% (wildcards)                  LIKE %!%foo!%% (literal)
  - x               NOT LIKE % (only NULL) AND LIKE %x%     LIKE %x%
  ```
  Quotes only start a phrase at a word boundary, so `O'Neil O'Brien` are two words. `.`, `_`, `"exact"` and
  `*phrase*` are unchanged. The column reference may still be an SQL expression (e.g. `CONCAT_WS(' ', a, b)`), but an
  empty one or one containing `?` now throws an `InvalidArgumentException`.
* **`SearchHelper::createSQLSearch()`**: `%` and `_` are searched literally (`LIKE ? ESCAPE '!'`). Columns are
  validated (`InvalidArgumentException` for invalid names or an empty list) and every part is quoted separately:
  `t.city` becomes `` `t`.`city` `` instead of the invalid `` `t.city` ``; already quoted names are kept.
* **Migration hint:** nothing to change in code. If users relied on typing `%` or `_` as wildcards in a table filter,
  tell them to use `*`.

---

## [v4.5.0] – 2026-10-04

### ⚙️ Backend & API

* **Typed path variables.** `BaseView` got `getPathVarAsInt(int $nr): ?int`, `getRequiredPathVarAsInt(int $nr): int`
  and `getRequiredPathVarAsString(int $nr): string`. The required getters throw a `NotFoundException` (404) if the
  path variable is missing, not an integer or empty. Integers are strictly formatted as in `DbRow::getInt()` (optional
  minus and digits, no `+`, no spaces, overflow counts as not an integer). The logic lives in the new
  `actra\yuf\core\PathVars`. See the README section "Path variables".
* `getPathVar()` is unchanged. No breaking changes.
* **Migration hint:** replace `(int)$this->getPathVar(nr: 1)` and own null/empty checks that throw a
  `NotFoundException` with the new getters:
  ```php
  // Before
  $id = (int)$this->getPathVar(nr: 1);
  $slug = $this->getPathVar(nr: 2);
  if ($slug === null || $slug === '') {
      throw new NotFoundException();
  }

  // After
  $id = $this->getRequiredPathVarAsInt(nr: 1);
  $slug = $this->getRequiredPathVarAsString(nr: 2);
  ```
  Note: `(int)` turned `"abc"` or a missing value into `0`; the new getter answers with a 404 instead.

---

## [v4.4.0] – 2026-10-04

### 🧩 Forms

* **Non-null getters for required fields.** `IntegerField` (and `NumericField`), `HiddenIntegerField`, `FloatField`,
  `DecimalField`, `DateField` and `TimeField` got `getRequiredValueAsInt(): int`, `getRequiredValueAsFloat(): float`,
  `getRequiredValueAsDecimal(): string`, `getRequiredValueAsDateTimeImmutable(): DateTimeImmutable` and
  `getRequiredValueAsTimeOfDay(): TimeOfDay`. They are meant for a required field after a successful `validate()`.
* An empty field throws the new `actra\yuf\form\FormFieldValueMissingException` (extends `LogicException`). Its
  message names the field and says whether it is not required (use the nullable getter) or has no value (not validated
  yet / empty). A default value is never returned. Invalid input still throws `UnexpectedValueException`.
* The nullable getters are unchanged. No breaking changes.
* **Migration hint:** replace `(int)$field->getValueAsInt()`, `$field->getValueAsInt() ?? 0`,
  `(float)$field->getValueAsDecimal()` and own "required value" helpers (e.g. `RequiredDate::of($field)`) with the
  `getRequiredValueAs...()` getter of the field. Keep the nullable getter for optional fields. See the README section
  "Optional vs. required value".

---

## [v4.3.0] – 2026-10-04

### ⚙️ Backend & API

* **Typed database rows.** New `actra\yuf\db\DbRow` with typed getters (`getString`, `getInt`, `getFloat`,
  `getDecimal`, `getBool`, `getDateTimeImmutable`, `getEnum` and the `getNullable...` variants, `has()`). A missing
  column, `NULL` in a non-nullable getter or a wrong type throws the new `DbRowValueException` (extends
  `UnexpectedValueException`). See the README section "Typed database rows".
* New `FrameworkDB::selectRows(): list<DbRow>` and `FrameworkDB::selectRow(): ?DbRow` (at most one row, more throws the
  new `DbRowCountException`); `DbSelectStmt` got `executeAndFetchRows()` and `executeAndFetchRow()`. For a `DbQuery`
  pass `$data->query` and `$data->params` of its `DbQueryData`.
* `select()` and `DbSelectStmt::ExecuteAndFetch()` are unchanged. New code should use the typed methods.
* `getInt()` accepts `int` and strictly integer-formatted strings (`'-12'`), `getFloat()` also numeric strings,
  `getDecimal()` `string` (DECIMAL columns) and `int` but no `float`, `getBool()` `0`, `1`, `'0'`, `'1'`.
* ⏰ `getDateTimeImmutable()` parses DATE/DATETIME/TIMESTAMP values in the PHP default time zone. The time zone of the
  database session must match it, otherwise TIMESTAMP values are shifted.
* No breaking changes.
* **Migration hint:** replace casts such as `(string)$row->name` or `ScalarCast::toString($row->name)` with
  `$row->getString(column: 'name')` after switching `select()` to `selectRows()`.

---

## [v4.2.0] – 2026-10-04

### ⚙️ Backend & API

* **New `Clock` abstraction** (`actra\yuf\clock`): `Clock` (`now(): DateTimeImmutable`, signature identical to PSR-20
  `Psr\Clock\ClockInterface`), `SystemClock` (real time) and `FixedClock` (always the given time, for tests). See the
  README section "Clock".
* The following constructors/methods got an optional last parameter `Clock $clock = new SystemClock()`; existing calls
  keep working unchanged: `SessionFileUploadStorage` (and `forCurrentRequest()`), `Logger`, `LogFile`,
  `FileSessionHandler`, `AbstractSessionHandler` (protected constructor), `MicrosoftIdToken`,
  `DirectoryTemplateCache`, `DbQueryLogItem`, `MailMimeHeader`.
* New `IdTokenTimeClaimsValidator` (the `nbf`/`iat`/`exp` checks of `MicrosoftIdToken`, unchanged behaviour).
* Log timestamps of `Logger` and `LogFile` are unchanged (`Y-m-d H:i:s,` plus eight fractional digits).
* No breaking changes.
* **Migration hint:** if your project has its own `Clock`, `SystemClock` or `FixedClock` classes (method
  `now(): DateTimeImmutable`), delete them and switch the imports to `actra\yuf\clock\Clock`, `SystemClock` and
  `FixedClock`. The method signature is identical; a `FixedClock` takes the time as constructor argument `now:`.

---

## [v4.1.0] – 2026-10-04

### 🎨 Frontend & UI

* ⚠️ **Table pagination: English titles by default, configurable.** The titles of the previous/next icons are now
  `'Previous'` / `'Next'` instead of `'Zurück'` / `'Vor'`. `TablePaginationRenderer` has the new optional arguments
  `previousTitle` and `nextTitle`; the defaults of `Pagination::render()` changed the same way. To keep the German
  texts, pass them:

  ```php
  // Before: always "Zurück" / "Vor"
  new DbResultTable(identifier: 'users', db: $db, dbQuery: $query);

  // After
  new DbResultTable(
      identifier: 'users',
      db: $db,
      dbQuery: $query,
      tablePaginationRenderer: new TablePaginationRenderer(previousTitle: 'Zurück', nextTitle: 'Vor')
  );
  ```
  Direct callers of `Pagination::render()` pass `previousTitle: 'Zurück', nextTitle: 'Vor'`. The template has no other
  hard-coded texts.
* ⚠️ `Pagination::render()` now HTML-encodes `previousTitle` and `nextTitle`: pass plain text, not already encoded HTML
  (e.g. `'Back & forth'`, not `'Back &amp; forth'`).

---

## [v4.0.0] – 2026-10-04

### ⚙️ Backend & API

* ⚠️ **Form migration checklist (the common steps of the new typed form API, in this order):**
    1. Add `messages: FormMessages::german()` to every `new Form(...)` to keep the German texts (the default is
       English).
    2. Rename the enums: `InputTypeValue` to `InputTypeEnum`, `AutoCompleteValue` to `AutoCompleteEnum`,
       `RadioOptionsLayout` to `RadioOptionsLayoutEnum`, `CheckboxOptionsLayout` to `CheckboxOptionsLayoutEnum`.
    3. Replace `AmountField` with `IntegerField`, `FloatField` or `DecimalField(scale:)`, `HiddenField(valueIsInt: true)`
       with `HiddenIntegerField`, and the `acceptMultipleSelections`/`multiple` flags with `MultiSelectOptionsField`/
       `MultiToggleField`.
    4. Give every `PasswordField` a `purpose: PasswordPurposeEnum::CURRENT` (login) or `::NEW`; replace the `autoComplete`
       argument.
    5. Replace `getRawValue()` with the typed getter (`getValueAsString()`, `getValueAsInt()`, `getValueAsFloat()`,
       `getValueAsDecimal()`, `getValueAsDateTimeImmutable()`, `getValueAsTimeOfDay()`, `getValues()`, `isChecked()`,
       `getFiles()`); `getValueAsString()` of numbers, dates and times is gone (format the typed value).
    6. Replace `setValue(mixed)` + `setOriginalValue()` with the constructor value, the typed `setValue()` /
       `setValues()` / `setChecked()` or, in a subclass, the protected `setInitialValue()`.
    7. Replace custom rules that extend `FormRule` with a typed base (`StringRule`, `IntegerRule`, ...); replace
       `RequiredRule`, `MinValueRule`, `MaxValueRule`, `ValueBetweenRule`, `ValidAmountRule`, `ValidDateRule`,
       `ValidTimeRule`, `ZipCodeRule`, `PhoneNumberRule`, `ValidCsrfTokenValue`, `NoArrayRule` and
       `ValidateAgainstOptions` (table below).
    8. Replace overrides of `FormField::validate(array, bool)` with rules or listeners; calls become
       `validate(input: FormInput::fromArray(...))` (`$form->validate()` is unchanged).
    9. Replace `addError(string, bool)` and `addErrorAsHtmlTextObject()` with `addError(HtmlText)`.
    10. Replace `FileDataModel`, `FileField::ERRMSG_*`/`VALUE_*` and `tmp_name` with `UploadedFile` (`path`) and
        `FormMessages`.
    11. Run PHPStan: the typed API turns wrong getters, setters and rule types into static errors.
* **Form: removed and renamed classes, methods and arguments (overview):** details follow in the topics below.

  | v3 | v4 |
  |:--|:--|
  | `AmountField(valueIsFloat: ...)` | `IntegerField`, `FloatField`, `DecimalField(scale:)` |
  | `HiddenField(valueIsInt: true)`, `HiddenField::getValueAsInt()` | `HiddenIntegerField` |
  | `SelectOptionsField(acceptMultipleSelections: true)`, `ToggleField(multiple: true)` | `MultiSelectOptionsField`, `MultiToggleField` |
  | `BooleanField` as `CheckboxOptionsField` (`getValues()`) | `BooleanField extends FormField`, `isChecked()`, `setChecked()` |
  | `PasswordField(autoComplete: ...)` | `PasswordField(purpose: PasswordPurposeEnum)` |
  | `DateTimeFieldCore`, `getValueAsString()` of numbers/dates/times | `getValueAsDateTimeImmutable()`, `getValueAsTimeOfDay()` (`TimeOfDay`), typed getters |
  | `getRawValue()`, `getOriginalValue()`, `setOriginalValue()`, `setValue(mixed)` | typed getters and setters, protected `setInitialValue()` |
  | `FormField::validate(array, bool $overwriteValue)` | `validate(FormInput)`, `validateCurrentValue()` |
  | `FileDataModel`, `FileField::VALUE_*`, `ERRMSG_*`, `FileField::setValue()` | `UploadedFile`, `UploadInput`, `FormMessages`, `FileUploadStorage` |
  | `RequiredRule` | `addRequiredRule(HtmlText)` |
  | `MinValueRule`, `MaxValueRule`, `ValueBetweenRule` | `IntegerMinRule`/`IntegerMaxRule`, `FloatMinRule`/`FloatMaxRule`, `DecimalMinRule`/`DecimalMaxRule` |
  | `MinLengthRule`/`MaxLengthRule` on lists | `MinCountRule`, `MaxCountRule` |
  | `ValidAmountRule`, `FloatValueRule`, `NumericValueRule`, `ValidDateRule`, `ValidTimeRule`, `NoArrayRule`, `ValidateAgainstOptions` | removed, the field checks its input |
  | `ZipCodeRule`, `PhoneNumberRule`, `ValidCsrfTokenValue` | removed, `ZipCodeValidator`, the fields, `CsrfTokenSource` |
  | `FormRule::validate(FormField)` | typed bases `StringRule`, `StringListRule`, `IntegerRule`, `FloatRule`, `DecimalRule` |
  | `addError(string, bool)`, `addErrorAsHtmlTextObject()` | `addError(HtmlText)` |
  | `InputTypeValue`, `AutoCompleteValue`, `RadioOptionsLayout`, `CheckboxOptionsLayout` | `InputTypeEnum`, `AutoCompleteEnum`, `RadioOptionsLayoutEnum`, `CheckboxOptionsLayoutEnum` |
  | hard-coded German texts, `FormControl` "Abbrechen" | `FormMessages`, `FormMessages::german()` |
* **Form fields: text fields:** `TextField`, `EmailField`, `PhoneNumberField`, `HiddenField`,
  `PasswordField`, `TextAreaField` (and the fields built on them: `ZipCodeField`, `IbanNumberField`) now store a
  `string`, not `mixed`. New class hierarchy: `FormField` > `TextualField` >
  `InputField` > `StringInputField` (value, `getValueAsString()`, no public setter) > `SettableStringInputField`
  (adds `setValue(string)`).
    * ⚠️ **Setters, initial value and original value:** the public `setValue()` changes only the current value; the
      initial value (constructor value) stays, so `valueHasChanged()` compares with it. `getOriginalValue()` and
      `setOriginalValue()` are removed. To fill a field after `parent::__construct()` (e.g. with data from the database)
      a subclass calls the new protected `setInitialValue(string)` (current and initial value). It throws a
      `LogicException` as soon as `validate()` or `validateCurrentValue()` has run on the field. A project can also
      pass the value to the constructor.
      ```php
      // Before
      $field->setValue($row->name);
      $field->setOriginalValue($row->name);

      // After: constructor value (initial value) ...
      $field = new TextField(name: 'name', label: $label, value: $row->name);
      // ... or in a subclass, right after parent::__construct()
      $this->setInitialValue($customer->name);
      // change the value later (the initial value stays): setValue(string)
      $field->setValue($row->name);
      ```
    * ⚠️ **Invalid input resets the value:** array or manipulated input (`name[]=x`) is no longer ignored while the
      previous value stays. The value is reset to `''`, exactly one error "invalid input" is added and the rules do not
      run (no second "required" error). `validate()` returns `false`.
    * ⚠️ **Normalization:** `TextField` and the fields built on it (also `ZipCodeField`, `IbanNumberField`; the number
      and date fields are described below) store the trimmed text without zero-width spaces (U+200B), also for constructor
      values and setters. `EmailField` stores a valid address in its canonical form, `TextAreaField` and `HiddenField`
      remove only zero-width spaces (not trimmed), `PasswordField` is not normalized at all (v3 removed U+200B).
      An empty field has the value `''` (`getRawValue()` returned `null` for a missing key).
    * ⚠️ **`HiddenField` is string-only:** the constructor accepts `?string`, not `int|float|bool`
      (`new HiddenField(name: 'id', value: (string)$id, valueIsInt: true)`). `HiddenIntegerField` replaces the
      `valueIsInt` flag (see "numbers, date and time").
    * ⚠️ **`TextAreaField` is string-only:** the array value (one entry per line) is removed. Use the string value and
      `getValues()` (trimmed lines, no empty lines):
      ```php
      // Before
      $field = new TextAreaField(name: 'ns', label: $label, value: $nameservers);   // list<string>
      $nameservers = (array)$field->getRawValue();

      // After
      $field = new TextAreaField(name: 'ns', label: $label, value: implode(PHP_EOL, $nameservers));
      $nameservers = $field->getValues();                      // list<string>
      $field->setValue(implode(PHP_EOL, $nameserversFromDatabase));
      ```
      A subclass that overrode `validate()` to parse the lines must move its checks into rules: the per-line rule
      `addEachRule()` (see "Form rules").
    * ⚠️ **`PasswordField`:** the free `autoComplete` argument is replaced by the required argument
      `PasswordPurposeEnum $purpose` (`CURRENT`: login or confirming the password, `NEW`: registration, password
      change, reset). The field always renders the matching `autocomplete` attribute. A password field without
      `autoComplete` needs a purpose now. The class is `final`, has no public setter and no initial value, is not
      normalized and is never rendered back (`renderValue()` is `''`).
      ```php
      // Before
      new PasswordField(name: 'pw', label: $label, requiredError: $required,
          autoComplete: AutoCompleteValue::CURRENT_PASSWORD);

      // After
      new PasswordField(name: 'pw', label: $label, requiredError: $required, purpose: PasswordPurposeEnum::CURRENT);
      ```
    * ⚠️ **`final` classes:** `EmailField`, `PasswordField` and `PhoneNumberField` are `final`. Customize them with the
      constructor, setters, rules, listeners and the renderer. `TextField` and `TextAreaField` stay open
      (`HiddenField`, `ZipCodeField`, `IbanNumberField` and `CsrfTokenField` are `final` as well).
    * ⚠️ `InputFieldRenderer` takes an `InputField` only (it already read the `inputType` of the field, which an
      `OptionsField` does not have).
    * The v3.3.0 getters stay: `getValueAsString()` (now on `StringInputField` and `TextAreaField`), `getValues()` of
      `TextAreaField`.
* **Form fields: option fields and `BooleanField`:** `OptionsField` is split by value type. `SingleOptionsField`
  (value `string`, the option key, `''` = nothing selected) is the base of `RadioOptionsField`, `SelectOptionsField` and
  `ToggleField`; `MultiOptionsField` (value `list<string>`, empty is `[]`) is the base of `CheckboxOptionsField` and the
  new `MultiSelectOptionsField` and `MultiToggleField`. `BooleanField` has a `bool` value. The option fields stay
  non-final, `FormOptions` is `final`. New on every option field: `isSelected(string $optionKey): bool` and
  `isMultiple(): bool`.
    * ⚠️ **`Multi*` classes replace the flags:** `SelectOptionsField(acceptMultipleSelections: true)` and
      `ToggleField(multiple: true)` are removed. The constructor argument of the multi classes is `initialValues`
      (`list<string>`).
      ```php
      // Before
      $tags = new SelectOptionsField(name: 'tags', label: $label, formOptions: $options, initialValue: ['a', 'b'],
          acceptMultipleSelections: true);
      $areas = new ToggleField(name: 'areas', label: $label, formOptions: $options, initialValue: ['a'],
          multiple: true);

      // After
      $tags = new MultiSelectOptionsField(name: 'tags', label: $label, formOptions: $options,
          initialValues: ['a', 'b']);
      $areas = new MultiToggleField(name: 'areas', label: $label, formOptions: $options, initialValues: ['a']);
      ```
    * ⚠️ **Getters per class:** `getValueAsString(): string` on `RadioOptionsField`, `SelectOptionsField` and
      `ToggleField`; `getValues(): list<string>` on `CheckboxOptionsField`, `MultiSelectOptionsField` and
      `MultiToggleField`. Neither throws any more (invalid input resets the value). `getValues()` of a single
      `SelectOptionsField`/`ToggleField` is removed (use `getValueAsString()`, `''` means nothing selected, no list
      with one entry), `getValueAsString()` of a multiple select (it always threw) does not exist. A single field
      constructed with an array (`new SelectOptionsField(..., initialValue: ['a'])`) is a `TypeError`: use the multi
      class.
    * ⚠️ **A scalar posted to a multi field is invalid input:** `tags=a` instead of `tags[]=a` (also an empty
      `tags=`) was wrapped into a list in v3, now the value is reset to `[]`, one error "invalid input" is added and
      the rules do not run. A browser sends `tags[]`, so only manipulated requests change.
    * ⚠️ **Invalid options reset the value:** a posted key that is not one of the options (and a nested or non-string
      entry) no longer stays in the field. The value is reset to `''` or `[]`, exactly one error
      (`FormMessages::invalidOption`, `[field]` is the field name) is added and the other rules do not run (no second
      "required" error). An array posted to a single field is invalid input (v3 kept the previous value). Empty keys
      in a multi list (`['', 'a']`) are dropped, so `['0']` is a normal selection. `ValidateAgainstOptions` is not
      added to the fields any more (the check is part of reading the input; the class is removed).
    * ⚠️ **Typed setters and initial value:** constructor values and setters have the type of the value
      (`?string`, `list<string>`, a wrong type is a `TypeError`; keys are not checked against the options). The public
      setters change only the current value, the initial value stays, `valueHasChanged()` compares with it. A subclass
      fills the field after `parent::__construct()` with the protected `setInitialValue(?string)` (single),
      `setInitialValues(list<string>)` (multi) or `setInitiallyChecked(bool)` (`BooleanField`): current and initial
      value, a `LogicException` once the field was validated. `setOriginalValue()` and `getOriginalValue()` are
      removed.
      ```php
      // Before
      $field->setValue($row->status);               // single
      $field->setValue($row->tags);                 // checkbox / multiple select: array
      $field->setOriginalValue($row->tags);

      // After
      $field->setValue($row->status);               // ?string, null = nothing selected
      $field->setValues($row->tags);                // list<string> (setValue(array) throws a LogicException)
      // pre-fill in a subclass: $this->setInitialValues($row->tags);  // or the constructor argument initialValues
      ```
    * ⚠️ **`getAddedValues()`/`getRemovedValues()`** exist only on `MultiOptionsField` (`list<string>`, compared
      with the initial values). `valueHasChanged()` of a multi field compares the selection, the order of the keys
      does not matter (v3 compared the arrays strictly).
    * ⚠️ **`BooleanField` is no longer a `CheckboxOptionsField`:** it extends `FormField` and has a `bool` value.
      `instanceof CheckboxOptionsField` is `false`, `getValues()` and the `formOptions` property are removed. Use
      `isChecked()`, `setChecked(bool)` and the protected `setInitiallyChecked(bool)`. Input: `name[]=checked` (as
      rendered) and `name=checked` are checked, a missing value is not checked, any other value is invalid input (one
      error, not checked). The layouts and their HTML are unchanged.
      ```php
      // Before
      $agree = new BooleanField(name: 'agree', label: $label, isCheckedByDefault: false, requiredError: $required);
      if (in_array('checked', $agree->getValues(), true)) { ... }

      // After: unchanged constructor
      if ($agree->isChecked()) { ... }
      ```
    * ⚠️ **Default texts come from `FormMessages`:** the required text of a `RadioOptionsField` without
      `requiredError` (`selectOneOption`), the empty option of a required `SelectOptionsField` or
      `MultiSelectOptionsField` (`selectEmptyOption`) and the invalid option text (`invalidOption`). With
      `FormMessages::german()` they are the v3 texts, without it English. The empty option label is read when it is
      rendered (`emptyValueLabel` is a computed property), so the messages of the form are used.
    * ⚠️ **`ToggleField` and `MultiToggleField`:** the child handling moved into `ToggleChildren` (composition), the
      markup into `ToggleFieldRenderer` (the field sets it as its renderer in the constructor, override
      `getDefaultRenderer()` in a subclass to change it). `addChildField()`, `addChildComponent()`, `getChildField()`,
      `getChildComponent()` and `childrenByMainOption` stay. The public string property `defaultChildFieldRenderer`
      is replaced by a method with a closure. Child fields now get the form (`topFormComponent`) and its messages,
      so listeners on them work. Like every field, a toggle field can be rendered only once.
      ```php
      // Before
      $toggle->defaultChildFieldRenderer = MyChildRenderer::class;

      // After
      $toggle->setDefaultChildFieldRenderer(rendererFactory: fn(FormField $child) => new MyChildRenderer($child));
      ```
    * ⚠️ **Renderers:** `DefaultOptionsRenderer`, `CheckboxItemRenderer` (now `CheckboxOptionsField|BooleanField`) and
      `SelectOptionsRenderer` (now `SelectOptionsField|MultiSelectOptionsField`) mark the selected options with
      `isSelected()` (an exact comparison of the keys as strings; v3 compared loosely, e.g. `'1.0'` with `'1'`). A
      custom renderer reads `isSelected($optionKey)` instead of `getRawValue()`. `SelectOptionsField::$cssClasses`,
      `$renderEmptyValueOption` and `$placeholder` are still readable but no longer `readonly` (`private(set)`);
      `SelectOptionsField::getDataAttributes()` has the type `array<string, string>`.
* **Form fields: numbers, date and time:** `AmountField`, `DateTimeFieldCore`,
  `ValidAmountRule`, `ValidDateRule` and `ValidTimeRule` are removed. The fields parse their text themselves (an
  `AmountParser`/date/time parser in `accept()`) and hold a typed value. New classes: `IntegerField` (`?int`),
  `FloatField` (`?float`), `DecimalField` (`?string`, bcmath, for money), `HiddenIntegerField` (`?int`),
  `actra\yuf\common\TimeOfDay`; `NumericField` (now `final`, extends `IntegerField`), `DateField` (`?DateTimeImmutable`)
  and `TimeField` (`?TimeOfDay`) changed their value type. Input that cannot be parsed is kept for re-rendering, the
  typed value does not exist then and the field's error is added (`individualInvalidError`/`invalidError`, else
  `FormMessages::invalidValue`). Empty input gives `null`.
    * ⚠️ **`AmountField` is split into three classes:**
      ```php
      // Before
      $qty = new AmountField(name: 'qty', label: $label, valueIsFloat: false, initialValue: 5, requiredError: $required);
      $length = new AmountField(name: 'length', label: $label, valueIsFloat: true, initialValue: 1.5);
      $price = new AmountField(name: 'price', label: $label, valueIsFloat: true, initialValue: 12.5);   // money

      // After
      $qty = new IntegerField(name: 'qty', label: $label, initialValue: 5, requiredError: $required);
      $length = new FloatField(name: 'length', label: $label, initialValue: 1.5);          // measurements
      $price = new DecimalField(name: 'price', label: $label, scale: 2, initialValue: '12.50');   // money
      ```
      The constructor arguments are those of `AmountField` without `valueIsFloat` (`initialValue` has the type of the
      value). `DecimalField` needs the argument `scale` (decimals, `2` for CHF) and returns a string like `'12.50'`
      (`getValueAsDecimal(): ?string`, no float rounding errors, calculate with `bcadd()` etc.). Accepted input is that
      of v3 (`AmountParser`: sign, digits, dot, surrounding whitespace; no exponent, no comma, no thousands separator,
      nothing outside of the `int`/finite `float` range). **A `DecimalField` rejects more decimals than `scale`**
      (`'12.555'` with scale 2 is an error, it is never rounded; trailing zeros are accepted, `'12.500'` is `'12.50'`),
      fewer decimals are filled up (`'12'` becomes `'12.00'`, `'-0'` becomes `'0.00'`).
    * ⚠️ **No `getValueAsString()` on numbers, dates and times** (it existed on `AmountField` since v3.3.0 and on
      `DateField`/`TimeField`). Use the typed getter and format it yourself:
      ```php
      // Before
      $text = $qtyField->getValueAsString();       // '' or '5'
      $text = $dateField->getValueAsString();      // 'Y-m-d' or ''
      $text = $timeField->getValueAsString();      // 'H:i:s' or ''

      // After
      $text = (string)$qtyField->getValueAsInt();                              // '' becomes null: use ?? ''
      $text = $dateField->getValueAsDateTimeImmutable()?->format('Y-m-d') ?? '';
      $text = $timeField->getValueAsTimeOfDay()?->toString() ?? '';
      ```
      The getters are `getValueAsInt(): ?int` (`IntegerField`, `NumericField`, `HiddenIntegerField`),
      `getValueAsFloat(): ?float` (`FloatField` only: `IntegerField` and `DecimalField` have no `getValueAsFloat()`
      any more), `getValueAsDecimal(): ?string`, `getValueAsDateTimeImmutable(): ?DateTimeImmutable` and
      `getValueAsTimeOfDay(): ?TimeOfDay`. They return `null` for an empty field and throw an
      `UnexpectedValueException` naming the field if the field holds input that could not be parsed (before
      validation or after a failed validation). A getter that does not fit the type does not exist (a PHPStan error instead of a
      runtime exception).
    * ⚠️ **`HiddenField(valueIsInt: true)` becomes `HiddenIntegerField`:** the `valueIsInt` argument and
      `HiddenField::getValueAsInt()` are removed (`HiddenField` is string-only). Manipulated input is a validation
      error, so `getValueAsInt()` never fails after a successful validation.
      ```php
      // Before
      $idField = new HiddenField(name: 'id', value: (string)$id, valueIsInt: true);
      // After
      $idField = new HiddenIntegerField(name: 'id', value: $id);   // ?int
      ```
    * ⚠️ **`NumericField` has an integer value and is `final`:** `getValueAsString()` is gone, `getValueAsInt()`
      stays, the constructor takes `?int $initialValue` (was `null|int|float`). It is an `IntegerField`, so leading
      zeros are not kept (`'007'` becomes `7`, rendered as `7`). For codes with leading zeros use a `TextField` with a
      `RegexRule('/^\d{4,6}$/')`. HTML (`inputmode="numeric"`, `pattern`, `maxlength`) is unchanged.
    * ⚠️ **`DateField` and `TimeField`: constructor value and type:**
      ```php
      // Before
      $date = new DateField(name: 'd', label: $label, value: '2020-01-02', invalidError: $invalid);
      $time = new TimeField(name: 't', label: $label, value: '08:30', invalidError: $invalid);
      $isoDate = $date->getValueAsString();
      $timeText = $time->getValueAsString();

      // After
      $date = new DateField(name: 'd', label: $label, value: new DateTimeImmutable('2020-01-02'), invalidError: $invalid);
      $time = new TimeField(name: 't', label: $label, value: new TimeOfDay(hour: 8, minute: 30), invalidError: $invalid);
      $time = new TimeField(name: 't', label: $label, value: TimeOfDay::fromString('08:30'), invalidError: $invalid);
      $isoDate = $date->getValueAsDateTimeImmutable()?->format('Y-m-d');
      $timeValue = $time->getValueAsTimeOfDay();     // ?TimeOfDay
      ```
      The input formats are those of v3 (`Y-m-d`, `Y-n-j` and `d.m.Y` for dates, `H:i` and `H:i:s` for times);
      impossible dates (`2020-02-30`) and times (`25:00`) are invalid. The date value is at 00:00:00 (only the date
      part of a given `DateTimeImmutable` is used), the field renders `Y-m-d`; the time field renders `H:i` (seconds of
      the value are not shown, as in v3). `DateField` and `TimeField` are `final`.
    * **New `actra\yuf\common\TimeOfDay`** (`final readonly`: `hour`, `minute`, `second`): the constructor throws a
      `ValueError` for values out of range, `TimeOfDay::fromString('08:30')` (`H:i` or `H:i:s`) returns `null` for
      anything else, `toString()` (`H:i:s`), `toShortString()` (`H:i`), `equals()`.
    * ⚠️ **Typed setters and initial value** (as for the text fields): the constructor value has the type of the
      value (`?int`, `?float`, `?string` for `DecimalField`, `?DateTimeImmutable`, `?TimeOfDay`; a wrong type is a
      `TypeError`, a `DecimalField` string that is no decimal or has too many decimals an `InvalidArgumentException`,
      also a `FloatField` value of `INF` or `NAN`). The public `setValue()` changes only the current value (the
      initial value stays, `valueHasChanged()` compares with it), clears kept invalid input and adds no error.
      `IntegerField` has the protected `setInitialValue(?int)` for subclasses (current and initial value, a
      `LogicException` after `validate()`); the other number and date fields are `final`, pass the value to the
      constructor.
    * ⚠️ **Rendering:** the fields render the canonical text of their value, which differs from the posted text for
      equivalent input: an integer without sign and leading zeros (`'+007'` renders `7`), a `FloatField` the shortest
      text that parses to the same value without exponent (`'7.50'` renders `7.5`), a `DecimalField` the value with
      its scale (`12.5` renders `12.50`). Invalid input is rendered as posted (trimmed). The HTML structure and
      attributes of all fields are unchanged.
    * ⚠️ **Removed rules:** `ValidAmountRule`, `ValidDateRule`, `ValidTimeRule` (the field checks its input; a rule
      that read them is not needed), `FloatValueRule` and `NumericValueRule` too. A custom rule that used
      `ValidAmountRule` on a text field validates the text itself with `AmountParser::isInteger()`, `isDecimal()`,
      `toInt()`, `toFloat()` or the new `toDecimal(value, scale)`.
    * ⚠️ **`MinValueRule`/`MaxValueRule`/`ValueBetweenRule`:** replaced by the typed numeric rules, see "Form rules".
    * ⚠️ `HiddenFieldRenderer` takes an `InputField` (was `HiddenField`) so that it renders `HiddenIntegerField` too.
* **Form fields: phone number, zip code, IBAN, CSRF token:** the checks that belong to one
  field moved from rules into the fields and into two pure validators. New `ZipCodeValidator::validate(zipCode,
  countryCode)` and `IbanValidator::validate(input)` (`actra\yuf\datacheck\validatorTypes`, no global state, usable
  without a form). `ZipCodeField`, `IbanNumberField` and `HiddenField` are now `final`.
    * ⚠️ **Removed rules:** `ZipCodeRule`, `PhoneNumberRule` and `ValidCsrfTokenValue`. The fields run the checks
      themselves when they are validated (zip code: `ZipCodeValidator` with the country code of the field, phone
      number: the parser with the country code of the field, CSRF: the token source); the error texts are those of the
      constructor arguments (`individualInvalidError`, `invalidErrorMessage`), the zip code and CSRF defaults come from
      `FormMessages` (`invalidZipCode`, `invalidCsrfToken`, German with `FormMessages::german()`). A project that added
      one of these rules to a field removes the line:
      ```php
      // Before
      $zip->addRule(new ZipCodeRule(defaultErrorMessage: $invalid));   // already part of ZipCodeField
      $form->getField('csrftoken')->addRule(new ValidCsrfTokenValue());

      // After: nothing to add, the field checks itself. Own checks of a zip code or IBAN without a field:
      $isValid = ZipCodeValidator::validate(zipCode: $input, countryCode: 'CH');
      $isValid = IbanValidator::validate(input: $input);
      ```
    * ⚠️ **`PhoneNumberField` stores the internal format from the start:** a valid number is stored as `+41.446681800`
      not only after `validate()` but also for the constructor value and `setValue()` (with the country code of the
      field); `getValueAsString()` returns it. An invalid number stays as typed (trimmed). `valueHasChanged()` is the
      plain comparison of the stored values (the number `044 668 18 00` and `+41 44 668 18 00` are the same value).
      The country code (`countryCode`, posted with the field as `countryCodeFieldName`) is applied before the number
      is read; non-text country code input is still ignored.
      ```php
      // Before
      $field = new PhoneNumberField(name: 'phone', label: $label, value: '044 668 18 00', invalidErrorMessage: $invalid);
      $field->getRawValue();        // '044 668 18 00' until validate(), then '+41.446681800'

      // After
      $field->getValueAsString();   // '+41.446681800' at once
      ```
    * ⚠️ **`IbanValidator` is strict about characters:** a character other than a letter or digit (after removing
      spaces) makes the IBAN invalid (v3 ignored unknown characters and raised a PHP warning), and so does an IBAN
      longer than 34 characters. Valid IBANs are unchanged (known country, mod-97 checksum, no length check per
      country, spaces and case are ignored).
    * ⚠️ **`CsrfTokenField` extends `InputField`, not `HiddenField`:** `instanceof HiddenField` is `false` for it, it has
      neither a getter nor a setter and renders the token of its source, never the posted one. New interface
      `actra\yuf\security\CsrfTokenSource` (`getToken()`, `isValid()`) and its default `SessionCsrfTokenSource` (wraps
      `CsrfToken`). The field takes the source as an optional constructor argument, `Form` as the new last argument
      `csrfTokenSource` (default: the session), so forms can be tested without a session. The token is read lazily
      (no session access before it is rendered or checked).
      ```php
      $form = new Form(name: 'contact', csrfTokenSource: new MyCsrfTokenSource());
      ```
      `Form::validate()` still checks the token after all other fields were valid, with the posted token and the
      fallback to the query string (`?csrftoken=...`), and still adds the message to the form. The text now comes from
      `FormMessages::invalidCsrfToken` (English by default, the v3 text with `FormMessages::german()`).
    * ⚠️ **HTML:** the value of an invalid phone number is HTML-encoded (v3.3.1 wrote it unencoded into the `value`
      attribute when the field had an error, so `"><script>` in the input broke out of the attribute: a cross-site
      scripting vulnerability, fixed). Everything else is unchanged (checked against the HTML of v3.3.1); posted zip
      codes and IBANs are rendered trimmed (see the normalization note above).
    * The hook `readAdditionalInput(FormInput)` reads input besides the field's own value (country code, fallback
      token, upload pointer).
* **Form fields: `FileField`:** `FileField` no longer touches `$_SESSION`, `$_SERVER`, `$_FILES`
  or the file system itself. It reads the uploads from `FormInput::getUploads()` and keeps the files between the
  requests in a `FileUploadStorage`. The default `SessionFileUploadStorage` does what v3 did (session list of the files,
  directory below the temp directory); tests and projects with other needs pass their own storage. `FileField` is now
  `final`, the value is `array<string, UploadedFile>` (`getFiles()`, no setter).
    * ⚠️ **`FileDataModel` is replaced by `UploadedFile`** (`actra\yuf\form\model`, `final readonly`): `tmp_name` is
      `path`, `error` is gone (a stored file is always an accepted upload), the hash used as array key and as
      `_removeAttachment` value is `getHash()`.
      ```php
      // Before
      foreach ($field->getFiles() as $hash => $file) {   // FileDataModel
          rename($file->tmp_name, $targetDirectory . '/' . basename($file->name));
          // $file->name, $file->type, $file->size, $file->error
      }

      // After
      foreach ($field->getFiles() as $hash => $file) {   // UploadedFile
          rename($file->path, $targetDirectory . '/' . basename($file->name));
          // $file->name, $file->type, $file->size
      }
      ```
      `name` and `type` are what the browser sent, as in v3: use `basename()` before you use the name in a path and do
      not trust the type (v3 did not check it either). `FileField` still has no maximum file size, mime type or extension
      check of its own (only PHP's `upload_max_filesize` / `post_max_size`).
    * ⚠️ **Removed constants:** `FileField::VALUE_NAME`, `VALUE_TMP_NAME`, `VALUE_TYPE`, `VALUE_ERROR`, `VALUE_SIZE` and
      `ERRMSG_FILE_EMPTY`, `ERRMSG_FILE_INCOMPLETE`, `ERRMSG_FILE_TOO_BIG`, `ERRMSG_FILE_TECHERROR`. The upload data
      is read by `FormInput::getUploads()` (`UploadInput`: `name`, `tmpName`, `type`, `error`, `size`), the texts are
      `FormMessages::fileEmpty`, `fileIncomplete`, `fileTooBig`, `fileTechnicalError` (without the trailing space and
      without the file name; the field appends the encoded file name). Code that searched the error texts of the
      field compares with the `FormMessages` property instead:
      ```php
      // Before
      str_starts_with($error, FileField::ERRMSG_FILE_TOO_BIG)

      // After
      str_starts_with($error, $form->messages->fileTooBig)
      ```
    * ⚠️ **Texts come from `FormMessages`:** "Nur [max] Datei(en) möglich.", the duplicate file name text
      (`tooManyFiles`, `duplicateFile`; the individual constructor arguments `tooManyFilesErrMsg` and
      `alreadyExistsErrorMessage` win as before) and the "löschen" button of the file list (`removeFile`) are the v3
      texts with `FormMessages::german()` and English otherwise (see "Form messages" below). With `german()` the HTML is
      unchanged.
    * ⚠️ **Removed:** `FileField::setValue()` (the files come in with the request, a project cannot add files to a
      field), the protected `convertMultiFileArray()` (use `FormInput::getUploads()`), `getRawValue()` (use
      `getFiles()`). `removeOldFiles()`, `clearData()`, `getFiles()`,
      `getRemovedValues()`, `getAddedValues()` (the files, as in v3), `uniqueSessFileStorePointer` and
      `maxFileUploadCount` stay.
    * **Optional storage argument:** `new FileField(..., storage: $storage)`, any `FileUploadStorage` (`load()`,
      `save()`, `store()`, `delete()`, `clear()`, `removeExpired()`). `store()` returns `null` if the upload cannot be
      stored; the field then adds the technical error of the file (v3 added a file that did not exist).
      ```php
      $field = new FileField(name: 'cv', label: $label, storage: new MyFileUploadStorage());
      ```
    * ⚠️ **Behaviour:** the session now holds plain arrays (`name`, `type`, `size`, `path`) per pointer instead of
      `FileDataModel` objects, so an upload that was started before the update is not found afterwards (the user
      uploads the file again). A manipulated `$_FILES` structure (missing keys, nested or non-scalar values) adds the
      error `FormMessages::invalidInput` and the uploaded files are kept (v3 ignored a structure without keys and failed
      with a `TypeError` or warnings on the others). A single `<input type="file" name="file">` is read as well (v3
      expected `file[]`). A posted `file_UID` that is empty is ignored (v3 accepted it and then stored the files in the
      root directory). The directory below the temp directory is named after `SERVER_NAME` with every character other
      than letters, digits, `.`, `_` and `-` replaced (the name can come from the `Host` header); `clearData()` also
      empties `getFiles()`.
* **Form messages:** the German texts of the form code ("Die ungültige Eingabe wurde ignoriert.", "Der angegebene Wert
  ist ungültig.", ...) are no longer hard-coded. New `FormMessages` with English defaults and `FormMessages::german()`
  with the v3 texts; the form hands them to its fields and to its `FormControl` (the cancel link text "Abbrechen",
  see "Form components, errors and renderers"; the message list is in `FormMessages`).
  ⚠️ **Attention:** without the argument the fields and the `FormControl` show the English texts.
  ```php
  // Before: German texts hard-coded
  $form = new Form(name: 'contact');

  // After: keep the German texts with one line
  $form = new Form(name: 'contact', messages: FormMessages::german());
  // own texts (named arguments, the rest stays English): new FormMessages(invalidInput: 'Ungültige Eingabe.')
  ```
* **Added:** `FormInput` (request data narrowed to text, list, missing or invalid, `FormInput::fromArray()`) and
  `InputShapeEnum`; fields read their request value from it. A posted array of strings keeps its keys: `getMap(name)`
  returns `qty[123]=2` as `[123 => '2']`, `getList(name)` the values without the keys. `getUploads(name)` returns the
  uploads of an input of `$_FILES` as `list<UploadInput>` (single and `name[]` structure) and `hasMalformedUpload(name)`
  tells that the structure was manipulated.
  `FormField::validateCurrentValue()` runs the listeners and rules without reading input.
* **Form rules:** rules get typed values (no `getRawValue()`, no `mixed`).
    * ⚠️ **Typed rules:** `FormRule` no longer has `validate(FormField)` (it only stores the error message). A rule
      extends the base that fits the value, and is a pure predicate that is called for a non-empty value only (no empty
      check, no `setValue()`):

      | Base | `validate()` | Added with |
      |:--|:--|:--|
      | `StringRule` | `validate(string $value)` | `addRule()` of all text fields (also number and date fields: the text), `SingleOptionsField` (the selected key) |
      | `StringListRule` | `validate(array $values)` (`list<string>`) | `addRule()` of `MultiOptionsField` |
      | `IntegerRule` | `validate(int $value)` | `addValueRule()` of `IntegerField` (and `NumericField`) |
      | `FloatRule` | `validate(float $value)` | `addValueRule()` of `FloatField` |
      | `DecimalRule` | `validate(string $value)` (canonical decimal) | `addValueRule()` of `DecimalField` |

      `addEachRule(StringRule)` applies a text rule to every line of a `TextAreaField` (`getValues()`) or to every
      selected key of a `MultiOptionsField`; a failing rule adds its message once. A rule that extends `FormRule`
      directly can no longer be added (`TypeError`, a PHPStan error). All rules stay non-final. The argument keeps
      its name `formRule` (also for `addEachRule()` and `addValueRule()`), so calls with named arguments stay valid.
      ```php
      // Before: reads the value of the field and checks its type itself
      class NoSpacesRule extends FormRule
      {
          public function validate(FormField $formField): bool
          {
              $value = $formField->getRawValue();
              return !is_string($value) || !str_contains($value, ' ');
          }
      }
      $field->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $message));

      // After: typed base, native parameter, called for non-empty text only
      class NoSpacesRule extends StringRule
      {
          public function validate(string $value): bool
          {
              return !str_contains($value, ' ');
          }
      }
      $field->addRule(formRule: new NoSpacesRule(defaultErrorMessage: $message));
      // an int value: extends IntegerRule + addValueRule(); a list of keys: extends StringListRule on a multi field
      ```
    * ⚠️ **Retyped rules:** `MinLengthRule`, `MaxLengthRule`, `RegexRule`, `ValidValueRule` and
      `ValidEmailAddressRule` extend `StringRule` (constructor arguments unchanged). `ValidValueRule` takes
      `list<string>` and compares exactly (v3 compared loosely, so `1` matched `'1'`). `ValidEmailAddressRule` checks
      syntax and DNS only: the canonical form (lower case) is set by `EmailField`, not by the rule, so a project that
      added the rule to a `TextField` must call `strtolower()` itself.
    * ⚠️ **`MinLengthRule`/`MaxLengthRule` on lists:** on a `MultiOptionsField` they counted the entries. Use the new
      `MinCountRule(minCount:, errorMessage:)` and `MaxCountRule(maxCount:, errorMessage:)` (`StringListRule`).
    * ⚠️ **`RequiredRule` is removed:** use `addRequiredRule(HtmlText)` (or the `requiredError` constructor argument),
      `isRequired()` stays. A second `addRequiredRule()` replaces the message of the first one.
      ```php
      // Before
      $field->addRule(new RequiredRule($message));
      // After
      $field->addRequiredRule(errorMessage: $message);
      ```
    * ⚠️ **`MinValueRule`, `MaxValueRule`, `ValueBetweenRule` are replaced** by `IntegerMinRule`/`IntegerMaxRule`,
      `FloatMinRule`/`FloatMaxRule` and `DecimalMinRule`/`DecimalMaxRule` (`addValueRule()`). They threw for every
      posted (string) value in v3.x, so they only worked with values a project set as `int`/`float`. "Between" is a
      min and a max rule. Decimal limits are decimal strings and are compared without float rounding (`bcmath`).
      ```php
      // Before
      $field->addRule(new MinValueRule(minValue: 1, errorMessage: $tooSmall));
      $field->addRule(new MaxValueRule(maxValue: 100, errorMessage: $tooBig));
      $field->addRule(new ValueBetweenRule(minValue: 1, maxValue: 100, errorMessage: $outOfRange));

      // After: IntegerField (FloatField, DecimalField)
      $field->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $tooSmall));
      $field->addValueRule(formRule: new IntegerMaxRule(max: 100, errorMessage: $tooBig));
      // ValueBetweenRule: a min and a max rule with the same message
      $field->addValueRule(formRule: new IntegerMinRule(min: 1, errorMessage: $outOfRange));
      $field->addValueRule(formRule: new IntegerMaxRule(max: 100, errorMessage: $outOfRange));
      // money: new DecimalMinRule(min: '0.05', errorMessage: $tooSmall)
      ```
    * ⚠️ **Removed rules:** `RequiredRule`, `FloatValueRule`, `NumericValueRule` (the number fields parse their
      input), `NoArrayRule` (array input is rejected when the input is read), `ValidateAgainstOptions` (the option
      check is part of reading the input of an options field), `ValueBetweenRule`, `MinValueRule`, `MaxValueRule`.
* **Form validation and request data:** `getRawValue()`, `setValue(mixed)`, `getOriginalValue()`,
  `setOriginalValue()` and `validate(array, bool)` are gone; every setter has its real type.
    * ⚠️ **`validate(array)` becomes `validate(FormInput)`:** `FormField::validate(FormInput $input): bool` is a
      `final` template (read the additional input, read the value, run listeners, check the required rule and the
      rules, validate the children of a toggle field if the field is valid). A subclass can no longer override it;
      customize through the constructor, `normalize()`, rules and listeners. The flag `overwriteValue: false` is now
      the method `validateCurrentValue()`. The listeners (`FormFieldListener`) are unchanged.
      ```php
      // Before
      $field->validate(['name' => 'Ann']);
      $field->validate([], overwriteValue: false);

      // After
      $field->validate(input: FormInput::fromArray(data: ['name' => 'Ann']));
      $field->validateCurrentValue();
      ```
    * ⚠️ **`Form::validate()` and `Form::isSent()` take an optional `FormInput`:** without argument they read the
      current request (`FormInput::fromGlobals(methodPost:)`: `$_POST` or `$_GET` for the values, `$_FILES`, `$_GET`
      for the sent indicator and the CSRF fallback), so a project that calls `$form->validate()` changes nothing. A
      project (or a test) can pass its own request:
      ```php
      $form->validate(input: FormInput::fromArray(data: $post, files: $files, query: ['contact' => '']));
      $form->isSent(input: $input);
      ```
      `FormInput::fromGlobals()` is the only place in `src/form/` that reads `$_POST`, `$_GET` and `$_FILES`. The
      form name check ("A Form with the name ... has already been defined.") is unchanged (its list moved into the
      `@internal` class `FormNameRegistry`; tests that build the same form name twice call
      `FormNameRegistry::reset()`).
    * ⚠️ **Removed from `FormField`:** `getRawValue()` (use the typed getter: `getValueAsString()`, `getValueAsInt()`,
      `getValueAsFloat()`, `getValueAsDecimal()`, `getValueAsDateTimeImmutable()`, `getValueAsTimeOfDay()`,
      `getValues()`, `isChecked()`, `getFiles()`; `getRawValue(true)` is the getter plus `isValueEmpty()`),
      `getOriginalValue()`, `setOriginalValue()` (the initial value is the constructor value or the protected
      `setInitialValue()`; `valueHasChanged()`, `getAddedValues()` and `getRemovedValues()` compare with it), the
      constructor argument `value` of `FormField` (`mixed`), and `getAddedValues()`/`getRemovedValues()` on fields
      other than `MultiOptionsField` and `FileField` (they returned `[]` before). `renderValue()`, `isValueEmpty()` and
      `valueHasChanged()` are abstract: a project field that extends `FormField` directly implements them and
      `readInput(FormInput)`.
    * ⚠️ **Typed setters:** `setValue(string)` (`TextField`, `EmailField`, `PhoneNumberField`, `HiddenField`,
      `TextAreaField`), `setValue(?string)` (single option fields, `DecimalField`), `setValue(?int)`
      (`IntegerField`, `HiddenIntegerField`), `setValue(?float)`, `setValue(?DateTimeImmutable)`,
      `setValue(?TimeOfDay)`, `setValues(list<string>)`, `setChecked(bool)`. A value of the wrong type is a PHPStan
      error (and a `TypeError` at runtime), no longer a `TypeError` from a `mixed` parameter. `PasswordField`,
      `CsrfTokenField` and `FileField` have no `setValue()` at all (it threw a `LogicException`), `BooleanField` and
      `MultiOptionsField` neither (use `setChecked()` / `setValues()`).
    * ⚠️ **Behaviour:** the text rules of `EmailField` run for a non-empty text after the required check, as before;
      an unparsable number or date still gets its error before the other rules, and the rules for the typed value
      (`addValueRule()`) do not run for it. A text rule runs for the text of a number or date field too (the canonical
      text when the value is valid).

* **Form enums renamed:** every fixed set of values of the form code ends with `Enum`. The namespaces and the cases
  are unchanged:

  | v3 | v4 |
  |:--|:--|
  | `actra\yuf\form\settings\InputTypeValue` | `InputTypeEnum` |
  | `actra\yuf\form\settings\AutoCompleteValue` | `AutoCompleteEnum` |
  | `actra\yuf\form\component\layout\RadioOptionsLayout` | `RadioOptionsLayoutEnum` |
  | `actra\yuf\form\component\layout\CheckboxOptionsLayout` | `CheckboxOptionsLayoutEnum` |

  ```php
  // Before
  use actra\yuf\form\settings\AutoCompleteValue;
  new TextField(name: 'given', label: $label, autoComplete: AutoCompleteValue::GIVEN_NAME);
  new RadioOptionsField(name: 'r', label: $label, formOptions: $options, initialValue: null,
      layout: RadioOptionsLayout::DEFINITION_LIST);

  // After
  use actra\yuf\form\settings\AutoCompleteEnum;
  new TextField(name: 'given', label: $label, autoComplete: AutoCompleteEnum::GIVEN_NAME);
  new RadioOptionsField(name: 'r', label: $label, formOptions: $options, initialValue: null,
      layout: RadioOptionsLayoutEnum::DEFINITION_LIST);
  ```
  New in v4: `PasswordPurposeEnum` and `InputShapeEnum`.
* **Form components, errors and renderers:**
    * ⚠️ **`addError(HtmlText)`:** `FormComponent::addError(string $errorMessage, bool $isEncodedForRendering)` and
      `addErrorAsHtmlTextObject(HtmlText)` are replaced by one method, `addError(HtmlText $errorMessage)`. The flag
      is gone: the `HtmlText` says whether the text is encoded.
      ```php
      // Before
      $field->addError(errorMessage: 'Name < 3', isEncodedForRendering: false);
      $field->addError(errorMessage: '<b>Name</b> is wrong', isEncodedForRendering: true);
      $field->addErrorAsHtmlTextObject(errorMessageObject: $htmlText);

      // After
      $field->addError(errorMessage: HtmlText::unencoded(textContent: 'Name < 3'));
      $field->addError(errorMessage: HtmlText::encoded(textContent: '<b>Name</b> is wrong'));
      $field->addError(errorMessage: $htmlText);
      ```
    * ⚠️ **`FormControl` cancel text:** the default text of the cancel link comes from `FormMessages::$cancel` (English
      "Cancel"; `FormMessages::german()` gives the v3 text "Abbrechen"). A `FormControl` in a form uses the messages of
      that form, also when added with `addChildComponent()`; without a form it uses the English default (pass
      `cancelLabel` there). An individual `cancelLabel` argument wins as before.
      `FormControl::$cancelLabel` is never `null` any more (it is computed from the individual label or the messages).
      ```php
      // Before: "Abbrechen" was hard-coded
      $form->addComponent(formComponent: new FormControl(name: 'save', submitLabel: $save, cancelLink: '/list'));

      // After: keep it with one argument of the form
      $form = new Form(name: 'edit', messages: FormMessages::german());
      ```
    * ⚠️ **`FormComponent::getHtmlTag()` returns `HtmlTag`** (was `?HtmlTag`), and `render()` no longer returns `''`
      for a missing tag: a component always has a tag. An override must return an `HtmlTag` too. New
      `FormRenderer::prepareHtmlTag(): HtmlTag` (`final`) prepares the renderer and returns its base tag (throws a
      `LogicException` if `prepare()` did not call `setHtmlTag()`); the form renderers use it instead of
      `prepare()` followed by `getHtmlTag()`. A custom renderer that renders a nested renderer does the same.
      `FormRenderer::getHtmlTag(): ?HtmlTag` and `prepare()` are unchanged.
    * ⚠️ **`FormRenderer::addFieldInfoToParentHtmlTag()`** adds nothing for a field without `fieldInfo` (v3 failed with a
      `TypeError`).
    * **Typed collections:** `FormInfo` takes `list<string>` for `dlClasses`, `dtClasses` and `ddClasses`;
      `FormCollection::$childComponents` is `array<int|string, FormComponent>` (numeric names are `int` keys in PHP);
      `Form::getAllFields()` returns `list<FormField>`. `ErrorCollection` is `final`, `listErrors()` returns
      `list<HtmlText>` and `getFirstError()` throws a `LogicException` for an empty collection (v3 failed with a `TypeError`).
      `Form`, `FormCollection`, `FormComponent`, `FormInfo`, `FormControl`, `FormSubHeadline` and all renderers stay
      non-final (extension points).
* **Form HTML:** the markup of all fields and layouts is unchanged (pinned by tests against the HTML of v3.3.x) with
  these deliberate exceptions, all listed above: texts that now come from `FormMessages` (English without
  `FormMessages::german()`, the `FormControl` cancel text included), canonical rendering of number values (`'+007'`
  renders `7`, `'7.50'` in a `FloatField` renders `7.5`), posted zip codes and IBANs are trimmed, an invalid phone
  number is HTML-encoded (security fix) and a `PasswordField` is never rendered back.

---

## [v3.3.2] – 2026-10-04

### 🎨 HTML & CSS (Frontend)

* **Form Rendering:**
    * 🩹 **Fixed (security):** `PhoneNumberField` rendered an invalid posted phone number back into the `value`
      attribute without HTML encoding when the field had an error, which allowed HTML injection (e.g. `"><b>x`).
      The value is now encoded like in every other field.

---

## [v3.3.1] – 2026-10-04

### 🎨 HTML & CSS (Frontend)

* **Form Rendering:**
    * 🩹 **Fixed (security):** `PasswordField` no longer renders the posted password back into the `value` attribute
      when a form is shown again (e.g. with validation errors), so it cannot end up in the page source or caches.
      The posted value is still available via `getValueAsString()`; the user has to type the password again.

---

## [v3.3.0] – 2026-10-04

### ⚙️ Backend & API

* **Form Field Values:**
    * Added typed value getters, so no casting of `getRawValue()` is needed anymore. Call them after a successful
      validation; they throw an `UnexpectedValueException` for values that cannot be converted.
        * `getValueAsString(): string` on `InputField` (all subclasses), `TextAreaField`, `RadioOptionsField`,
          `SelectOptionsField` and `ToggleField` (single selection; `null` becomes `''`).
        * `getValueAsInt(): ?int` and `getValueAsFloat(): ?float` on `AmountField` (so `NumericField`),
          `getValueAsInt(): ?int` on `HiddenField` (`null` for an empty value).
        * `getValues(): array` (`list<string>`) on `CheckboxOptionsField` (so `BooleanField`), `SelectOptionsField`,
          `ToggleField` and `TextAreaField`.
      ```php
      // Before
      $name = ScalarCast::toString($field->getRawValue());
      $quantity = (int)$quantityField->getRawValue();

      // After
      $name = $field->getValueAsString();
      $quantity = $quantityField->getValueAsInt(); // ?int, null if empty
      ```
    * `TextAreaField::getValues()` returns one trimmed entry per line (CRLF-safe, without empty lines). A subclass that
      parsed the lines itself in `validate()` can use it instead:
      ```php
      // Before
      $lines = array_filter(array_map('trim', preg_split('/\R/', ScalarCast::toString($field->getRawValue()))));

      // After
      $lines = $field->getValues();
      ```
    * `HiddenField` got an optional `valueIsInt` argument. Use it for IDs, e.g.
      `new HiddenField(name: 'id', value: $id, valueIsInt: true)`: manipulated input then becomes a validation error
      instead of an exception in `getValueAsInt()`.
    * Added `actra\yuf\form\AmountParser` (accepted number formats, used by the amount rule and the numeric getters).
    * 🩹 **Fixed:** `DateField::getValueAsDateTimeImmutable()` no longer throws a `TypeError` for an empty field
      (`null`), it returns `null`.
    * 🩹 **Fixed:** `PhoneNumberField` and `ZipCodeField` no longer throw a `TypeError` for array input (`name[]=x`).
      An array phone value is rejected with the normal validation error, an array country code is ignored (the
      current country code stays).
    * 🩹 **Fixed:** `ValidAmountRule` (`AmountField`, `NumericField`) accepted decimals in integer fields.
      ⚠️ **Attention:** input that was accepted before is now a validation error:
        * Integer fields (`valueIsFloat: false`, `NumericField`) reject `'1.5'`, `'1.0'`, `'1.'`, `'.5'` and `'1e3'`.
        * Float fields reject exponent notation (`'1e3'`, `'1.5E-3'`).
        * Values outside the `int` range (integer fields) or too large for a `float` are rejected.
    * `AmountField` and `NumericField` now store posted input trimmed (`' 12 '` becomes `'12'`, also in
      `getRawValue()`). Surrounding whitespace is still accepted.
    * ⚠️ **Possible conflicts:** project subclasses that already declare one of the new methods with another
      signature cause a fatal error. Rename them or adjust the signature.
        * Public: `getValueAsString(): string`, `getValueAsInt(): ?int`, `getValueAsFloat(): ?float`,
          `getValues(): array`.
        * Protected on `FormField`: `getValueAsStringOrFail()`, `getValueAsIntOrFail()`, `getValueAsFloatOrFail()`,
          `getValuesAsStringListOrFail()`.
    * **Outlook:** `getRawValue()` and the `mixed` value storage of `FormField` will be removed in v4. The new getters
      stay; use them in new code.

---

## [v3.2.2] – 2026-09-12

### ⚙️ Backend & API

* **Phone Number Formatting:**
    * 🩹 **Fixed:** Anchored regex evaluation in `PhoneMatcher` now uses the `A` (`PCRE_ANCHORED`) modifier, ensuring
      alternation patterns in metadata-leading digits are anchored strictly to the start of the string without false
      positives.

---

## [v3.2.1] – 2026-09-10

### 🎨 HTML & CSS (Frontend)

* **Form Rendering:**
    * 🩹 **Fixed:** Optional `SelectOptionsField` instances now render their empty option without a visible label by
      default.
    * The default empty option label for required `SelectOptionsField` instances changed from German to English
      (`-- Please select --`).

---

## [v3.2.0] – 2026-08-30

### ⚙️ Backend & API

* **Database Query Helpers:**
    * `DbQuery::addOrderPart()` got an optional `parameters` argument (before `ascending`): with parameters, the first
      argument is an SQL expression instead of a column name (e.g. to sort by `MATCH() AGAINST()`). Its values are bound
      between those of the `WHERE` part and the ones of the `LIMIT`.
      ```php
      $dbQuery->addOrderPart(
          column: 'MATCH(products.searchContent) AGAINST (? IN BOOLEAN MODE)',
          parameters: [$searchTerm],
          ascending: false
      );
      ```
      ⚠️ **Attention:** call `addOrderPart()` with named arguments, because `ascending` is no longer the second
      argument. A call like `addOrderPart($column, false)` now passes `false` to `parameters` and results in a
      `TypeError`.
    * `DbQuery::addOrderPart()` now accepts several columns separated by a comma and applies the sort direction to each.
    * Added `DbQuery::clearOrderParts()` to remove all sorting.
    * Parts added by `addJoinPart()` / `addWherePart()` are normalized to a single line (no more line
      breaks/indentation).
    * Generated queries are checked for parameter/placeholder match before execution, throwing a `LogicException`
      instead of a `PDOException`.
    * ⚠️ **Attention:** An order expression must not end with `ASC` or `DESC` (sort direction is added automatically).
      This now throws a `LogicException`.
* **Tables:**
    * User-chosen sorting now replaces the sorting of the given `DbQuery` instead of being appended to it (e.g. clicking
      a column header works even if pre-sorted by relevance).

---

## [v3.1.0] – 2026-08-30

### ⚙️ Backend & API

* **Database Query Helpers:**
    * Added `DbQuery::addJoinPart()` to append joins between `FROM` and `WHERE` parts.
    * `DbQuery` keeps join parts separately so result and count queries include identical joins. All join types
      (`LEFT JOIN`, `INNER JOIN`, etc.) are recognized as one clause.
    * 🩹 **Fixed:** Parameters are stored per query section. `addJoinPart()` / `addWherePart()` no longer shift
      parameters of the original query.
    * 🩹 **Fixed:** Conditions added with `addWherePart()` are wrapped in parentheses to prevent `OR` conditions from
      breaking other clauses.
    * 🔒 **Security:** `addOrderPart()` now strictly rejects columns containing characters other than letters, digits,
      `_`, `.` and backticks. Fully qualified columns are wrapped in backticks (e.g. `t.group` becomes
      `` `t`.`group` ``).
    * ⚠️ **Attention:** `DbQuery::createFromSqlQuery()` now throws a `LogicException` for queries containing `GROUP BY`,
      `HAVING`, `ORDER BY`, `LIMIT` or `UNION` (which produced wrong results in `getTotalAmount()`), unbalanced
      parentheses, or parameter mismatches.

---

## [v2.2.0] – 2026-07-04

### 🎨 HTML & CSS (Frontend)

* **Template Tags:**
    * `tst:if` now supports `compare="hasSnippet"` to check whether a snippet file exists in the configured directory.
    * Can be used together with `operator="eq"` and `against="snippet-file.html"` to conditionally render content.

---

## [v2.1.1] – 2026-06-14

### ⚙️ Backend & API

* **JSON Request Body Validation:**
    * `JsonRequestBody::getOptionalFloat()` and `JsonRequestBody::getRequiredFloat()` now accept integers and cast them
      to floats.

---

## [v2.1.0] – 2026-06-14

### ⚙️ Backend & API

* **JSON Request Body Validation:**
    * Added `JsonRequestBody::getRequiredFloat()` and `JsonRequestBody::getOptionalFloat()`.
    * Improved error messages by including the expected type.
    * Integer accessors now require actual JSON integers (numeric strings are no longer cast automatically).

---

## [v2.0.0] – 2026-06-13

### 🚨 Breaking Changes

* **Request Method Verification:** Code comparing the request method as a string must now compare against
  `RequestMethodEnum` cases.
    * *Before:* `if (HttpRequest::getRequestMethod() === 'POST')`
    * *After:* `if (HttpRequest::getRequestMethod() === RequestMethodEnum::POST)`
* **JSON Response Envelope:** Success and error envelopes changed structure:
    * *Success:* `{"success": true, "data": {}}`
    * *Error:* `{"success": false, "error": {"code": null, "message": "..."}}`

### ⚙️ Backend & API

* **REST/API Endpoint Support:**
    * Added `RequestMethodEnum` with `GET`, `POST`, `PUT`, `PATCH`, and `DELETE`.
    * `HttpRequest::getRequestMethod()` now returns `RequestMethodEnum` instead of a string.
    * Added `RequestBody` and `JsonRequestBody` for JSON body validation.
    * Added `BaseView::getJsonRequestBody()` and immediate response controls (`sendAndExit`).

---

## [v1.7.0] – 2026-05-25

### 🎨 HTML & CSS (Frontend)

* **ActionsColumn Styling:**
    * Default table cell (`<td>`) class changed from `action` to `td-action`.
    * Multiple links are now wrapped in a container with the default class `td-action-group` (customizable via
      `ActionsColumn::$tdActionGroupClass`).

### ⚙️ Backend & API

* **Table Column Enhancements:**
    * In `AbstractTableColumn`, `cellCssClasses` and `columnCssClasses` are now `private(set)`.
    * `tableIdentifier` is now a public property; `getTableIdentifier()` and `setTableIdentifier()` were removed.
    * `SmartTable::addColumn()` sets `tableIdentifier` directly.
    * Added `sortableColumnClass` constructor parameter (defaulting to `sort`).

---

## [v1.6.0] – 2026-05-20

### ⚙️ Backend & API

* **Form Enhancements:** `SelectOptionsField` supports custom data attributes via `addDataAttribute()`, rendered
  automatically by `SelectOptionsRenderer`.

---

## [v1.5.0] – 2026-05-18

### 🎨 HTML & CSS (Frontend)

* **Navigation Refinement:** `NavigationItem` property `activeCssClass` renamed to `activeSubToggleClass` and
  `inactiveCssClass` to `inactiveSubToggleClass`.
* **Logic Change:** `cssClass` in `HtmlDataObject` is only populated if the user has access to the item's children.

---

## [v1.4.0] – 2026-05-16

### ⚙️ Backend & API

* **HtmlDocument Enhancements:** Added `getActiveHtmlId()` and `listActiveHtmlIds()`.

---

## [v1.3.0] – 2026-05-15

### 🚨 Breaking Changes

* **HtmlDataObject:** Property `buttonClass` renamed to `cssClass`. Templates using this property must be updated.

### 🎨 HTML & CSS (Frontend)

* **Navigation Styling:** `NavigationItem` now supports configurable CSS classes via `activeCssClass` and
  `inactiveCssClass`.

---

## [v1.2.0] – 2026-05-12

### ⚙️ Backend & API

* **Navigation & Access Control:** Refactored `NavigationItem` and `NavigationItemCollection` to accept
  `AccessRightCollection` instead of `AuthUser`.

---

## [v1.0.7] – 2026-05-06

### 🎨 HTML & CSS (Frontend)

* **ActionsColumn Rendering:** Removed `<ul>` and `<li>` tags. Multiple action links are now separated by a newline
  (`PHP_EOL`).

---

## [v1.0.6] – 2026-05-06

### ⚙️ Backend & API

* **ActionsColumn Constants:** Added `ActionsColumn::EDIT` and `ActionsColumn::DELETE` constants.

---

## [v1.0.0] – 2026-04-25

### ⚙️ Backend & API

* **DbSettingsModel Defaults:** Constructor now defaults to `utf8mb4` charset, `de_CH` language, and
  `sqlSafeUpdates = true`.
* **Routing & Layout:** Introduced structured layout/routing with custom view directories and class prefixes.

---

## [v0.7.0] – 2026-03-15

### ⚙️ Backend & API

* **Core Autoloader:** Replaced internal autoloader with `actra/autoloader`.

---

## [v0.6.1] – 2026-03-11

### ⚙️ Backend & API

* **Database (FrameworkDB):** `PDO::ATTR_STRINGIFY_FETCHES` is now set to `false`. Database results now return native
  PHP types (`int`/`float`) instead of strings.
