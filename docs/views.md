# Views and requests

## Views

By default, the view of a request is the class `<viewClassPrefix>\view\<viewGroup>\php\[<fileGroup>\]<fileTitle>`
(`ClassNameViewFactory`), created with `new $className(context: $context)`. Its class name is the file name (e.g.
`welcome`), so it is lowercase: an allowed exception from PascalCase, to be documented in the project.

To pass further dependencies (e.g. a typed project context) to these views, give the `Route` a
`ClassNameViewFactory` with a `create` closure. yuf still builds the class name and checks that the class exists and
extends `BaseView`; the closure only creates the view:

```php
viewFactory: new ClassNameViewFactory(
    create: fn(string $className, ViewContext $context): BaseView => new $className(
        context: $context,
        projectContext: $projectContext,
    ),
),
```

For views with PascalCase names, give the `Route` a `ViewMap`: it maps the file name to a closure. Only the view of the
current request is created; a file without a mapped view is rendered without view.

```php
new Route(
    path: '/',
    viewDirectory: $core->viewDirectory,
    viewGroup: 'frontend',
    viewFactory: new ViewMap()->add(
        fileTitle: 'index',
        create: fn(ViewContext $context): BaseView => new IndexView(context: $context),
    ),
);
```

Every view receives the `ViewContext` of the request and passes it to `BaseView::__construct()`:

| `$this->context->…` | Content                                                       |
|:--------------------|:--------------------------------------------------------------|
| `route`             | The `Route`; also the file group, file title and `PathVars`   |
| `content`           | `ContentHandler` (`getContentType()`)                         |
| `locale`            | `LocaleHandler`                                               |
| `templateEngine`    | `TemplateEngine` of the request (see [templates.md](templates.md)) |
| `httpRequest`       | `HttpRequest`                                                 |
| `session`           | `?Session`, `null` without sessions (see [session-and-login.md](session-and-login.md)) |
| `authSession`       | `AuthSession`                                                 |
| `formContext`       | `FormContext` for forms (see [forms.md](forms.md))            |

`BaseView::getHtmlDocument()` and `getJsonRequestBody()` give the HTML document and the JSON request body.

## The request

`HttpRequest` is an immutable snapshot of the request, created once by `Core` (`$core->httpRequest`,
`$this->context->httpRequest`). Nothing in yuf reads a superglobal afterwards; everything that needs the request gets it
as argument or constructor dependency.

```php
$httpRequest = $this->context->httpRequest;
$httpRequest->getMethod();               // RequestMethodEnum (GET, HEAD, POST, PUT, PATCH, DELETE, OPTIONS)
$httpRequest->getPath();                 // '/de/page.html', without the query string
$httpRequest->getUrl();                  // 'https://www.example.com/de/page.html?a=1'
$httpRequest->getProtocol();             // ProtocolEnum::HTTPS
$httpRequest->getRemoteAddress();        // client IP address
$httpRequest->getHeader(name: 'Accept'); // ?string, header names are case-insensitive
$httpRequest->getBearerToken();          // ?string
$httpRequest->getCookie(name: 'theme');  // ?string
$httpRequest->getBody();                 // raw body, e.g. for JSON
```

Input is never merged: `getQueryString()`, `getQueryInteger()`, `getQueryFloat()`, `getQueryArray()` and
`hasQueryValue()` read the query string, the `getPost…()` methods the posted form data. Strings are trimmed, a missing
value is `null`. Integers and floats must be complete numbers (`'12'`, `'-1.5'`, `'1e3'`): `'12abc'`, `''` and `'1.5'`
(as integer) give `null`. Uploads: `getFile()` for a field with one file, `getFiles()` for any field.

A view declares the source of each input parameter; `getInputString()`, `getInputInteger()`, `getInputFloat()` and
`getInputArray()` read from it and check required parameters:

```php
$inputParameters = new InputParameterCollection();
$inputParameters->add(inputParameter: new InputParameter(name: 'page', source: InputSourceEnum::QUERY, isRequired: false));
$inputParameters->add(inputParameter: new InputParameter(name: 'title', source: InputSourceEnum::POST, isRequired: true));
```

## Path variables

A file name like `subscription-42.html` is split at `-` into path variables (`0` → `subscription`, `1` → `42`); the
view allows them with `maxAllowedPathVars`. `getPathVar()` returns the untyped `?string`. The typed getters of
`BaseView` throw a `NotFoundException` (404) for a wrong URL instead of turning it into ID `0`:

```php
$id = $this->getRequiredPathVarAsInt(nr: 1);      // int, 404 if missing or not an integer
$slug = $this->getRequiredPathVarAsString(nr: 2); // trimmed string, 404 if missing or empty
$page = $this->getPathVarAsInt(nr: 3) ?? 1;       // ?int, null if missing or not an integer
```

Integers are strict: optional minus and digits only; values outside the integer range are not an integer.

## JSON endpoints

- `BaseView::getJsonRequestBody()` reads and validates a JSON request body; `JsonRequestBody` has typed accessors for
  required and optional strings, integers, floats and arrays.
- `BaseView::setSuccessResponseContent()` answers `{"success": true, "data": {}}`.
- `BaseView::setErrorResponseContent()` answers `{"success": false, "error": {"code": 0, "message": "…"}}` with an
  HTTP status code; optional additional data is in a top-level `data` property.

## Responses and caching

- Generated content (HTML, JSON, text) is sent with `Cache-Control: private, no-store`: it may contain personal data
  and CSRF tokens, so neither browsers nor proxies store it.
- Files (`HttpResponse::createResponseFromFilePath()`, `FileHandler`) have an ETag and `Last-Modified`; a request with
  the current version gets `304 Not Modified`. `maxAge:` sets `max-age` (0: the browser asks every time). Versioned
  assets without personal data (`/css/styles.min.css?v=20260922`) add `isPublic: true, isImmutable: true`.
- `sendAndExit()` closes a started session before the content is sent, so a download does not block other requests of
  the user. Write the session before.
