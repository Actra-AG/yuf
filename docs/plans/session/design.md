# Design: session object

Step 3 of [docs/plans/standard-completion/plan.md](../standard-completion/plan.md). Today the session is static state:
`AbstractSessionHandler::register()` / `enabled()` (via `$GLOBALS`) / `getSessionHandler()` / `clearUserData()`, the
static classes `AuthSession` and `CsrfToken`, `DbResultTable::saveToSession()` / `getFromSession()`, and nine files of
yuf that read or write `$_SESSION` directly (auth, CSRF, tables, filters, `SearchHelper`, upload storage, preferred
language, debug page). Per-request static registries check uniqueness (`FormNameRegistry`, the `$instances` of
`SmartTable`, `TableFilter`, `AbstractTableFilterField`, the single-instance guards of `AuthUser` and
`Authenticator`); tests reset them by reflection.

Decisions of the user: one `Session` object per request; projects must not use `$_SESSION` either (documented); forms
get a `FormContext` from the view; all static registries and guards are removed; characterization tests first, then
one release.

## 1. `Session`

- `final class Session` – the only way to read and write session data outside the session handler.
  - Values are `string|int|float|bool|array|null` (`array` of these, recursively): no objects, so no
    (un)serialization surprises. PHPDoc type alias for the value.
  - `has(string $key): bool`, `get…()` typed getters (`getString()`, `getInt()`, `getBool()`, `getArray()`, each `null`
    when missing or of another type), `set(string $key, … $value): void`, `remove(string $key): void`.
  - `getId(): string`, `regenerateId(): void` (deletes the old session, as today), `clearUserData(): void` (keeps the
    data of the session handler and the preferred language, as `AbstractSessionHandler::clearUserData()` today).
  - Backed by a `SessionStorage` interface: `NativeSessionStorage` (on `$_SESSION`, the only class besides the session
    handler that touches it) and `ArraySessionStorage` (tests, CLI). `Session` gets the storage and the session
    handler (for id and regeneration) through its constructor.
- Decision of the user: the stored keys may change where it makes the structure more consistent (e.g. all keys of
  yuf under one documented prefix and named per `naming.md`). Users are then logged out once and tables lose their
  sorting, paging and filters after the update (⚠️ in `UPGRADE.md`). Today's keys: `auth_userSession`, `csrftoken`,
  `table`, `tableFilter`, `columnFilter`, `searchHelper`, upload pointers, handler keys (`sessionCreated`,
  `trustedRemoteAddress`, `trustedUserAgent`, `lastActivity`, `preferredLanguage`).

## 2. Session handler and `Core`

- `AbstractSessionHandler` loses `register()`, `enabled()`, `getSessionHandler()`, `clearUserData()` and its static
  holder (⚠️). `Core::prepareHttpResponse()` starts the handler and creates `public readonly ?Session $session`
  (`null` when sessions are disabled with `individualSessionHandler: false`).
- Preferred language: `RequestHandler` gets the `?Session` and reads/writes it through it.
- `HttpResponse::changeCookieSameSiteToLax()` and the Microsoft login's `changeCookieSameSiteToNone()` call the
  handler that `Core` / the caller passes in.

## 3. Services on the session

- `AuthSession` becomes `final readonly class AuthSession(Session $session)` with the instance methods `logIn()`,
  `logOut()`, `isLoggedIn()`, `getAuthSessionId()` (⚠️). `Authenticator` gets it through its constructor; views get it
  through `ViewContext::$authSession` (`null` without session).
- CSRF: the static `CsrfToken` is removed (⚠️). `SessionCsrfTokenSource(Session $session)` keeps the token in the
  session (same key, same generation and `hash_equals()` validation); the field name is a constant
  (`CsrfTokenSource::FIELD_NAME = 'csrftoken'`) and rendering the hidden field is a method of the source (or of a small
  renderer). `HtmlDocument` (`csrfField`), `ExceptionHandler` and `TableFilter` get the source explicitly. Without a
  session there is no CSRF token source (behaviour: section 8).
- Tables and search: `DbResultTable`, `TableFilter`, the filter fields and `SearchHelper` get the `Session` through
  their constructor (next to the request and the template engine); `saveToSession()` / `getFromSession()` become
  instance methods of a small `TableSessionState` (or are inlined), same keys.
- `SessionFileUploadStorage` gets the `Session` instead of reading `$_SESSION`.
- `ExceptionHandler`'s debug page shows the session data through the `Session` it gets from `Core` (via
  `ExceptionHandlerContext` or the setter pattern of v4.23.0).

## 4. Forms

- New `final readonly class FormContext` with the request (`HttpRequest`) and the CSRF token source
  (`CsrfTokenSource`, `null` without session); `ViewContext::$formContext` provides it.
- `Form::__construct(FormContext $context, string $name, …)`: the context is the new required first argument (⚠️ every
  form); the argument `csrfTokenSource:` is removed (a project that needs another source builds its own
  `FormContext`).
- Decided by me (change if you disagree): with the request in the context, `Form::validate()`, `isSent()` and
  `process()` get their `input:` argument back as optional, defaulting to
  `FormInput::fromHttpRequest(httpRequest: $context->httpRequest, methodPost: $this->methodPost)` – the dependency is
  explicit through the constructor, and every view saves the repetition introduced in v4.29.0.

## 5. Static registries removed

`FormNameRegistry`, `SmartTable::$instances`, `TableFilter::$instances`, `AbstractTableFilterField::$instances`, the
single-instance guards of `AuthUser` and `Authenticator` and `AuthUser::resetInstance()` are removed (⚠️ for code that
relied on the exceptions, e.g. tests). Form names and table/filter identifiers must be unique per page (README):
they are the sent indicator and the session keys, so a duplicate shares state.

## 6. `$_SESSION` in projects

README and `UPGRADE.md`: projects use `ViewContext::$session` (or the `Session` passed to their services) instead of
`$_SESSION`, also for their own data (cart, order data, flash messages, `requestedPageAfterLogin`), with a
before/after example. yuf cannot enforce it; a PHPStan rule against `$_SESSION` could later go into
`actra/coding-standard` (`disallowedSuperGlobals`), which would enforce it in every project.

## 7. Tests and release

- Characterization tests first (no release): today's session keys and values of `AuthSession`, `CsrfToken`, table
  sorting/paging/filters, `SearchHelper`, upload storage, preferred language, `clearUserData()`, and the behaviour of
  forms and the table filter without session.
- v4.30.0 (⚠️): everything above in one release; tests use `ArraySessionStorage`, no reflection and no `reset()`
  calls anymore; `UPGRADE.md` with a static → instance table and before/after for a view, a form, a table, an own
  `AuthUser` singleton and own `$_SESSION` data.

## 8. Decisions after the characterization tests (step 1)

1. Without session (`FormContext::$csrfTokenSource === null`) a form has no CSRF field and its validation does not check
   a token; the table filter accepts its input without token (decision of the user). Reason: CSRF needs ambient
   authority (a session cookie); without session there is none to abuse. Documented in the README and `UPGRADE.md`.
2. Layout: all data of yuf under `$_SESSION['yuf']` with named sections (`handler`, `auth`, `csrf`, `tables`,
   `tableFilters`, `search`, `uploads`), camelCase keys. Project data is stored next to it through `Session::get…()` /
   `set()` and cannot collide with it; `clearUserData()` keeps only `yuf.handler` (incl. the preferred language). ⚠️
   users are logged out once, tables lose their state.
3. Fixes (each pinned by a test, listed in `UPGRADE.md`): no writes on read (defaults are returned and written only
   when they change); the preferred language is written only for routes with an explicit language;
   `AuthSession::getAuthSessionId()` throws when logged out; an empty or non-string stored CSRF token counts as missing;
   `Session::export()` for the debug page; upload pointers live in `yuf.uploads` and cannot collide with other data.
   Table and `SearchHelper` behaviour otherwise as today (sorting keeps the page, value shapes unchanged).

