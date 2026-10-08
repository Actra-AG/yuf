# Upgrade Guide

This document tracks relevant changes and upgrade instructions for developers.

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
