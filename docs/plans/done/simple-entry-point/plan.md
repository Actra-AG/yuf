# Plan: simple entry point (`App`) for new projects and migrations

## Problem

Migrating a project from the predecessor of yuf (before Composer) gets stuck at the front controller (`index.php`)
before any view runs. The old `index.php` had one call with all settings; today the same setup needs seven classes,
three separate places for one language and repeated arguments on every route. The errors that follow are silent (404)
or far away from the cause.

A typical old front controller (generic example, shortened):

```php
require_once 'framework/Core.php';
$core = new Core();
$languageDE = new Language(code: 'de', locale: 'de_CH');
$core->prepareHttpResponse(
    environmentSettingsModel: new EnvironmentSettingsModel(
        allowedDomains: EnvSettings::ALLOWED_DOMAINS,
        availableLanguages: new LanguageCollection(languages: [$languageDE]),
        debug: EnvSettings::DEBUG,
        copyrightYear: 2020,
        robots: 'noindex,nofollow',
        cspPolicySettingsModel: new CspPolicySettingsModel(imgSrc: "'self' data:"),
    ),
    logger: new Logger(logEmailRecipient: 'error@example.com', logDirectory: $core->logDirectory),
    routeCollection: new RouteCollection(routes: [
        new Route(path: '/', viewGroup: 'frontend', defaultFileName: home::getPath(withDirectory: false),
            isDefaultForLanguage: true, defaultContentType: ContentType::createHtml(), language: $languageDE,
            acceptedExtension: ContentType::HTML),
        new Route(path: '/news/', viewGroup: 'news', defaultFileName: 'overview.html', /* … */),
        new Route(path: '/export/', viewGroup: 'export', defaultContentType: ContentType::createCsv(), /* … */),
        new Route(path: '/pdf/', viewGroup: 'pdf',
            defaultContentType: ContentType::createFromFileExtension(extension: 'pdf'), /* … */),
    ]),
)->sendAndExit();
```

The same with today's API (what the developer has to find out alone):

```php
require __DIR__ . '/../vendor/autoload.php';
$core = Core::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2020);
$german = new Language(code: 'de', locale: 'de_CH');
$core->availableLanguages->add(language: $german);
$core->prepareHttpResponse(
    routeCollection: new RouteCollection(routes: [
        new Route(
            path: '/',
            viewDirectory: $core->viewDirectory,
            viewGroup: 'frontend',
            defaultFileName: 'home.html',
            isDefaultForLanguage: true,
            defaultContentType: ContentType::createHtml(),
            language: $german,
            acceptedExtension: 'html',
        ),
        // … the same 8 arguments for /news/, /export/ and /pdf/
    ]),
    cspPolicySettings: new CspPolicySettings(imgSrc: "'self' data:"),
)->sendAndExit();
```

### Stumbling blocks (each one costs time)

| #  | Stumbling block                                                                                                  | Today                                                                       |
|:---|:-----------------------------------------------------------------------------------------------------------------|:----------------------------------------------------------------------------|
| 1  | `require_once 'framework/Core.php'` → Composer, `composer.json` with PSR-4 for the own namespace                   | only in `docs/setup.md`                                                     |
| 2  | `EnvironmentSettingsModel` and the constant class `EnvSettings` → `.env.php` with fixed keys                     | key table in `docs/setup.md`, no mapping of the old arguments              |
| 3  | The language must be added to `$core->availableLanguages` **and** given to every route                           | forgotten: the route is silently no default route, every request is a 404  |
| 4  | Every route needs `viewDirectory: $core->viewDirectory`                                                          | required argument, no default                                              |
| 5  | `defaultContentType`, `acceptedExtension` and `isDefaultForLanguage` repeat the same information                 | 3 arguments per route                                                       |
| 6  | CSP moved from the environment model to `prepareHttpResponse(cspPolicySettings:)`, `Logger` became `FileLogger`  | found only in `docs/upgrade/v*.md`                                         |
| 7  | Directory layout and namespace: `app/` beside the document root, namespace `app\` (`viewClassPrefix`)              | defaults of `fromEnvironment()`, the class prefix is a route argument       |
| 8  | Session: on by default (`individualSessionHandler: null`), off with `false`                                      | `false\|AbstractSessionHandler\|null`, hard to read                         |
| 9  | Static helpers of the old views (`home::getPath()`) do not exist any more                                        | no hint                                                                     |
| 10 | The upgrade notes are per version (`docs/upgrade/v0.md` … `v4.md`, `UPGRADE.md`): 2000+ lines, no path "from the old framework" | –                                                                           |

## Goal

1. A new project's `index.php` is three lines; everything that differs between environments stays in `.env.php`:

   ```php
   require __DIR__ . '/../vendor/autoload.php';

   App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)->run();
   ```

   Without own routes this serves `app/view/frontend/` at `/` (`index.html`, HTML, no language files needed).

2. A migrated project writes one short line per language and per route, with no class it does not need:

   ```php
   require __DIR__ . '/../vendor/autoload.php';

   App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2020)
       ->withLanguage(code: 'de', locale: 'de_CH')
       ->withCsp(cspPolicySettings: new CspPolicySettings(imgSrc: "'self' data:"))
       ->withHtmlRoute(path: '/', viewGroup: 'frontend', defaultFileTitle: 'home')
       ->withHtmlRoute(path: '/news/', viewGroup: 'news', defaultFileTitle: 'overview')
       ->withFileRoute(path: '/export/', viewGroup: 'export', contentType: ContentType::createCsv())
       ->withFileRoute(path: '/pdf/', viewGroup: 'pdf', contentType: ContentType::createFromFileExtension(extension: 'pdf'))
       ->run();
   ```

3. Every misconfiguration fails at startup with a message that names the fix (no silent 404).
4. One migration guide "from the old framework" maps every old argument to its new place.

`App` is a facade only: it builds `Core`, `Language`, `Route` and `RouteCollection` and calls `prepareHttpResponse()`.
No new behaviour, `Core` and `Route` stay the full API for special cases. Not breaking: minor release.

## Design

### `actra\yuf\App` (`src/App.php`, `final readonly`)

- `public static function fromEnvironment(string $envFilePath, int $copyrightYear, string $appDirectory = …,
  string $viewClassPrefix = Core::APP_CLASS_PREFIX): App` calls `Core::fromEnvironment()` at once (error handler and
  `.env.php` check as early as today) and passes the directory arguments through. `$viewClassPrefix` is the default
  of all routes (`'site'` keeps the old namespace during a migration).
- `public function __construct(Core $core, …)` for tests (no globals, like `Core`).
- `public Core $core`: for project settings (`$app->core->environmentSettings->getString(key: …)`) and everything the
  facade does not cover.
- Immutable copies (naming.md: `with…`), each returns a new `App`:

  | Method                                                                                                      | Builds                                                                                                                                                         |
  |:------------------------------------------------------------------------------------------------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------------|
  | `withLanguage(code:, locale:)`                                                                               | `Language`, added to `availableLanguages` in `run()`; the first language is the default language of the routes                                                  |
  | `withHtmlRoute(path:, viewGroup:, defaultFileName: 'index.html', language: null, viewFactory: null)`        | `Route` with `viewDirectory`, `ContentType::createHtml()`, `acceptedExtension: 'html'`, `isDefaultForLanguage` for the first HTML route of each language     |
  | `withFileRoute(path:, viewGroup:, contentType:, defaultFileName: '', viewFactory: null)`                    | `Route` for downloads/API (CSV, PDF, JSON): `acceptedExtension` from the content type, never default for a language                                         |
  | `withRoute(route:)`                                                                                          | escape hatch for a fully configured `Route` (`viewCallback`, `forceFileName`, …)                                                                             |
  | `withCsp(cspPolicySettings:)`, `withoutSession()`, `withSessionHandler(sessionHandler:)`, `withLogger(logger:)`, `withLoginPath(loginPath:)`, `withTemplateTags(templateTags:)`, `withNavigation(navigationProvider:)`, `withExceptionHandler(exceptionHandler:)` | the arguments of `prepareHttpResponse()` and `RouteCollection`, one method each instead of the `false\|…\|null` union |

- `public function prepare(): HttpResponse` builds everything and calls `prepareHttpResponse()` (tested);
  `public function run(): never` is `prepare()->sendAndExit()`.
- Without `withHtmlRoute()`/`withFileRoute()`/`withRoute()`, `prepare()` adds the default route
  `withHtmlRoute(path: '/', viewGroup: 'frontend')`.
- Startup checks with messages that name the fix (`LogicException`):
  - a route language that was not added with `withLanguage()` (stumbling block 3),
  - two routes of one language marked as default,
  - a `.env.php` that does not exist: message with the path and a pointer to `.env.example.php` (in
    `Core::loadEnvironmentFile()`, helps `Core` users too).

### Decisions

- Languages and the copyright year are project code, the same in every environment (dev, stage, prod): they stay in
  `index.php` (`withLanguage()`, `fromEnvironment(copyrightYear:)`), never in `.env.php`. `.env.php` holds only what
  differs between environments.

### Open point

- Silent 404 for a route whose language is not available (`RequestHandler::createDefaultRoutes()`, stumbling block 3):
  `App` checks it; whether `RequestHandler` should throw too is a behaviour change (⚠️ `UPGRADE.md`), not part of this
  plan.

## Steps

1. **`App` facade** (`src/App.php`, `tests/AppTest.php`): as designed above. Tests with `new App(core: new Core(…))`
   and `prepare()`: default route, single language as default language of all routes, first HTML route default per
   language, file route without default, each `with…` method passes its value (CSP header, no session, login
   redirect), each startup check with its message. `README.md`: the three-line `index.php`.
2. **Missing `.env.php` message** in `Core::loadEnvironmentFile()`: `file_exists()` check before `require_once`,
   message with the path and `.env.example.php`. Test not possible (global, once per process): manual check in the
   example app.
3. **Entry points use `App`**: `index.example.php`, `example/public/index.php`, `docs/setup.md` (section "Application
   entry point": `App` first, `Core` for special cases). Skeleton `../yuf-skeleton/public/index.php` with the release
   (versioning.md, section 1).
4. **Migration guide** `docs/upgrade/from-framework.md`, linked from `README.md` and `docs/upgrade/v0.md`. One table
   per topic, old → new, each row one search term in the old project:
   - Setup: Composer instead of `require_once 'framework/Core.php'`, `composer.json` with
     `"autoload": {"psr-4": {"app\\": "app/"}}` (or the old namespace plus `viewClassPrefix:` for a first
     step), `.env.php` from `.env.example.php`, directory layout (`app/` outside the document root, `vendor/`).
   - Front controller: every argument of the old `index.php` (`EnvironmentSettingsModel`, `availableLanguages`,
     `copyrightYear`, `robots`, `cspPolicySettingsModel`, `Logger`, `Route`, `individualExceptionHandler`) → `App`
     method or `.env.php` key.
   - Settings classes with constants (`EnvSettings::DEBUG`) → `.env.php` keys and
     `$app->core->environmentSettings->getString()`, passed to own settings classes.
   - Views: renamed classes and methods with the search term per row, collected from `docs/upgrade/v0.md` … `v4.md`
     and `UPGRADE.md` (`getPath()`, `BaseView` constructor with `ViewContext`, `FrameworkDB`, forms), each with a link
     to the detailed entry instead of a copy.
   - Order of work: front controller and "Hello World" route first, then one view group at a time.
   - A complete before/after of a typical old front controller (the example in this plan, without project names).
   - Public repository: generic examples only. No project, customer or domain names, no real paths (admin or login
     paths, directory layouts of live sites), no mail addresses, and no statements about security weaknesses of old
     versions or about which sites still run them. Describe what changed, not what was unsafe before.
5. **QA**: `composer check` green; the example app (https://yuf.ddev.site/) runs with the new `index.php`; a dry run of
   the guide on an old project by a developer who did not write it, with the open questions added to the guide.
   `UPGRADE.md`: section of the minor release (new `App`, new message), no breaking change.

Steps 1 and 4 touch disjoint files and can run in parallel; 2 and 3 after 1.

## Handover notes

### Step 1 (done, 2026-10-27)

- `src/App.php`, `tests/Unit/AppTest.php` (23 tests), `README.md` "Usage" shows `App`.
- Deviations from the design:
  - Tests use `App::fromCore(core:, viewClassPrefix:)`, the constructor is private: PHPStan 2.3.1 does not
    understand `clone($this, [...])` on readonly properties that the constructor sets to `[]` (it narrows them to
    `array{}`), so all state is promoted constructor parameters, hidden behind the factories.
  - `withHtmlRoute()`/`withFileRoute()` take `languageCode:` (a string, no `Language` object needed).
  - `withLanguage()` after a route throws ("languages before routes"): the routes take the first language when they
    are added, so a later language would silently change nothing.
  - `withLoginPath()` checks the path at once (not only in `prepare()`).
  - `getLanguage(code:)` returns the `Language` object for an own `Route` of `withRoute()`; `withRoute()` requires
    that very object (`RequestHandler::getLanguageRoot()` compares by identity).
- Not done: steps 2–5. `UPGRADE.md` entry comes with the release (step 5).

### Steps 2 and 3 (done, 2026-10-27)

- `Core::loadEnvironmentFile()` throws `UnexpectedValueException` for a missing file, with the path and a pointer to
  `.env.example.php` and `docs/setup.md` (checked manually in DDEV with `App::fromEnvironment()`).
- `index.example.php`: the three-line form with comments. `example/public/index.php`: `App` with `ViewMap`; checked at
  https://yuf.ddev.site/ (`/` and `/index.html` 200, `/index.txt` 404: HTML routes accept `.html` only now).
- `docs/setup.md`: "Application entry point" explains `App` first, `Core` for special cases; "Error log" names
  `withLogger()`. Other docs still show `Core::prepareHttpResponse()` arguments; `setup.md` maps them to the `with…`
  methods.
- Skeleton not changed yet: it requires the released yuf (v7.0.0 today) and `App` does not exist there. With the
  release, `../yuf-skeleton/public/index.php` becomes:

  ```php
  App::fromEnvironment(envFilePath: __DIR__ . '/../.env.php', copyrightYear: 2026)
      ->withLanguage(code: 'en', locale: 'en_US.UTF-8')
      // Explicit ViewMap: maps the file name "index" to app\view\frontend\IndexView (any class name, constructor
      // dependencies possible)
      ->withHtmlRoute(
          path: '/',
          viewGroup: 'frontend',
          viewFactory: new ViewMap()->add(
              fileTitle: 'index',
              create: fn(ViewContext $context): BaseView => new IndexView(context: $context),
          ),
      )
      // Automatic detection by file name (no viewFactory, nothing registered): /auto/<name>.html uses the class
      // app\view\auto\php\<name> if it exists, otherwise html/<name>.html is rendered without view
      ->withHtmlRoute(path: '/auto/', viewGroup: 'auto', defaultFileTitle: 'welcome')
      // No PHP session needed for this example
      ->withoutSession()
      ->run();
  ```

  Imports: `actra\yuf\App`, `actra\yuf\core\BaseView`, `actra\yuf\core\ViewContext`, `actra\yuf\core\ViewMap`,
  `app\view\frontend\IndexView`. Behaviour change against today's skeleton: both routes accept `.html` only.
- Frontend review: no templates or CSS changed.
- Next: step 4 (migration guide), then step 5 (QA, `UPGRADE.md`, release).

### Follow-ups of steps 1–3 (2026-10-29)

- `withHtmlRoute(fileExtension: HtmlFileExtensionEnum::NONE)`: paths without extension (`/about`), e.g. to keep the
  URLs of a site taken over from another system; then `.html` is a 404 (one form per route, `.html` stays the
  default). `defaultFileName:` became `defaultFileTitle:` (default `index`), the extension follows `fileExtension`.
- New opt-in `Route::$redirectDefaultFileName` (`RequestHandler`): a request of the default file name is redirected
  with 301 to the route path, with the query (`/home.html` → `/`): one URL per page, no redirect in a view.
  `withHtmlRoute()` sets it by default (`redirectDefaultFileName: false` to switch it off). Not for routes with
  `${…}` in the path or `forceFileName`. Documented in `docs/views.md` ("Default file name") and `docs/setup.md`.
- Hyphens stay path variables in every route (`/about-us` is `about` with the variable `us`), documented in
  `docs/setup.md`. A per-route option for hyphenated file titles is not planned; add it only if a migration needs it.
- `UPGRADE.md` (step 5): new `Route` argument `redirectDefaultFileName` (non-breaking, default `false`).
- Example app: `/index.html` is now a 301 to `/`.

### Step 4 (done, 2026-10-29)

- `docs/upgrade/from-framework.md`: starting point is the last state of https://github.com/Actra-AG/framework
  (`main`); the three old front controllers (`Core::init()`, `new Core(…)`, `prepareHttpResponse(
  environmentSettingsModel:)`) with a before/after to `App`; order of work; one table per topic (old → new, search
  term, link to the detailed entry of `v0.md` … `v4.md`, `UPGRADE.md` or the docs); ⚠️ for silent changes; checklist
  "Before going live". All 125 relative links and anchors checked with a script (GitHub slug rules).
- Linked from `README.md` ("Upgrading") and `docs/upgrade/v0.md`.
- Built from two read-only reviews: all version notes, and the old framework compared with `src/` (plus the
  framework's README and UPGRADE.md; its UPGRADE.md lists the HTML/CSS changes of 2025 for projects on older
  copies). Generic examples only, no project names.
- Findings that are in the guide and not in the version notes: the namespace `framework\` → `actra\yuf\`; the old
  `Route` defaulted `acceptedExtension` to `html`, the current one to `null`; `de_CH.UTF-8` as locale must not be
  passed to `DbSettings(timeNamesLanguage:)`.
- Docs only: no release needed. Next: step 5 is done except the dry run of the guide on an old project; then the
  skeleton, based on the guide.


### Closed (2026-10-29)

- Released: yuf v8.2.0 (`App`, `HtmlFileExtensionEnum`, `Route::$redirectDefaultFileName`), v8.3.0 (migration guide,
  `RecordingResponseSender`); skeleton v1.18.0 (`App`, `TestApplication`), v1.18.1 (`deploy.json` step).
- Open: the dry run of `docs/upgrade/from-framework.md` on an old project is tracked outside this repository; add its
  open questions to the guide.
