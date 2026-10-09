# Plan: move yuf to the remaining rules of the coding standard

yuf follows `actra/coding-standard` (v1.2.0). Code style and PHPStan are covered by
[docs/plans/coding-standard/plan.md](../coding-standard/plan.md). This plan covers the remaining rules that change the public
API:

- names (`naming.md`): acronyms written like words, no `Model` suffix for settings bundles, `Enum` suffix, constants in
  UPPER_SNAKE_CASE;
- dependencies through the constructor instead of static accessors (`php.md`, section 1);
- views that can receive constructor arguments and have PascalCase class names.

Consumers such as `actra/backend` extend yuf classes (`BaseView`, `AuthUser`, `Authenticator`, `FrameworkDB`,
`DbResultTable`, forms and fields), so they can only follow after the yuf release that changes a name or a dependency.
Each step below is released on its own, with `composer check` green.

The remaining differences to the coding standard after v4.23.0 are listed in [remaining.md](remaining.md).

## Decisions

- **No backwards compatibility for renames** (decided by the user, 2026-10-07): renamed classes, traits, methods,
  properties, arguments and constants are not kept as deprecated aliases. Every rename is a breaking change with an
  ⚠️ entry and a before/after example in `UPGRADE.md`. This deliberately differs from the recommendation of
  coding standard v1.2.0 (deprecated alias for one release) and from "prefer deprecating first"
  (`versioning.md`, section 4).
- Static accessors are removed in the release that replaces them; there are no deprecated wrappers either.
- The decision is recorded in this plan only, not as a deviation in `AGENTS.md` (decided by the user).
- `TableItemModel` becomes `TableItem`: matches `TableItemCollection` and `SmartTable::addDataItem()`; `TableRow`
  could be mistaken for `<tr>`.
- The view factory is registered per `Route` (`new Route(…, viewFactory: …)`), not globally in `Core`:
  `actra/backend` creates its routes itself, and each `BackendRoute` has its own `BackendMessages` and path, so each
  route gets a factory with its own dependencies, and the other routes of a project stay unaffected.
- From step 4, the default `ClassNameViewFactory` creates views with `new $className(context: $viewContext)`, so every
  view accepts `ViewContext $context`. There is only one kind of view, and `BaseView` needs no static fallback.

## 1. Inventory

"Deprecation possible" says whether the old API could be kept for one release. Because of the decision above, all
entries are released as breaking changes anyway; the column shows the effort that is saved.

### 1.1 Static state and static accessors

| Static state / accessor                                                                          | Used in yuf (files)                                                                                                                                               | Used by `actra/backend`                                                   | Plan                                                                          |
|:-------------------------------------------------------------------------------------------------|:------------------------------------------------------------------------------------------------------------------------------------------------------------------|:--------------------------------------------------------------------------|:------------------------------------------------------------------------------|
| `Route::$routesByPath` (duplicate path check)                                                    | `Route`                                                                                                                                                           | –                                                                         | step 2: check in `RouteCollection`                                            |
| `Route::getPhpClassName()` (reads `RequestHandler::get()`)                                       | `ContentHandler`                                                                                                                                                  | –                                                                         | step 3: moves into `ClassNameViewFactory`                                     |
| `ContentHandler::getViewClass()` (`new $phpClassName()`)                                         | `ContentHandler`                                                                                                                                                  | all 28 views rely on it                                                   | step 3: `ViewFactory`                                                         |
| `RequestHandler::get()`                                                                          | `BaseView`, `Route`, `LocaleHandler`, `ContentHandler`, `HtmlDocument`, `ExceptionHandler`                                                                       | `BackendView` (2×: `->route`, `->pathVars`)                               | step 4 (views), step 10 (rest)                                                |
| `ContentHandler::get()` / `isRegistered()`                                                       | `BaseView`, `ExceptionHandler`                                                                                                                                    | `BackendView` (1×)                                                        | step 4                                                                        |
| `HtmlDocument::get()`                                                                            | `ContentHandler`; `example/` view                                                                                                                                 | `BackendView::execute()` (1×)                                             | step 4                                                                        |
| `JsonRequestBody::get()`, `RequestBody::getData()`                                               | `BaseView`                                                                                                                                                        | –                                                                         | step 4                                                                        |
| `CspNonce::get()` (per request)                                                                  | `Core`, `HtmlDocument`, `HtmlSnippet`, `ExceptionHandler`                                                                                                        | –                                                                         | step 8                                                                        |
| `Logger::register()` / `get()`                                                                   | `Core`, `ExceptionHandler`                                                                                                                                        | –                                                                         | step 9                                                                       |
| `LocaleHandler::register()` / `get()` / `isRegistered()`                                         | `Core`, `Route`, `ExceptionHandler`, `LangTag` (compiled templates)                                                                                               | –                                                                         | step 10; `get()` stays for templates                                          |
| `Core::get()`                                                                                    | `Route`, `RequestHandler`, `LocaleHandler`, `MicrosoftAuthenticator`, `MicrosoftIdToken`, `HtmlDocument`, `HtmlSnippet`, `LogFile`, `SessionSettingsModel`, `ExceptionHandler`, `AbstractSessionHandler`, `IfTag`, `SnippetTag`; `tests/Double/CoreTestInstance` | –                                                                         | step 10 (core, html, session); stays for templates                            |
| `Core::config()`                                                                                 | – (env values of projects)                                                                                                                                        | –                                                                         | stays                                                                         |
| `ErrorHandler::register()`, `ExceptionHandler::register()`                                       | `Core`                                                                                                                                                            | –                                                                         | stays                                                                         |
| `HttpRequest::*` (static reads of the superglobals, cached in static properties)                | 14 files                                                                                                                                                          | `getRemoteAddress`, `getProtocol`, `getHost`, `getURI`, `getUserAgent`, `getBearer` | stays (only renames in step 6)                                                |
| `AbstractSessionHandler::register()` / `enabled()` / `getSessionHandler()` / `clearUserData()`   | `HttpResponse`, `RequestHandler`, `CsrfToken`, `AuthSession`, `MicrosoftAuthenticator`, `Authenticator`, `AbstractSessionHandler`                               | `getSessionHandler()->getID()` (3×)                                       | stays                                                                         |
| `AuthSession::*`, `CsrfToken::*`, `FormNameRegistry::*`                                          | auth, forms, tables                                                                                                                                               | `AuthSession::logOut/isLoggedIn/getAuthSessionID/logIn`                   | stays                                                                         |
| `AuthUser::$instance`, `Authenticator::$instance` (one instance per request)                     | `AuthUser`, `Authenticator`                                                                                                                                       | `MyAuthUser::get()`, `MyAuthenticator::get()` (own accessors)            | stays                                                                         |
| `FrameworkDB::$instances` / `getInstance()`, `DbSettingsModel::$instances`                       | `FrameworkDB`, `DbSettingsModel`                                                                                                                                  | `DB::get()` (own accessor, extends `FrameworkDB`)                         | stays                                                                         |
| `SmartTable`, `TableFilter`, `AbstractTableFilterField`, `SearchHelper`: `$instances`            | table, search                                                                                                                                                     | `SearchHelper::getInstance()` (1×)                                        | stays                                                                         |
| Caches: `PhoneMetaData`, `PhoneParser`, `AbstractCurlRequest`, `DbQueryLogList`, `LogFile`      | phone, api, db, common                                                                                                                                            | –                                                                         | stays                                                                         |

What stays static for now, and why:

- `ErrorHandler`, `ExceptionHandler::register()`: `set_error_handler()` and `set_exception_handler()` are global by
  nature; the static guard only prevents a second registration.
- `HttpRequest`: it is the boundary to the superglobals and is used in 14 files of yuf and by consumers. Replacing it by
  an instance (`HttpRequest::fromGlobals()`) passed to every caller is a separate plan once the views get their
  `ViewContext` (step 4 makes that possible).
- Session (`AbstractSessionHandler`, `AuthSession`, `CsrfToken`, `FormNameRegistry`): built on `$_SESSION`, which is
  global. Replacing it needs a session object passed through forms and tables (`CsrfTokenSource` already exists as an
  extension point); separate plan.
- `LocaleHandler::get()` and `Core::get()` in `LangTag`, `IfTag` and `SnippetTag`: compiled templates call static
  methods. The template refactoring is postponed.
- `FrameworkDB::getInstance()`: the connection pool per identifier. `actra/backend` makes its repositories instances
  with a `DB` dependency (its task 6); the pool stays the place that creates connections.
- `AuthUser` / `Authenticator` single-instance guards and the `$instances` registries of the tables: they only enforce
  unique identifiers; no consumer reads them. They go with the refactoring of their area.
- Caches of immutable data (phone metadata, curl handle, log files): no request state.

### 1.2 Acronyms written in capitals (public API)

| Name                                                                                                       | Kind                                     | Used by `actra/backend`                                         | Deprecation possible          | Step |
|:-----------------------------------------------------------------------------------------------------------|:-----------------------------------------|:----------------------------------------------------------------|:------------------------------|:-----|
| `AuthUser::$ID`                                                                                             | constructor argument + public property   | `ID:` in `MyAuthUser`, `->ID` read 5×                           | property yes (hook), argument no | 5    |
| `Authenticator::logAuthResult(?int $userID, string $sessionID, …)`                                         | abstract method arguments                | overridden in `MyAuthenticator`                                 | no (yuf calls it with named arguments, the override must rename at the same time) | 5    |
| `AuthSession::getAuthSessionID()`, `logIn(authSessionID:)`                                                 | static method, argument                  | 3× / 2×                                                         | method yes, argument no       | 5    |
| `AbstractSessionHandler::getID()`, `regenerateID()`                                                        | methods                                  | `getID()` 3×                                                    | yes                           | 5    |
| `MicrosoftAuthenticator::redirectToMicrosoftLogin(tenantID:, clientID:)`, `microsoftIdTokenLogin(tenantID:, clientID:)`, `new MicrosoftIdToken(tenantID:, clientID:)` | arguments                                | –                                                               | no                            | 5    |
| `HttpRequest::getURI()`, `getURL()`, `isSSL()`                                                             | static methods                           | `getURI()` 2×                                                   | yes                           | 6    |
| `ErrorHandler::handlePHPError()`                                                                            | public method (callback)                 | –                                                               | yes                           | 6    |
| `CSVFile`, `SMTPMailer`, `FrameworkDB`, `SimpleXMLExtended`                                                | classes                                  | `CSVFile`, `SMTPMailer`; `DB extends FrameworkDB`               | yes (`class_alias`)           | 7    |
| `SimpleXMLExtended::addXML()`, `addCData()`                                                                 | methods                                  | –                                                               | yes                           | 7    |
| `AbstractMail::addCC()`, `addBCC()`                                                                         | methods                                  | check                                                           | yes                           | 7    |
| `MailerFunctions::stripTrailingWSP()`, `mb_pathinfo()`                                                     | static methods                           | –                                                               | yes                           | 7    |
| `StringUtils::utf8_to_punycode_email()`, `punycode_to_utf8_email()`                                        | static methods (snake_case)              | –                                                               | yes                           | 7    |
| `SearchHelper::createSQLFilters()`, `createSQLSearch()`                                                     | methods                                  | –                                                               | yes                           | 7    |
| `CDataSectionNode`, `ForTag` (`$forUID`, `$forDOM`, `str_replace_node()`)                                  | template class, internals                | –                                                               | –                             | template plan |

Not API, renamed in the step of their area: private methods and variables (`readSessionID()`, `$requestedSessionID`,
`setCSSActive()`, `getMailMIME()`, `encodeQP()`, `base64EncodeWrapMB()`, `sendCommandEHLO()`, `sendCommandSTARTTLS()`,
`$errorsHTML`, `$linkHTML`, `$tagNParts`) and the private constant `AuthSession::authSessionIdIndicator`. The session
key `'authSessionID'` becomes `'authSessionId'` in step 5 (logged-in users have to log in again).

### 1.3 Other names that break `naming.md`

| Name                                                                                           | Rule                                       | Used by `actra/backend`                                    | Deprecation possible           | Step |
|:-----------------------------------------------------------------------------------------------|:-------------------------------------------|:-----------------------------------------------------------|:-------------------------------|:-----|
| `DbSettingsModel` (+ arguments `dbSettingsModel:` of `FrameworkDB`)                           | settings bundle without `Model`            | `ActraBackend::init()`, `DB`, `DBTest`, README             | class yes, argument no         | 1    |
| `SessionSettingsModel` (+ `sessionSettingsModel:` of `FileSessionHandler`, `AbstractSessionHandler`) | settings bundle                      | –                                                          | class yes, argument no         | 1    |
| `CspPolicySettingsModel` (+ `cspPolicySettingsModel:` of `Core::prepareHttpResponse()`, `HttpResponse::createHtmlResponse()`, property `Core::$cspPolicySettingsModel`) | settings bundle | –                                       | class/property yes, argument no | 1    |
| `TableItemModel` (+ `tableItemModel:` of `SmartTable::addDataItem()`, `AbstractTableColumn::renderCell()`, `renderCellValue()`, `TableItemCollection::add()`) | value object without type suffix | closure type in 4 tables, `->data` in `AbstractTable` | class yes, argument no | 1    |
| trait `SelectOptionsSettings`                                                                  | not a settings bundle; trait names the ability | –                                                     | yes                            | 1    |
| enums `AuthMethod`, `AuthResult`                                                               | `Enum` suffix                              | `AuthResult::*` cases, `AuthMethod::OTP`                   | yes (`class_alias`)            | 5    |
| enum `HttpStatusCode`                                                                          | `Enum` suffix                              | check                                                      | yes                            | 6    |
| `SmartTable::totalAmount`, `table`, `tableHeader`, `tableBody`, `cells`, `totalAmountMessagePlaceholder`, `amount`; `DbResultTable::sessionDataType`, `filter`, `pagination` (protected) | UPPER_SNAKE_CASE constants | check (subclasses of `DbResultTable`)                      | yes                            | 7    |
| View classes named like their file (`app\view\frontend\php\index`)                             | class names PascalCase                     | 28 views (`login`, `userMod`, …)                           | –                              | 3    |

## 2. Steps

Version numbers assume that nothing else is released in between. Every step: characterization tests first (when
behaviour changes), hand-written doubles in `tests/Double/`, `composer check` green, `example/` checked in the browser
when routing, views or HTML output change, `UPGRADE.md` section in the releasing commit.

### Step 1 – v4.12.0: settings and value object names (coding standard v1.2.0)

Changes:

- `composer.json`: `actra/coding-standard` `^1.2.0` (already committed before v4.10.1).
- `DbSettingsModel` → `DbSettings`; `FrameworkDB::__construct(dbSettings:)`, `FrameworkDB::getInstance(dbSettings:)`.
- `SessionSettingsModel` → `SessionSettings`; `FileSessionHandler::__construct(sessionSettings:)`,
  `AbstractSessionHandler::__construct(sessionSettings:)`.
- `CspPolicySettingsModel` → `CspPolicySettings`; `Core::prepareHttpResponse(cspPolicySettings:)`,
  `Core::$cspPolicySettings`, `HttpResponse::createHtmlResponse(cspPolicySettings:)`.
- `TableItemModel` → `TableItem`; arguments `tableItem:`; `TableItemModelTest` → `TableItemTest`.
- Trait `SelectOptionsSettings` → `HasSelectOptionsPresentation`, marked `@internal`; the private
  `initializeSelectOptionsSettings()` → `initializeSelectOptionsPresentation()`.
- No behaviour change, so no new tests; PHPStan proves the renames, the existing tests are adapted.
- `example/`: no code change (uses none of these names); check it in the browser.

`UPGRADE.md` (draft):

````markdown
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
````

`actra/backend` afterwards: `DbSettingsModel` → `DbSettings` in `ActraBackend::init()` (argument and its own public
property `$dbSettingsModel`, a breaking change of backend), `DB::useConnection()`, `DB::get()`, `DBTest`, README;
`TableItemModel` → `TableItem` in the callbacks of `VisitTable`, `UserTable`, `TokenTable`, `NotificationTable` and in
`AbstractTable`.

### Step 2 – v4.13.0: route registry without static state

Changes:

- `Route::$routesByPath` is removed. `RouteCollection::addRoute()` throws the `LogicException` for a duplicate path
  (also via its constructor).
- Characterization test first: `RouteCollectionTest` – a duplicate path throws; two routes with different paths are
  kept in order. Today a second `new Route(path: '/')` in the same test process throws, which the test documents
  before the change and which no longer happens after it.
- Not breaking (no code change needed); `UPGRADE.md` entry without ⚠️: "The duplicate path check moves from the `Route`
  constructor to `RouteCollection::addRoute()`. Routes with the same path in different collections no longer throw."
- `example/`: no change.

`actra/backend` afterwards: nothing.

### Step 3 – v4.14.0: view factory and PascalCase view classes

Design (no container, no new static state):

```php
namespace actra\yuf\core;

/** Creates the view for a request of a route. Projects implement it to pass dependencies to their views. */
interface ViewFactory
{
    /** `null` if the route has no view for this file: the content file is rendered without view, as before. */
    public function createView(ViewContext $context): ?BaseView;
}

/** What a view factory needs to choose and create the view of the current request. */
final readonly class ViewContext
{
    public function __construct(
        public Route $route,
        public ?string $fileGroup,
        public string $fileTitle,
    ) {}
}

/** Default: the class name is built from the route and the file name, the view has no constructor arguments. */
final readonly class ClassNameViewFactory implements ViewFactory
{
    public function createClassName(ViewContext $context): string { /* former Route::getPhpClassName() */ }
    public function createView(ViewContext $context): ?BaseView { /* class_exists, is_subclass_of, new $className() */ }
}

/** Explicit mapping from file title to a closure that creates the view. */
final class ViewMap implements ViewFactory
{
    /** @var array<string, Closure(ViewContext): BaseView> */
    private array $creators = [];

    /** @param Closure(ViewContext): BaseView $create */
    public function add(string $fileTitle, Closure $create, ?string $fileGroup = null): ViewMap { … }

    public function createView(ViewContext $context): ?BaseView { … }
}
```

- `Route::__construct()` gets the new last argument `?ViewFactory $viewFactory = null`;
  `ContentHandler` uses `$route->viewFactory ?? new ClassNameViewFactory()`.
- `Route::getPhpClassName()` is removed (⚠️); `ClassNameViewFactory::createClassName()` replaces it.
- The view class no longer has to equal the file name: the content file (`html/login.html`), the language files and
  `RequestHandler::$fileTitle` still use the file name, only the PHP class is free (`LoginView`, any namespace).
- Closures are lazy: only the view of the current request is created.
- `Route::$viewCallback` is not the starting point: it returns the content string instead of a view, is one closure for
  all files of a route, and skips the language files and the check of `maxAllowedPathVars`. It stays unchanged.
- Tests: characterization of the class name first (`ClassNameViewFactoryTest`: prefix, `view`, view group, `php`,
  optional file group, file title – the cases of today's `getPhpClassName()`), `ViewMapTest` (known file title, file
  group, unknown file title → `null`, the closure gets the context). Doubles in `tests/Double/core/`: a view that does
  not call the `BaseView` constructor (it still reads `RequestHandler::get()` until step 4), with `@phpstan-ignore` and
  reason, like the test session handler.
- `example/`: the view becomes `app\view\frontend\IndexView`, registered with a `ViewMap` on the route; the comment
  explains that without `viewFactory` the class `app\view\frontend\php\index` is used. Check it in the browser.

How `actra/backend` registers its views (in `ActraBackend::createRoute()`):

```php
$messages = $backendRoute->messages;
$paths = new BackendPaths(basePath: $backendRoute->path);
$userRepository = new DbAuthUserRepository(db: $db);

$views = new ViewMap()
    ->add(fileTitle: 'login', create: fn(ViewContext $context): BaseView => new LoginView(
        messages: $messages,
        paths: $paths,
    ))
    ->add(fileTitle: 'userMod', create: fn(ViewContext $context): BaseView => new UserModView(
        messages: $messages,
        paths: $paths,
        userRepository: $userRepository,
    ));
// … the other 26 views

new Route(
    path: $backendRoute->path,
    viewDirectory: __DIR__ . '/view/',
    viewGroup: ActraBackend::viewGroup,
    defaultFileName: 'login.html',
    // … as today
    viewFactory: $views,
);
```

`UPGRADE.md` (draft):

````markdown
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

### ⚠️ `Route::getPhpClassName()` is removed

Before:

```php
$className = $route->getPhpClassName();
```

After:

```php
$className = new ClassNameViewFactory()->createClassName(context: $viewContext);
```
````

`actra/backend` afterwards (its task 7, part 1): rename the 28 views to PascalCase (`login` → `LoginView`, …), register
them with a `ViewMap` per `BackendRoute`, inject `BackendMessages`, the route path (e.g. a `BackendPaths` object that
replaces the static `getPath()` of the views) and the repositories, and remove `ActraBackend::get()`, `messages()` and
`path()` where the views used them. Release as a breaking minor of backend.

### Step 4 – v4.15.0: views get request data through the `ViewContext`

Changes (refined in the task; `RequestHandler` and the former `ContentHandler` could not be constructed in tests):

- `ContentHandler` gets a plain public constructor `__construct(ContentType $contentType)` (no static registration, no
  request processing) and `getHtmlDocument()` (lazy, one `HtmlDocument` per `ContentHandler`, also used for the final
  rendering). `ContentHandler::register()` keeps today's behaviour (static registration, then the request pipeline in a
  private method); `get()` and `isRegistered()` stay for `ExceptionHandler` (step 10).
- `ViewContext` becomes a `final class` (no longer `readonly`): `Route $route`, `?string $fileGroup`,
  `string $fileTitle`, `PathVars $pathVars`, `ContentHandler $content`, plus `getHtmlDocument()` and the lazily cached
  `getJsonRequestBody()` (`JsonRequestBody::fromString(json: RequestBody::getData())`). No `RequestHandler` in it.
- `BaseView::__construct()` gets the first argument `ViewContext $context` (⚠️ every view, stored as
  `protected readonly ViewContext $context`) and no longer calls `RequestHandler::get()`, `ContentHandler::get()`,
  `JsonRequestBody::get()`. New protected `BaseView::getHtmlDocument()`.
- `ClassNameViewFactory` creates views with `new $className(context: $context)`.
- `HtmlDocument::get()` and `JsonRequestBody::get()` are removed (⚠️); `HtmlDocument` gets a public constructor (its
  internals still read `RequestHandler::get()`, `Core::get()`, `CspNonce::get()` until step 10), `JsonRequestBody` gets
  `fromString()`. `RequestBody::getData()` stays: it is the boundary that reads `php://input`, like `HttpRequest`.
  `ContentHandler::get()` and `RequestHandler::get()` stay for the rest of yuf (step 10).
- `HttpRequest` stays static (see 1.1).
- Tests: `JsonRequestBodyTest` (`fromString()`), `ContentHandlerTest`, `BaseViewTest` (view group, IP whitelist, access
  rights, path vars, content, error/success responses, missing required parameter), doubles in `tests/Double/`
  (`ViewContextFactory`, `ConfigurableTestView`, `TestAuthUser`).
- `example/`: `IndexView` gets `ViewContext $context`, uses `$this->getHtmlDocument()` instead of `HtmlDocument::get()`.

`UPGRADE.md` (draft):

````markdown
### ⚠️ Views get a `ViewContext`

Before:

```php
final class index extends BaseView
{
    public function __construct()
    {
        parent::__construct(requiredViewGroupName: 'frontend', …);
    }

    public function execute(): void
    {
        HtmlDocument::get()->replacements->addEncodedText(identifier: 'title', content: 'Hello');
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

    public function execute(): void
    {
        $this->getHtmlDocument()->replacements->addEncodedText(identifier: 'title', content: 'Hello');
    }
}
```

Views of a `ViewMap` get the context as closure argument:
`create: fn(ViewContext $context): BaseView => new IndexView(context: $context, …)`.

`HtmlDocument::get()` and `JsonRequestBody::get()` are removed: use `BaseView::getHtmlDocument()` and
`BaseView::getJsonRequestBody()` (`JsonRequestBody::fromString()` for a JSON string).
````

`actra/backend` afterwards (task 7, part 2): `BackendView` and the 28 views accept and pass `ViewContext`; replace
`RequestHandler::get()` (2×), `ContentHandler::get()` and `HtmlDocument::get()` in `BackendView`.

### Step 5 – v4.16.0: auth and session names

Changes (all ⚠️):

- `AuthUser::$ID` → `$id` (constructor argument and property).
- `Authenticator::logAuthResult(?int $userId, string $sessionId, string $ip, …)`.
- `AuthSession::getAuthSessionId()`, `AuthSession::logIn(authSessionId:)`; the session key `'authSessionID'` becomes
  `'authSessionId'`, and a logged-in session without it is logged out.
- `AbstractSessionHandler::getId()`, `regenerateId()`.
- `MicrosoftAuthenticator` and `MicrosoftIdToken`: `tenantId:`, `clientId:`.
- Enums `AuthMethod` → `AuthMethodEnum`, `AuthResult` → `AuthResultEnum` (cases unchanged).
- Private names of the area (`readSessionId()`, `$requestedSessionId`, `authSessionIdIndicator` →
  `AUTH_SESSION_ID_INDICATOR`).
- Tests: existing `AuthSessionTest`, `AbstractSessionHandlerTest`, `AuthWebToken`/`MicrosoftIdToken` tests adapted; a
  characterization test of the stored session data first, then a test that an old session is logged out.
- `UPGRADE.md`: a table of the renames plus a before/after example for the `logAuthResult()` override and the
  `AuthUser` constructor (an override with the old argument names fails, because yuf calls it with named arguments).
- `example/`: no change.

`actra/backend` afterwards (task 5, inherited names): `MyAuthUser` (`id:`, `->id`), `MyAuthenticator::logAuthResult()`,
`getAuthSessionId()`, `logIn(authSessionId:)`, `getId()` of the session handler, `AuthResultEnum`, `AuthMethodEnum`;
then its own `$userID`, `$sessionID`, `$groupID`, … (its task 5).

### Step 6 – v4.17.0: request, response and error names

Changes (all ⚠️): `HttpRequest::getUri()`, `getUrl()`, `isSsl()`; `ErrorHandler::handlePhpError()`; enum
`HttpStatusCode` → `HttpStatusCodeEnum` (used in many signatures: `BaseView::setErrorResponseContent(httpStatusCode:)`,
`HttpResponse`, `ContentHandler::$httpStatusCode`). Tests adapted; `example/` checked. `actra/backend` afterwards:
`getURI()` (2×) and `HttpStatusCode`, if used.

### Step 7 – v4.18.0: remaining class, method and constant names

Changes (all ⚠️): `CSVFile` → `CsvFile`, `SMTPMailer` → `SmtpMailer`, `FrameworkDB` → `FrameworkDb`,
`SimpleXMLExtended` → `SimpleXmlExtended` (`addXml()`, `addCdata()`); `AbstractMail::addCc()`, `addBcc()`;
`MailerFunctions::stripTrailingWsp()`, `mbPathinfo()`; `StringUtils::utf8ToPunycodeEmail()`,
`punycodeToUtf8Email()`; `SearchHelper::createSqlFilters()`, `createSqlSearch()`; constants of `SmartTable` and
`DbResultTable` in UPPER_SNAKE_CASE (`SmartTable::TOTAL_AMOUNT`, …; the placeholder values stay). Private names of
these classes. Split into two releases (mailer / table and common) if the diff gets too large. `actra/backend`
afterwards: `CsvFile`, `SmtpMailer`, `DB extends FrameworkDb`, table constants if used.

### Step 8 – v4.19.0: CSP nonce per request as object

- `CspNonce` becomes a `final readonly class` with `__construct(public string $value)` (empty value throws
  `InvalidArgumentException`) and `CspNonce::create()` (random). `CspNonce::get()` and the static property are removed
  (⚠️).
- `Core::prepareHttpResponse()` creates one `CspNonce` per request and passes it to `ExceptionHandler::register(…,
  cspNonce:)` (stored in the protected property `$cspNonce` of the handler), `ContentHandler::register(cspNonce:)`
  (constructor `ContentHandler(contentType, cspNonce)`, public readonly `$cspNonce`; `getHtmlDocument()` creates
  `new HtmlDocument(cspNonce:)`) and `HttpResponse::createHtmlResponse(nonce: $cspNonce->value)` (string, unchanged).
  Views get it through `$this->context->content->cspNonce`.
- `HtmlSnippet` gets an optional `?CspNonce $cspNonce` (also in `createForCurrentView()`) and adds the replacement
  `cspNonce` only when one is given and the replacement is not set yet (⚠️).
- Tests: `CspNonceTest` without reflection; `ContentHandlerTest`; new `HtmlSnippetTest` (with fixtures in
  `tests/Fixture/`); `HttpResponseSecurityHeadersTest` checks the nonce in `script-src` and `style-src`.

### Step 9 – v4.20.0: logger without static accessor

- Projects create their `ExceptionHandler` subclass before `Core` has created the logger and the nonce, so the request
  dependencies are handed over in `ExceptionHandler::register(individualExceptionHandler:, context:)`, bundled in the
  new `final readonly class ExceptionHandlerContext` (`$logger`, `$cspNonce`, `$cspPolicySettings`, `$isDebug`).
  Subclasses read it through `protected getContext()` (throws `LogicException` before `register()`).
- `Logger::register()` / `get()` are removed (⚠️); `ExceptionHandler::$cspNonce` is removed (⚠️);
  `Core::prepareHttpResponse()` still accepts `?Logger $logger` and builds the context.
- Tests: `ExceptionHandlerTest` with a hand-written `RecordingLogger` double (`tests/Double/core/`).
- The other `Core::get()` / `RequestHandler::get()` uses in `ExceptionHandler::getHtmlContent()` stay for step 10.

### Step 10 – locale and `Core::get()` inside yuf

Smaller releases, each for one area:

- **10.1 (v4.21.0) locale:** `LocaleHandler` gets the language and the available languages through its constructor (no
  `RequestHandler::get()` / `Core::get()`); `LocaleHandler::register()` takes the instance; `Route::loadLocalizedText()`
  gets the `LocaleHandler`; `ViewContext` gets `$locale`.
- **10.2 (v4.22.0) directories and settings:** what needs a directory of `Core` gets it explicitly (projects have the
  `Core` instance at hand: `$core->viewDirectory`, `$core->cacheDirectory`, `$core->logDirectory`). `Route` requires
  `viewDirectory` (second parameter, no `'{default}'`). `SessionSettings::$savePath` is `?string` (`null` = default of
  the handler); `FileSessionHandler` gets `defaultSavePath`; `Core::prepareHttpResponse()` creates the default handler
  itself when `individualSessionHandler` is `null` (`false` = no session). `AbstractSessionHandler::setPreferredLanguage()`
  does not check the available languages (`RequestHandler` does). `MicrosoftAuthenticator` gets `logDirectory` and
  `cacheDirectory` through its constructor and passes the cache directory to `MicrosoftIdToken`. `Pagination` and
  `TableFilter` find their snippets with `__DIR__`.
- **10.3 request data (v4.23.0):** no static `RequestHandler` / `ContentHandler` anymore. `RequestHandler` has two
  phases: the constructor `(routeCollection, availableLanguages, allowedDomains)` sets what cannot throw (first
  available language, path parts, file name, default routes), `resolveRoute()` does the rest in today's order (domain,
  `//`, route, language, session, file name parts; twice = `LogicException`), so the 404 page keeps language and
  language root. `ContentHandler::processRequest(requestHandler, localeHandler, core)` replaces `register()`;
  `getHtmlDocument()` throws before it. `HtmlDocument(requestHandler, cspNonce, core)`. `ExceptionHandlerContext`
  gets `core`; `ExceptionHandler::register()` returns the instance, `setRequestHandler()` / `setContentHandler()` give
  it the request data (fallbacks `en`, `/`, no file name before). `Core::prepareHttpResponse()` orchestrates.
  `HtmlSnippet::createForCurrentView()` gets the `Route` (it read `RequestHandler::get()`).
- **Stays:** `Core::get()` and `LocaleHandler::get()` for the compiled templates (`IfTag`, `SnippetTag`, `LangTag`)
  until the template refactoring; `tests/Double/CoreTestInstance` stays as long as `Core::get()` exists.
  Also `HtmlSnippet::render()` and `HtmlDocument` (they create the `TemplateEngine` with `Core::get()->cacheDirectory` /
  `baseDirectory`: template infrastructure; `HtmlDocument` gets the `Core` explicitly since 10.3).

### Later (separate plans)

- `LogFile` (static facade `LogFile::info()/debug()/error()` with a static registry of open files, reads
  `Core::get()->logDirectory`): replacing it needs a logger instance.
- `HttpRequest` as an instance (`HttpRequest::fromGlobals()`) passed through `ViewContext`.
- Session object instead of `AbstractSessionHandler::getSessionHandler()`, `AuthSession`, `CsrfToken`,
  `FormNameRegistry`.
- Templates (`src/template/`): `LangTag`, `IfTag`, `SnippetTag`, `CDataSectionNode`, `ForTag` names.

## 3. Follow-up tasks for `actra/backend`

| yuf release        | `actra/backend` task                                                                                                  |
|:-------------------|:----------------------------------------------------------------------------------------------------------------------|
| v4.12.0 (step 1)   | `DbSettings`, `TableItem`; require `actra/yuf ^4.12`                                                                  |
| v4.13.0 (step 2)   | none                                                                                                                  |
| v4.14.0 (step 3)   | task 7 part 1: PascalCase views, `ViewMap` per `BackendRoute`, inject `BackendMessages`, paths, repositories; remove `ActraBackend::get()`, `messages()`, `path()` from views |
| v4.15.0 (step 4)   | task 7 part 2: `ViewContext` in `BackendView` and the views; no `RequestHandler::get()`, `ContentHandler::get()`, `HtmlDocument::get()` |
| v4.16.0 (step 5)   | task 5 inherited names: `id`, `userId`, `sessionId`, `authSessionId`, `getId()`, `AuthResultEnum`, `AuthMethodEnum`  |
| v4.17.0 (step 6)   | `HttpRequest::getUri()`, `HttpStatusCodeEnum`                                                                         |
| v4.18.0 (step 7)   | `CsvFile`, `SmtpMailer`, `FrameworkDb`                                                                                |
| v4.19.0+ (8–10)    | only if backend uses the removed accessors (today: none)                                                              |

## Handover notes

### Step 1 (v4.12.0) – done

- `DbSettings`, `SessionSettings`, `CspPolicySettings`, `TableItem` and the trait `HasSelectOptionsPresentation`
  (`@internal`); named arguments and `Core::$cspPolicySettings` renamed, no aliases. Pure renames, no behaviour change.
- `TableItemModelTest` → `TableItemTest`; README and `docs/plans/form-v4/plan.md` updated; baseline unchanged (767 entries,
  6 messages/paths renamed). `example/` needed no change.

### Step 2 (v4.13.0) – done

- `Route::$routesByPath` removed; `RouteCollection::addRoute()` (also via its constructor) throws the `LogicException`
  for a duplicate path within the collection.
- `RouteCollectionTest` added (characterization first, then the new behaviour); `UPGRADE.md` entry without ⚠️.
- Baseline and `example/` unchanged.

### Step 3 (v4.14.0) – done

- `ViewFactory`, `ViewContext`, `ClassNameViewFactory` (`createClassName()` = former `Route::getPhpClassName()`),
  `ViewMap`; `Route::$viewFactory` (last argument), `ContentHandler` creates the view through it. `getPhpClassName()`
  and `ContentHandler::getViewClass()` removed; a class not extending `BaseView` now throws a `LogicException`.
- Class names could not be characterized against `Route::getPhpClassName()` (reads `RequestHandler::get()`);
  `ClassNameViewFactoryTest` takes the expected names literally from the old algorithm. `ViewMapTest` added.
- Doubles: `tests/Double/core/TestView.php` and `tests/Double/view/frontend/php/...` (view without parent constructor,
  `@phpstan-ignore constructor.missingParentCall`; lowercase class names follow the file name convention).
- `example/`: `app\view\frontend\IndexView` registered with a `ViewMap` (checked: `/` and `/index.html` 200, `nope.html`
  404). README section "Views" and `UPGRADE.md` added; baseline unchanged (767).

### Step 4 (v4.15.0) – done

- `ViewContext` (final class: route, file group/title, `PathVars`, `ContentHandler`, `getHtmlDocument()`, cached
  `getJsonRequestBody()`); `BaseView` takes it as first argument and uses it for route, path vars, content type and
  response content; `ClassNameViewFactory` passes it. `ContentHandler` has a public constructor and `getHtmlDocument()`;
  `register()` runs the pipeline as before. `HtmlDocument::get()` and `JsonRequestBody::get()` removed.
- Deviation from the draft: no `RequestHandler` in the context and `RequestBody::getData()` kept (it is the I/O
  boundary). `PathVars::get()` equals `RequestHandler::getPathVar()` (trimmed value or `null`).
- `JsonRequestBodyTest` is written against `fromString()` (the former `get()` could not be fed in tests);
  `BaseViewTest` and `ContentHandlerTest` added; step 3 doubles now call the parent constructor.
- Not covered by tests: `ContentHandler::register()` and `getHtmlDocument()` (`HtmlDocument` reads
  `RequestHandler::get()`), `BaseView::getHtmlDocument()` / `getJsonRequestBody()`, present required input parameters
  (`HttpRequest` caches `$_GET`/`$_POST` statically). `example/` checked in the browser (`/`, `/index.html` 200,
  `nope.html` 404).

### Step 5 (v4.16.0) – done

- `AuthMethodEnum`, `AuthResultEnum`, `AuthUser::$id`, `logAuthResult(userId:, sessionId:)`, `getAuthSessionId()`,
  `logIn(authSessionId:)`, `getId()`, `regenerateId()`, `tenantId:`/`clientId:`; private constants of `AuthSession` in
  UPPER_SNAKE_CASE. The session key is renamed to `'authSessionId'` (decided by the user); a logged-in session without
  it (stored by an older version) is logged out by `AuthSession::isLoggedIn()` instead of failing in
  `getAuthSessionId()`.
- Characterization test of the session key first (`AuthSessionTest`); new `AuthenticatorTest` with the double
  `RecordingAuthenticator` checks the named-argument call of `logAuthResult()`. Like `AuthSessionTest`, it resets the
  `Authenticator` and session handler singletons with reflection, until the session plan removes them.
- Baseline unchanged (766 entries, one message renamed). README and `example/` needed no change.

### Step 6 (v4.17.0) – done

- `HttpRequest::getUri()`, `getUrl()`, `isSsl()`, `ErrorHandler::handlePhpError()` (also the callable of
  `set_error_handler()`), enum `HttpStatusCodeEnum` (file renamed; used in `BaseView`, `HttpResponse`, `ContentHandler`,
  `CurlResponse`, `NotFoundException`, `UnauthorizedException`, `ExceptionHandler`).
- The scan of `src/core/`, `src/request/`, `src/response/`, `src/exception/` and `Core.php` found no further acronym
  names (`MimeType` string values and the `SimpleXMLExtended` class are left to step 7).
- Pure renames, tests adapted. Baseline unchanged (766 entries); `example/` checked: `/` 200, `nope.html` 404, HTTP
  redirects to HTTPS.

### Step 7 (v4.18.0) – done

- Classes `CsvFile`, `SmtpMailer`, `FrameworkDb`, `SimpleXmlExtended` (files renamed); methods `addXml()`, `addCdata()`,
  `addCc()`, `addBcc()`, `stripTrailingWsp()`, `mbPathinfo()`, `utf8ToPunycodeEmail()`, `punycodeToUtf8Email()`,
  `createSqlFilters()`, `createSqlSearch()`; constants of `SmartTable` and `DbResultTable` in UPPER_SNAKE_CASE (values
  unchanged); the private `SESSION_DATA_TYPE` of `TableFilter` and `AbstractTableFilterField` too.
- Beyond the list: private `getMailMime()`, `encodeQp()`, `base64EncodeWrapMb()`, `sendCommandEhlo()`,
  `sendCommandStartTls()`, `setCssActive()`, `$errorsHtml`; public named parameters `linkHtml`, `includeNull`,
  `queryText`, `qpMode`; snake_case variables in `src/common/` and `src/mailer/`.
- Pure renames, tests adapted; baseline unchanged (766 entries, messages renamed). Left: `src/template/`, `src/phone/`.
- The four class files are case-only renames: on a case-insensitive file system, git keeps the old file names unless
  they are renamed with `git mv` (otherwise the autoloader does not find the classes on Linux).

### Step 8 (v4.19.0) – done

- `CspNonce` is a `final readonly class` (`create()`, `$value`); `Core` creates one per request and passes it to
  `ExceptionHandler::register(cspNonce:)`, `ContentHandler::register(cspNonce:)` (constructor and `HtmlDocument` take
  it) and `HttpResponse::createHtmlResponse(nonce:)`. `HtmlSnippet` adds `cspNonce` only when given. UPGRADE.md has
  the four ⚠️ entries.
- `ExceptionHandler::$cspNonce` is set in `register()` (the handler is created by the project), so it carries a
  `@phpstan-ignore property.uninitialized`.
- Tests: new `CspNonceTest`, `HtmlSnippetTest` (via `CoreTestInstance`), nonce in the CSP header of
  `createHtmlResponse()`, `ContentHandlerTest`. The header test sets `$_SERVER['HTTP_HOST']` temporarily; `HttpRequest`
  caches host and protocol statically.
- Not covered by tests: that `Core` passes the same object to all consumers (runs the whole request), and
  `HtmlDocument` rendering the nonce. `example/` checked: `/` 200 and `nope.html` 404 both send a CSP header with a
  nonce (different per request); the example templates do not render `{cspNonce}`. No `src/` template uses it.
- Baseline unchanged (766 entries).

### Step 9 (v4.20.0) – done

- New `ExceptionHandlerContext`; `ExceptionHandler` keeps it in `private ?ExceptionHandlerContext $context`, which
  removes the `@phpstan-ignore property.uninitialized` of step 8. `Logger` lost `register()`, `get()` and the static
  instance. UPGRADE.md has three ⚠️ entries.
- Tests: new `ExceptionHandlerTest` (context without `register()` throws, `register()` keeps the context, second
  `register()` throws), doubles `RecordingLogger` and `ContextExposingExceptionHandler`. The test resets the static
  registered instance with `ReflectionProperty` (pattern of `AuthSessionTest`) and calls
  `restore_exception_handler()` in `tearDown()`.
- Not covered by tests: `handleException()` (every path sends the response and exits), so `RecordingLogger` is only
  used to construct the context; and that `Core` builds the context from its own values.

### Step 10.1 (v4.21.0) – done

- `LocaleHandler` has a public, pure constructor `(?Language, LanguageCollection)` and `$language`; `register()` takes
  the instance and calls `setlocale()`. `get()` / `isRegistered()` stay (comment: `LangTag`). The unavailable language
  throws a `LogicException`. `parseLanguageFile()` uses `require` instead of `require_once` (the per-instance
  `loadedLangFiles` guard is enough, and several instances in tests need the file).
- `RequestHandler::register()` returns the instance; `Core` creates and registers the `LocaleHandler` and passes it to
  `ContentHandler::register()`, `Route::loadLocalizedText()` and `ViewContext::$locale`.
- `ExceptionHandler::loadLocalizedText()` creates its `LocaleHandler` with the new constructor (still `Core::get()` /
  `RequestHandler::get()` there: part 10.3).
- New `LocaleHandlerTest` with fixtures `tests/Fixture/localeTexts.lang.php` / `localeEmpty.lang.php`; `register()` is
  not tested (`setlocale()` is process-wide). `ViewContextFactory` uses a `LocaleHandler` without language.

### Step 10.2 (v4.22.0) – done

- `Route`, `SessionSettings`, `FileSessionHandler`, `Core::prepareHttpResponse()`, `MicrosoftAuthenticator` and
  `MicrosoftIdToken` as described in 10.2; UPGRADE.md has a ⚠️ entry for each. `Route::$viewDirectory` moved right
  after `path` (a required parameter after optional ones is deprecated); named-argument callers are unaffected.
- `RequestHandler` throws a `LogicException` (was `Exception` in `setPreferredLanguage()`) with the same message before
  it sets an unavailable language as the preferred one.
- Tests: new `RouteTest`, `SessionSettingsTest`, `MicrosoftIdTokenTest` (prepared key file in a temp cache directory,
  no network); `AbstractSessionHandlerTest` checks the default save path of `FileSessionHandler` and no longer needs
  `CoreTestInstance`. Not covered: `MicrosoftAuthenticator` (logging, needs sessions/HTTP), the `Core` default session
  handler, the language check in `RequestHandler` (needs a request).
- Baseline unchanged (765 entries). Remaining `Core::get()` uses: `LogFile`, `HtmlSnippet`, `HtmlDocument`,
  `ExceptionHandler`, `RequestHandler`, `IfTag`, `SnippetTag`.

### Step 10.3 (v4.23.0) – done

- `RequestHandler::get()` / `register()` and `ContentHandler::get()` / `isRegistered()` / `register()` removed; design as
  in 10.3 above. UPGRADE.md has ⚠️ entries for them, `HtmlDocument`, `ExceptionHandlerContext` and
  `HtmlSnippet::createForCurrentView()`. `RequestHandler::$route`, `fileTitle`, `fileExtension` and `pathVars` are
  `public private(set)` (not `readonly`: PHPStan rejects readonly assignment outside the constructor) with a
  `@phpstan-ignore property.uninitialized`. `ContentHandler::processRequest()` also throws when called twice.
- Behaviour change: the domain check now runs after the default routes are built (it only affects the language root
  of the error page for an unknown domain). `example/` checked with debug off: the 404 pages of `/nope.html` and
  `/nope/x.html` (language, language root, file name) are identical to before.
- Tests: new `RequestHandlerTest` (sets `REQUEST_URI` / `HTTP_HOST`, no reflection), `ContentHandlerTest` (document
  before `processRequest()`), `ExceptionHandlerTest` (setters twice, `register()` result). Not covered: `processRequest()`
  and `getHtmlDocument()` after it, `HtmlDocument` (need a full request), redirects of `/`, the session language
  check, `handleException()` fallbacks, and that `Core` wires everything.
- `Core::get()` remains in `IfTag`, `SnippetTag`, `HtmlSnippet::render()` and `LogFile` (plus `CoreTestInstance`).
  Baseline 765 -> 760 entries.
