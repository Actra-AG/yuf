# Migrating from the old framework

yuf replaces the [Actra framework](https://github.com/Actra-AG/framework) (namespace `framework\`, copied into the
project and loaded with `require_once 'framework/Core.php'`, no Composer). This guide takes a project from the last
state of that framework (`main`) to the current yuf in one go. The version notes ([v0](v0.md) to [v4](v4.md) and
[UPGRADE.md](../../UPGRADE.md)) have the details; each row below links to them.

How to read the tables: **old → new**, the **search term** to find the spots in the old project, and the link to the
details. ⚠️ marks a change that gives no error but a different result: check these by hand.

## Before you start

- Requirements: PHP 8.5, Composer; [DDEV](https://ddev.com) is the easiest way to run both locally.
- Work on a branch; the old site keeps running until the new one is checked.
- A project on an older copy of the framework also takes the HTML and CSS changes of the framework's own
  [UPGRADE.md](https://github.com/Actra-AG/framework/blob/main/UPGRADE.md) (e.g. `bodyID` → `bodyClassName`, `body_*`
  → `body-*`, table and upload classes). Templates and CSS need a frontend review.
- Two tools find most of the work: PHPStan (`composer check`) finds renamed classes, methods and arguments once the
  namespace is changed; opening every page with `'debug' => true` shows each template error with file and line.

## Order of work

1. **Composer and layout:** Composer instead of the copied framework, `app/` beside the document root `public/`.
2. **Front controller:** `index.php` with `App`, `.env.php`. Goal of the first day: one page renders.
3. **Settings:** per-environment constants and secrets into `.env.php`.
4. **Database:** `FrameworkDb` with `DbSettings`.
5. **Views:** `ViewContext` through all base views, no static `::get()` any more.
6. **Mechanical renames:** HTML replacements and the classes of the tables below, then PHPStan until it is green.
7. **Request, session and login:** instances instead of static calls.
8. **Forms, tables, mail.**
9. **Templates, language files, error pages:** every page once with `debug`.
10. **Before going live:** the [checklist](#before-going-live).

## 1. Composer and directory layout

| Old → new | Search term | Details |
|:--|:--|:--|
| Framework copied into the project (`framework/`, own autoloader, `site/cache/cache.autoload`) → `composer require actra/yuf`; delete `framework/` and the autoloader cache | `require_once`, `Autoloader`, `autoloaderPath`, `cache.autoload` | [UPGRADE.md](../../UPGRADE.md#-composer-loads-all-classes-actraautoloader-is-no-longer-used) |
| Namespace `framework\…` → `actra\yuf\…`, same sub-namespaces (`core`, `html`, `form`, `db`, `auth`, …); `framework\Core` → `actra\yuf\Core`, new `actra\yuf\App` | `use framework\`, `framework\` | [setup.md](../setup.md#application-entry-point) |
| Project code `site/` inside the document root → `app/` beside the document root `public/` (only `index.php` and assets in `public/`); Composer loads it: `"autoload": {"psr-4": {"app\\": "app/"}}` | `siteDirectoryName`, `site\` | [setup.md](../setup.md#application-entry-point) |
| First step without renaming the namespace: `"psr-4": {"site\\": "app/"}` and `App::fromEnvironment(…, viewClassPrefix: 'site')` | `namespace site\` | [setup.md](../setup.md#application-entry-point) |
| Old compiled templates (`site/cache/`) → not used; the new cache is `app/cache/v<n>/` | `cache/` | [v4.md](v4.md#old-compiled-templates) |
| Deployment → `composer install --no-dev --optimize-autoloader`, delete `app/cache/v[0-9]*/` (not `v*/`: it also deletes `app/cache/values/`) | `deploy` | [setup.md](../setup.md#production) |

## 2. Front controller

The old `index.php` exists in three forms; all three become the same `App` call:

- **Oldest:** `Core::init(defaultTimeZone: …)`, then `$core->prepareHttpResponse(…)`.
- **Constructor:** `new Core(defaultTimeZone: …, siteDirectoryName: 'site', …)` (shown in the framework's README).
- **Settings at prepare time:** `$core->prepareHttpResponse(environmentSettingsModel: new EnvironmentSettingsModel(…),
  logger: …, routeCollection: …)`.

Before (generic example):

```php
require_once 'framework/Core.php';
$core = new Core();
$german = new Language(code: 'de', locale: 'de_CH');
$core->prepareHttpResponse(
    environmentSettingsModel: new EnvironmentSettingsModel(
        allowedDomains: ['www.example.com'],
        availableLanguages: new LanguageCollection(languages: [$german]),
        debug: false,
        copyrightYear: 2020,
        robots: 'index,follow',
        cspPolicySettingsModel: new CspPolicySettingsModel(imgSrc: "'self' data:"),
    ),
    logger: new Logger(logEmailRecipient: 'error@example.com', logDirectory: $core->logDirectory),
    routeCollection: new RouteCollection(routes: [
        new Route(path: '/', viewGroup: 'frontend', defaultFileName: 'home.html', isDefaultForLanguage: true,
            defaultContentType: ContentType::createHtml(), language: $german, acceptedExtension: ContentType::HTML),
        new Route(path: '/export/', viewGroup: 'export', defaultFileName: '', isDefaultForLanguage: false,
            defaultContentType: ContentType::createCsv(), language: $german, acceptedExtension: ContentType::CSV),
    ]),
)->sendAndExit();
```

After (`public/index.php`; `allowedDomains`, `debug`, `robots` and `logEmailRecipient` are in `.env.php`):

```php
require __DIR__ . '/../vendor/autoload.php';

App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2020)
    ->withLanguage(code: 'de', locale: 'de_CH')
    ->withCsp(cspPolicySettings: new CspPolicySettings(imgSrc: "'self' data:"))
    ->withHtmlRoute(path: '/', viewGroup: 'frontend', defaultFileTitle: 'home')
    ->withFileRoute(path: '/export/', viewGroup: 'export', contentType: ContentType::createCsv())
    ->run();
```

| Old → new | Search term | Details |
|:--|:--|:--|
| `new Core(…)`, `Core::init(…)` → `App::fromEnvironment(envFilePath:, copyrightYear:)`; directory names → the directory arguments of `fromEnvironment()` (full paths, defaults fit the layout above) | `new Core(`, `Core::init(` | [setup.md](../setup.md#application-entry-point) |
| `EnvironmentSettingsModel(allowedDomains:, debug:, robots:)`, `defaultTimeZone:`, `Logger(logEmailRecipient:)` → `.env.php` keys `allowedDomains`, `debug`, `robots`, `logEmailRecipient`, `defaultTimeZone` (checked at startup) | `EnvironmentSettingsModel`, `defaultTimeZone` | [setup.md](../setup.md#environment-settings) |
| `copyrightYear:` → argument of `fromEnvironment()` | `copyrightYear` | [setup.md](../setup.md#application-entry-point) |
| `availableLanguages: new LanguageCollection(…)` and `language:` per route → `withLanguage(code:, locale:)` before the routes; routes take the first language | `LanguageCollection`, `language:` | [setup.md](../setup.md#application-entry-point) |
| `new Route(…)` with HTML → `withHtmlRoute(path:, viewGroup:, defaultFileTitle:)` (`home.html` → `'home'`); other files → `withFileRoute(path:, viewGroup:, contentType:)` | `new Route(` | [setup.md](../setup.md#application-entry-point) |
| ⚠️ `withHtmlRoute()` redirects the default file name with 301 to the path (`/home.html` → `/`): old links still work, with one redirect; `redirectDefaultFileName: false` keeps both URLs | `home.html` | [views.md](../views.md#default-file-name) |
| ⚠️ An own `new Route(…)` needs `viewDirectory: $core->viewDirectory`, and `acceptedExtension` defaults to `null` (any extension) instead of `html` | `new Route(` | [v4.md](v4.md#-route-needs-viewdirectory) |
| `CspPolicySettingsModel` → `withCsp(cspPolicySettings: new CspPolicySettings(…))` | `CspPolicySettingsModel` | [v4.md](v4.md#-settings-bundles-and-value-objects-without-model-suffix) |
| `new Logger(…)` → nothing (default `FileLogger`), or `withLogger(logger: new FileLogger(…, mailer: …))` | `new Logger(` | [setup.md](../setup.md#error-log) |
| `individualExceptionHandler:` → `withExceptionHandler()`; `individualSessionHandler:` → nothing (default), `withoutSession()` or `withSessionHandler()` | `individual` | [setup.md](../setup.md#application-entry-point) |
| Own code for "the page requested before the login" → `withLoginPath(loginPath: '/login.html')` and `LoginRedirect::findReturnPath()` | `UnauthorizedAccessRightException` | [views.md](../views.md#required-access-rights-and-the-login-page) |
| ⚠️ A host that is not in `allowedDomains` gets a 404, also over HTTP (no redirect to HTTPS) | `allowedDomains` | [UPGRADE.md](../../UPGRADE.md#-routing-host-and-language-files) |

## 3. Settings

| Old → new | Search term | Details |
|:--|:--|:--|
| Constant class for per-environment values and secrets (`EnvSettings::DB_PASSWORD`, `::DEBUG`, mail server, IP whitelists) → own flat keys in `.env.php` (`'db.password'`), read with `$core->environmentSettings->getString()`, `getInt()`, `getBool()`, `getStringList()`, `getEnum()` and passed to own settings objects; constants that are the same everywhere may stay | `EnvSettings::` | [setup.md](../setup.md#environment-settings) |
| `Core::config()`, `Core::get()` → `$core->environmentSettings`, `$core->logDirectory` etc., passed on; in views `$this->context->…` | `Core::config(`, `Core::get(` | [v4.md](v4.md#-coreconfig-is-removed-the-environment-settings-are-typed), [v4.md](v4.md#-coreget-removed) |
| ⚠️ A locale with `.UTF-8` (`de_CH.UTF-8`) passed on to `DbSettings(timeNamesLanguage:)` throws: MySQL expects `de_CH` | `timeNamesLanguage`, `->locale` | [database.md](../database.md#connection) |

## 4. Database

| Old → new | Search term | Details |
|:--|:--|:--|
| `FrameworkDB::getInstance()`, `extends FrameworkDB` → `new FrameworkDb(connectionParameters: DbConnectionParameters::forMysql(dbSettings: new DbSettings(hostName:, databaseName:, userName:, password:)))`; one object per connection, keep an own accessor | `FrameworkDB`, `getInstance(` | [v4.md](v4.md#-frameworkdb-no-connection-pool-constructor-takes-connection-parameters) |
| `DbSettingsModel(identifier:, …)` → `DbSettings(…)` without `identifier`; ⚠️ defaults `utf8mb4`, `lc_time_names` `de_CH`, `sql_safe_updates=1` | `DbSettingsModel` | [v4.md](v4.md#-dbsettings-final-readonly-no-identifier-validated) |
| ⚠️ Results are native `int`/`float` instead of strings (`=== '1'` is false now) | `=== '`, `(int)$row` | [v0.md](v0.md#v061-2026-03-11) |
| `lastInsertId()` → `getLastInsertId()` | `lastInsertId(` | [v4.md](v4.md#-frameworkdblastinsertid-throws-getlastinsertid-returns-the-int) |
| `select()` + casts → `selectRows()`, `selectRow()`, `iterateRows()` with typed `DbRow` getters; `ExecuteAndFetch()` → `executeAndFetch()` | `->select(`, `ExecuteAndFetch` | [database.md](../database.md#typed-rows) |
| Parameters: `float\|int\|string\|null` only, ⚠️ `false` binds as `''` (pass `0`/`1`); `createInQuery(paramArr:)` → `values:` | `paramArr`, `false` | [v4.md](v4.md#-frameworkdb-dbselectstmt-typed-parameters-prepare-options) |
| `addOrderPart($column, false)` → `addOrderPart(column:, ascending:)`; `createFromSqlQuery()` rejects `GROUP BY`, `ORDER BY`, `LIMIT`, `UNION`; ⚠️ `addWherePart()` parts are put in parentheses | `addOrderPart(`, `createFromSqlQuery(` | [v3.md](v3.md#v320-2026-08-30), [v3.md](v3.md#v310-2026-08-30) |
| `DbQueryLogList::getLog()` → `$db->getQueryLog()` | `DbQueryLogList` | [v4.md](v4.md#-query-log-per-connection) |

## 5. Views

| Old → new | Search term | Details |
|:--|:--|:--|
| Views get a `ViewContext`: `__construct(ViewContext $context)` and `parent::__construct(context: $context, requiredViewGroupName:, …)` in every view and base view; dependencies with `ViewMap` or `ClassNameViewFactory(create:)` | `extends BaseView`, `parent::__construct(` | [v4.md](v4.md#-views-get-a-viewcontext), [views.md](../views.md#views) |
| `HtmlDocument::get()` → `$this->getHtmlDocument()` | `HtmlDocument::get(` | [v4.md](v4.md#-htmldocumentget-is-removed) |
| `RequestHandler::get()->route`, `->pathVars`, `->fileTitle` → `$this->context->route`, `->pathVars`, `->fileTitle` | `RequestHandler::get(` | [v4.md](v4.md#-requesthandlerget-and-requesthandlerregister-removed) |
| `ContentHandler::get()->setContent()` → `$this->setContent()` or `$this->context->content->…` | `ContentHandler::` | [v4.md](v4.md#-contenthandlerget-isregistered-and-register-removed) |
| `LocaleHandler::get()->getText()` → `$this->context->locale->getText()` | `LocaleHandler::` | [v4.md](v4.md#-localehandlerget-register-and-isregistered-removed) |
| `JsonRequestBody::get()` → `$this->getJsonRequestBody()` | `JsonRequestBody::get` | [v4.md](v4.md#-jsonrequestbodyget-is-removed) |
| `CspNonce::get()` → `$this->context->content->cspNonce->value` | `CspNonce::` | [v4.md](v4.md#-cspnonce-is-an-object-per-request-cspnonceget-is-removed) |
| `(int)$this->getPathVar(nr: 1)` → `getRequiredPathVarAsInt(nr: 1)` (404 instead of 0), `getPathVarAsInt()`, `getRequiredPathVarAsString()` | `getPathVar(` | [views.md](../views.md#path-variables) |
| `new InputParameter(name:, isRequired:)` → `+ source: InputSourceEnum::QUERY` or `::POST` | `new InputParameter(` | [v4.md](v4.md#-changed-constructors-and-methods-1) |
| `setSuccessResponseContent()`, `setErrorResponseContent()` → new arguments; ⚠️ JSON envelope `{"success":…,"data"\|"error":{…}}` | `ResponseContent(` | [v2.md](v2.md#-breaking-changes), [views.md](../views.md#json-endpoints) |
| `$htmlDocument->contentFileName = 'x'` → `'x.html'` (with extension), or `useContentFile(filePath:)` | `contentFileName` | [views.md](../views.md#content-file) |
| `HtmlSnippet::createForCurrentView(snippetName:)->render()` → `createForCurrentView(route: $this->context->route, snippetName:, cspNonce: …)->render(templateEngine: $this->context->templateEngine)` | `HtmlSnippet::` | [v4.md](v4.md#-htmlsnippetrender-needs-the-template-engine) |
| Own static helpers of views (`home::getPath()`) → project code, unchanged | `function getPath` | – |

## 6. Request and response

| Old → new | Search term | Details |
|:--|:--|:--|
| Static `HttpRequest::getX()` → `$this->context->httpRequest->getX()` (`$core->httpRequest` in `index.php`); `getURI`/`getURL`/`isSSL` → `getUri`/`getUrl`/`isSsl`; `getRequestMethod()` → `getMethod()` (`RequestMethodEnum`); `getProtocol()` → `ProtocolEnum`; `getBearer()` → `getBearerToken()` | `HttpRequest::` | [v4.md](v4.md#-httprequest-static-getters-become-instance-methods) |
| `$_GET`, `$_POST`, `$_SERVER`, `$_FILES`, `$_COOKIE` → the getters of `HttpRequest` | `$_GET`, `$_POST`, `$_SERVER` | [views.md](../views.md#the-request) |
| `getInputString()`, `getInputInteger()` → `getQueryString()` / `getPostString()` etc.; ⚠️ query and post are not merged any more | `getInput` | [v4.md](v4.md#-no-merged-input-query-or-post) |
| ⚠️ Number getters are strict: `'12abc'`, `''`, `'+5'` give `null` instead of a number or `0` | `getInputInteger`, `?? 0` | [v4.md](v4.md#-numbers-are-strict) |
| `HttpStatusCode::X` → `HttpStatusCodeEnum::X` | `HttpStatusCode::` | [v4.md](v4.md#-request-response-and-error-names-follow-the-coding-standard) |
| `HttpResponse::redirectAndExit($uri)` → `redirectAndExit(relativeOrAbsoluteUri:, httpRequest:)`; `setSameSiteCookieTemporaryToLax:` → `sameSiteLaxSessionHandler:` | `redirectAndExit(` | [v4.md](v4.md#-sending-ends-with-never-a-responsesender-can-be-passed) |
| `createResponseFromFilePath(…)` → `+ httpRequest:`, returns a response instead of ending the script; ⚠️ HTML, SVG, XML and JS files are always downloaded | `createResponseFromFilePath(` | [v4.md](v4.md#-httpresponsecreateresponsefromfilepath-no-longer-ends-the-script), [UPGRADE.md](../../UPGRADE.md#-file-responses-and-content-security-policy) |
| `UrlHelper::generateAbsoluteUri($uri)` → `+ httpRequest:` | `generateAbsoluteUri(` | [v4.md](v4.md#-changed-constructors-and-methods-1) |
| ⚠️ HTML, JSON and text responses are sent with `Cache-Control: private, no-store`; opt-in `respondNotModifiedIfUnchanged()` | caching | [views.md](../views.md#responses-and-caching) |

## 7. HTML replacements and templates

The old names described the input ("already encoded"), the new names describe the output. A rename one to one keeps
the output; afterwards check every `addHtml()` and `fromHtml()` that gets user data and use `addText()` or `fromText()`.

| Old → new | Search term | Details |
|:--|:--|:--|
| `addEncodedText(identifier:, content:)` → `addHtml(identifier:, html:)`; `addUnencodedText(…, content:)` → `addText(…, text:)` | `addEncodedText`, `addUnencodedText` | [v4.md](v4.md#-the-replacement-api-says-what-it-does-fromhtml--fromtext-addhtml--addtext) |
| `HtmlText::encoded(textContent:)` → `HtmlText::fromHtml(html:)`; `unencoded(textContent:)` → `fromText(text:)`; new `fromTextWithLineBreaks()`, `fromSanitizedHtml()` | `HtmlText::encoded`, `HtmlText::unencoded`, `nl2br` | same |
| `addTextElement(propertyName:, content:, isEncodedForRendering:)` → `addHtml(propertyName:, html:)` or `addText(propertyName:, text:)`; `$object->data` → `toTemplateData()`; ⚠️ added data objects are copied | `addTextElement`, `->data->` | same, [v4.md](v4.md#-htmldataobjectdata-is-replaced-by-totemplatedata) |
| `HtmlReplacement::encodedText()`, `unencodedText()`, `object()` → `fromHtml()`, `fromText()`, `fromDataObject()` | `HtmlReplacement::` | [v4.md](v4.md#-htmlreplacement-named-constructors-are-from) |
| `new HtmlTagAttribute(name:, value:, valueIsEncodedForRendering:)` → `HtmlTagAttribute::fromText()`, `fromHtml()`, `fromName()` | `valueIsEncodedForRendering` | [v4.md](v4.md#-htmltagattribute-named-constructors-instead-of-valueisencodedforrendering) |
| Removed tags: `elseif`, `for2`, `forgroup`, `option`, `checkbox`, `radio`, `checkboxOptions`, `radioOptions`, `formComponent`, `formAddRemove`, `compare="hasSnippet"`, the `for` attributes `groups`, `classfirst`, `classlast`, `class`, `_count` → nested `if`/`else`, values prepared by the view | `tst:elseif`, `tst:for2`, `tst:option`, `hasSnippet` | [v4.md](v4.md#-removed-tags-and-features) |
| Raw `{var}`, `{var.prop}`, `${var.prop}` in `for`, method calls in selectors, PHP in templates → `{tst:text value='var.prop'}`, values of the view | `{`, `<?` in `.html` | same |
| ⚠️ `if` compares strictly (no PHP `==`); `gt`, `lt` need numbers | `tst:if` | [v4.md](v4.md#-if-comparison-rules) |
| Stricter syntax: `<tst:text …/>` closes itself, `<tst:else>` directly after `</tst:if>`, wrong closing tags throw | `tst:text`, `tst:else` | [v4.md](v4.md#-stricter-syntax-and-other-behaviour-of-the-tags) |
| ⚠️ Plain strings of own `TemplateData` or `ArrayObject` are escaped by the engine: own HTML with `HtmlText::fromHtml()` | `new ArrayObject(`, `TemplateData` | [v4.md](v4.md#-output-of-plain-strings-is-escaped-by-the-engine) |
| Own tags (`customtags`) → implement `TemplateTag`, `withTemplateTags()` | `customtags` | [templates.md](../templates.md#own-template-tags) |
| `NavigationItem(title: 'x')` → `title: HtmlText::fromText(text: 'x')`; `activeCssClass`/`inactiveCssClass` → `activeSubToggleClass`/`inactiveSubToggleClass`; navigation per request with `withNavigation()` | `new NavigationItem`, `activeCssClass` | [UPGRADE.md](../../UPGRADE.md#-tables-and-navigation), [v1.md](v1.md#v150-2026-05-18) |
| Template property `buttonClass` → `cssClass` | `buttonClass` | [v1.md](v1.md#-breaking-changes) |

## 8. Language files and error pages

| Old → new | Search term | Details |
|:--|:--|:--|
| Language files stay: `view/<group>/language/<code>/global.lang.php` and `<file title>.lang.php` with `$txt['key'] = '…';`; values must be strings | `.lang.php`, `$txt[` | [UPGRADE.md](../../UPGRADE.md#-routing-host-and-language-files) |
| ⚠️ Error pages load only `global.lang.php`: move texts that error pages use there | `tst:lang` in `error_docs` | [v4.md](v4.md#-localehandlerget-register-and-isregistered-removed) |
| ⚠️ A file title with other characters than letters, digits and `_` is a 404; `-` separates path variables | file names | [views.md](../views.md#path-variables) |
| Error pages stay (`debug.html`, `default.html`, `notFound.html`, `unauthorized.html`) in `app/error_docs/`; ⚠️ their values are escaped text (`csrfField` stays HTML) | `error_docs` | [v4.md](v4.md#-exceptionhandler-hooks-return-a-response) |
| `extends ExceptionHandler`: `send…HttpResponseAndExit()` → `create…Response(): HttpResponse` | `extends ExceptionHandler` | same |
| `Logger::get()->…` → the logger instance; `LogFile::info(…)` → `new LogFile(logDirectory:, group:, logFileName:)->write(line:)` | `Logger::`, `LogFile::` | [v4.md](v4.md#-loggerregister-and-loggerget-are-removed), [v4.md](v4.md#-logfile-is-an-instance-class-only) |
| ⚠️ In production, JSON and text errors show fixed texts and the status code | API clients | [v4.md](v4.md#-production-errors-show-fixed-texts) |

## 9. Session and login

| Old → new | Search term | Details |
|:--|:--|:--|
| `$_SESSION[…]` → `$this->context->session?->set()`, `getString()`, `getInt()`, `remove()` …; the key `yuf` is reserved | `$_SESSION` | [session-and-login.md](../session-and-login.md#session) |
| `session_start()` → nothing: ⚠️ the session starts on first use and is closed after the view (later writes throw) | `session_start(` | [v4.md](v4.md#-the-session-starts-on-first-use), [v4.md](v4.md#-the-session-is-closed-after-the-view) |
| `AbstractSessionHandler::getSessionHandler()`, `register()` → `$this->context->sessionHandler`, `$session->getId()`, `clearUserData()` | `AbstractSessionHandler::` | [v4.md](v4.md#-static--instance) |
| `SessionSettingsModel` → `SessionSettings` | `SettingsModel` | [v4.md](v4.md#-settings-bundles-and-value-objects-without-model-suffix) |
| Static `AuthSession::isLoggedIn()`, `logIn(authSessionID:)`, `logOut()`, `getAuthSessionID()` → `$this->context->authSession?->isLoggedIn()`, `logIn(authSessionId:)`, …; ⚠️ `logOut()` clears all session data | `AuthSession::` | [v4.md](v4.md#-static--instance) |
| `CsrfToken::getToken()`, `validateToken()`, `renderAsHiddenPostField()` → `$this->context->formContext->csrfTokenSource`, `CsrfHiddenFieldRenderer`; never in the URL | `CsrfToken::`, `csrftoken` | [v4.md](v4.md#-static--instance), [v4.md](v4.md#-the-csrf-token-is-never-read-from-the-url) |
| `AuthMethod`, `AuthResult` → `AuthMethodEnum`, `AuthResultEnum`; `->render()` → `->label(messages: new AuthResultMessages())->render()`; `ID`, `userID`, `sessionID` → `id`, `userId`, `sessionId` | `AuthResult`, `->ID`, `userID` | [v4.md](v4.md#-auth-and-session-names-follow-the-coding-standard), [UPGRADE.md](../../UPGRADE.md#-authresultenumrender-is-removed) |
| `extends Authenticator` → `parent::__construct(httpRequest:, authSession:, maxAllowedWrongPasswordAttempts:, loginRateLimit:)` (`loginRateLimit: null` to opt out) | `extends Authenticator` | [UPGRADE.md](../../UPGRADE.md#-authenticator-required-loginratelimit) |
| `extends AuthUser` → `id:`, `password: ?Password`, `ipWhitelist:`; implement `dbUpdatePassword()`, `dbRegisterWrongPasswordAttempt()`, `dbReleaseWrongPasswordAttempt()`; ⚠️ a login does not reset the counter of wrong passwords | `extends AuthUser`, `dbIncreaseWrongPasswordAttempts` | [v4.md](v4.md#-authuser-persist-the-upgraded-password-hash), [UPGRADE.md](../../UPGRADE.md#-wrong-passwords-are-counted-before-the-check-a-login-does-not-reset-the-counter) |
| `Password` hashes: column with 255 characters; old hashes are upgraded on the next login; dummy passwords → `password: null`; API keys → `SecretTokenHash` | `Password::`, `ACCESS_DO_PASSWORD_LOGIN` | [v4.md](v4.md#-password-password_hash-instead-of-salt-and-sha-256), [UPGRADE.md](../../UPGRADE.md#-authuserpassword-is-nullable-access_do_password_login-is-removed) |
| `MicrosoftAuthenticator` → new constructor, `tenantId:`/`clientId:`, `findAuthUserByMicrosoftAccount()` | `MicrosoftAuthenticator` | [UPGRADE.md](../../UPGRADE.md#-microsoft-login-identity-by-object-id-and-tenant-id-nonce-handled-by-yuf) |

## 10. Forms

The [form checklist of v4.0.0](v4.md#v400-2026-10-04) goes through a form step by step.

| Old → new | Search term | Details |
|:--|:--|:--|
| `new Form(name:)` → `new Form(context: $this->context->formContext, name:, messages: FormMessages::german())`; ⚠️ without `messages:` all texts are English | `new Form(`, `extends Form` | [v4.md](v4.md#v400-2026-10-04) |
| `getRawValue()` → `getValueAsString()`, `getValueAsInt()`, `getValueAsDecimal()`, `getValues()`, `isChecked()`, `getFiles()`, `getRequiredValueAs…()` | `getRawValue(` | [forms.md](../forms.md#typed-values) |
| `AmountField` → `IntegerField`, `FloatField`, `DecimalField(scale:)`; `HiddenField(valueIsInt:)` → `HiddenIntegerField`; multiple selection → `MultiSelectOptionsField`, `MultiToggleField` | `AmountField`, `valueIsInt`, `multiple: true` | [v4.md](v4.md#v400-2026-10-04) |
| `setValue()` + `setOriginalValue()` → value in the constructor, typed `setValue()`; `addRule(new RequiredRule(…))` → `addRequiredRule()`; `extends FormRule` → `StringRule`, `IntegerRule`, …; validation rules of the field types are removed | `setOriginalValue`, `RequiredRule`, `extends FormRule` | same |
| `addError(string, bool)` → `addError(errorMessage: HtmlText::fromText(…))` | `addError(` | same |
| `InputTypeValue`, `AutoCompleteValue`, `RadioOptionsLayout`, `CheckboxOptionsLayout` → `…Enum`; `PasswordField(autoComplete:)` → `purpose: PasswordPurposeEnum::…` | `InputTypeValue`, `AutoCompleteValue` | same |
| `EmailField(…)` → `+ mailDomainResolver: $this->context->formContext->mailDomainResolver` (`null`: syntax only) | `new EmailField(` | [UPGRADE.md](../../UPGRADE.md#-email-fields-null-mx-rejected-mail-domain-resolver-from-the-form-context-no-domain-check-in-debug-mode) |
| `removeCsrfProtection()` → `csrfProtection: false`; POST forms need a session; ⚠️ the CSRF check comes first | `removeCsrfProtection` | [UPGRADE.md](../../UPGRADE.md#-forms-csrf-checked-first-required-for-post-forms) |
| `FormRenderer::prepare()` → `createHtmlTag()` | `function prepare(` | [v4.md](v4.md#-formrendererprepare-is-replaced-by-createhtmltag) |
| `FileDataModel` → `UploadedFile`; `FileField` → `+ storage:, allowedFileTypes:`, form with `acceptUpload: true` | `FileDataModel`, `new FileField(` | [v4.md](v4.md#-filefield-allowedfiletypes-is-required-maxfilesize-is-new), [UPGRADE.md](../../UPGRADE.md#-uploads) |
| Own honeypot or time checks → `addSpamProtection(spamProtection: new SpamProtection(…, rateLimit:, captcha:))` | `honeypot` | [forms.md](../forms.md) |
| ⚠️ Text values are trimmed, `maxLength` is checked on the server, passwords are not rendered back, plain text in `placeholder` or CSS classes is escaped | – | [v4.md](v4.md#-plain-text-values-of-the-form-markup-are-escaped) |

## 11. Tables, search, CSV, mail and other classes

| Old → new | Search term | Details |
|:--|:--|:--|
| `TableItemModel` → `TableItem` (`getRow()` gives a `DbRow`); column `label:` → `HtmlText::fromText()` | `TableItemModel`, `label:` | [v4.md](v4.md#-settings-bundles-and-value-objects-without-model-suffix), [UPGRADE.md](../../UPGRADE.md#-tables-and-navigation) |
| `new DbResultTable(…)`, `TableHelper::createDbResultTable(…)` → `+ templateEngine:, httpRequest:, session:`; `TableFilter` → `+ httpRequest:, session:, csrfTokenSource:`; ⚠️ filters only via POST with a token | `DbResultTable(`, `TableFilter(` | [v4.md](v4.md#-pagination-tablepaginationrenderer-tablefilter-dbresulttable), [v4.md](v4.md#-tablefilter-checks-the-csrf-token) |
| `TableHelper::SORT_ASC` → `TableSortDirectionEnum::ASC`; `noDataHtml` and the result texts → `messages: new TableMessages(…)` | `SORT_ASC`, `noDataHtml` | [v4.md](v4.md#-sort-direction-is-tablesortdirectionenum), [UPGRADE.md](../../UPGRADE.md#-smarttable-the-text-properties-are-removed) |
| ⚠️ `ActionsColumn` labels are escaped; markup `td-action` without list (frontend review) | `ActionsColumn`, `linkHTML` | [v4.md](v4.md#-actionscolumn-labels-are-encoded-renderactionlinks-is-gone), [v1.md](v1.md#v170-2026-05-25) |
| `SearchHelper` → `SearchState` and `SearchQueryBuilder` | `SearchHelper` | [v4.md](v4.md#-searchhelper-is-replaced-by-searchquerybuilder-and-searchstate) |
| `CSVFile(utf8Encode:)` → `CsvFile(addByteOrderMark:)`; `pushDownloadAndExit()` → `+ httpRequest:`; ⚠️ cells starting with `= + - @` get a leading `'` | `CSVFile` | [v4.md](v4.md#-csvfile-formula-protection-renamed-argument-final) |
| `SMTPMailer(…, useTls:)` → `SmtpMailer(serverAddress:, hostName:, smtpUserName:, smtpPassword:, serverNameCache: $core->fileCache, encryption: SmtpEncryptionEnum::STARTTLS)`; `$mail->send()` → `send(abstractMailer: $mailer)`; `MailerConstants::` → enums | `SMTPMailer`, `->send(`, `MailerConstants::` | [mail.md](../mail.md), [v4.md](v4.md#-the-mailers-require-servernamecache) |
| Other renames: `FrameworkDB` → `FrameworkDb`, `SimpleXMLExtended` → `SimpleXmlExtended`, `addCC()` → `addCc()`, `StringUtils` methods in camelCase, `IpTypeEnum::ip` → `IP` | the old names | [v4.md](v4.md#-class-method-and-constant-names-follow-the-coding-standard) |
| Removed without class of that name: `FrameworkUtils`, `TimeUtils`, `JsonWebToken`, `ImageResizer`, `ValidatedCompanyNumber`, `MailerFunctions` → own code, or `AmountFormatter`, `AuthWebToken` | the class names | [v4.md](v4.md#-mailerfunctions-is-removed) |
| `CountryCodeEnum::AA`, `UR`, `HQ`, `JN`, `JQ`, `VZ`, `AN`, `CS`, `YU` removed → migrate stored values | `CountryCodeEnum::` | [UPGRADE.md](../../UPGRADE.md#-countrycodeenum-codes-without-country-removed) |

## Before going live

- ⚠️ All users are logged out once: yuf keeps its session data under `$_SESSION['yuf']`; table filters and searches
  start from their defaults ([v4.md](v4.md#-users-are-logged-out-once-tables-lose-their-state)).
- ⚠️ CSP: `default-src 'self'` and `form-action 'self'` are stricter; check the browser console on every page
  ([UPGRADE.md](../../UPGRADE.md#-csppolicysettings-default-default-src-self)).
- ⚠️ Form and table texts: `FormMessages::german()`, `TableMessages` (default German), pagination titles.
- ⚠️ CSV exports: formula prefix `'`; database results: native numbers; request numbers: strict.
- Every page once with `'debug' => true` (template errors with file and line), then `'debug' => false`.
- Production settings: `zend.exception_ignore_args=On`, PHP version and request and upload limits, client IP behind a
  proxy ([setup.md](../setup.md#production), [setup.md](../setup.md#request-and-upload-limits)).
- Deployment deletes `app/cache/v[0-9]*/` ([setup.md](../setup.md#production)).
- Frontend review of the changed templates and CSS, including the HTML changes of the framework's
  [UPGRADE.md](https://github.com/Actra-AG/framework/blob/main/UPGRADE.md) for projects on an older copy.
