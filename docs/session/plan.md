# Plan: session object

Design: [design.md](design.md). Step 3 of [docs/standard-completion/plan.md](../standard-completion/plan.md).

## Steps

1. **Characterization tests (no release):** session keys and values written and read by `AuthSession`, `CsrfToken`
   (generation, renewal, validation, rendering, disabled sessions), `DbResultTable` sorting/paging, `TableFilter` and
   the filter fields, `SearchHelper`, `SessionFileUploadStorage`, the preferred language, `clearUserData()`; forms and
   the table filter without session.
2. **v4.30.0 – session object (⚠️):** design sections 1–7 in one release.

## Follow-up in other projects

- `AuthSession::…()` → `$this->context->authSession->…()`; own `AuthUser` singletons get the `AuthSession` / `Session`
  passed in.
- Forms: `new MyForm(context: $this->context->formContext, …)`.
- Own `$_SESSION` data → `$this->context->session` (`get…()` / `set()` / `remove()`).
- Tables: pass the session.

## Handover notes

### Step 1 – done

Tests only, no change in `src/`, no release. `composer check` is green (3105 tests, 139 of them new).

**New test classes** (each with the PHPDoc "Characterization of the session behaviour before the redesign"; behaviour
tests use helpers like `seedX()` / `storedX()` / `request()` and the public API, the keys and value shapes are pinned in
`testStorageLayout…()` tests, so step 2 can change the keys by editing those tests and the helpers):

| Class | Tests | Area |
| --- | --- | --- |
| `tests/Unit/security/CsrfTokenTest.php` | 19 | token format (44 characters, base64 of 32 bytes), same token per session, `forceNew`, validation, hidden field, field name, cleared with the user data, without session |
| `tests/Unit/form/component/collection/FormSessionCsrfTest.php` | 13 | `Form` with the default `SessionCsrfTokenSource`: accepted/rejected tokens, rendering, token not renewed, when the session is (not) touched, GET form, removed protection, without session |
| `tests/Unit/table/DbResultTableSessionTest.php` | 31 | sorting and page remembered over requests, invalid input, `reset`/`find`, per table, cleared with the user data, static `saveToSession()`/`getFromSession()`, without session, duplicate identifier |
| `tests/Unit/table/filter/TableFilterSessionTest.php` | 26 | `TableFilter` with text, options and date field: remembered values, submit/reset, page, CSRF requirement, rendering of the CSRF field, without session, duplicate identifiers, protected `TableFilter::getFromSession()` |
| `tests/Unit/common/SearchHelperSessionTest.php` | 18 | `checkString`/`checkFilter`/`checkMultiFilter`/`checkDateRangeFilter`: remembered values, `find`/`reset`, per instance, cleared, without session |
| `tests/Unit/core/RequestHandlerPreferredLanguageTest.php` | 13 | preferred language written by `resolveRoute()`, read/written by the session handler, kept by `clearUserData()` |

**Added to existing classes** (19 tests): `AuthSessionTest` (+9: legacy/odd states, key written on read, second login,
double logout, without session), `AuthenticatorTest` (+6: login written to the session, already logged in, login twice,
second `Authenticator`, second `AuthUser`), `AbstractSessionHandlerTest` (+4: `clearUserData()` with the state
of tables/filters/search/uploads, only existing handler keys, layout of a new session and of an existing session of the
same client, both in a separate process), `FormNameRegistryTest` (class doc only).

**New test doubles** (`tests/Double/`): `session/NonStartingSessionHandler` (registers a stand-in in the static handler
holder through reflection), `table/StaticTableRegistries` (empties the `$instances` of `SmartTable`, `TableFilter` and
`AbstractTableFilterField` through reflection, needed to simulate a second request with the same identifiers),
`table/ExposingTableFilter` (calls the protected session methods of `TableFilter`).

**Reflection to remove in step 2:** `StaticTableRegistries`, `NonStartingSessionHandler`, the handler reset in
`AuthSessionTest` and `AuthenticatorTest`, `Authenticator::$instance` in `AuthenticatorTest`, `TestAuthUser::release()`,
the `FormNameRegistry::reset()` calls (many test classes). Tests marked "removed in step 2": the duplicate-identifier
tests (`DbResultTableSessionTest`, `TableFilterSessionTest`, `FormNameRegistryTest`, `FormValidateTest`) and the
second-instance tests of `AuthenticatorTest`.

**Already covered before step 1** (reused, not duplicated): `AuthSessionTest` (login, logout, `clearUserData`
effect, ID regeneration, legacy session), `SessionCsrfTokenSourceTest`, `FormCsrfTest` and `CsrfTokenFieldValueTest`
(with `InMemoryCsrfTokenSource`), `TableFilterCsrfTest` (token needed, POST only), `DbResultTableRequestTest` (input
sources, default sorting, `find`/`reset`), `SearchHelperRequestTest` (basics, `find`/`reset`, date range),
`SessionFileUploadStorageTest` (complete: save/load/clear by pointer, broken entries, expiry – nothing added),
`AbstractSessionHandlerTest` (`clearUserData()` basics, session fixation).

**Not covered** (cannot be tested without `exit` or `Core`): the redirect of `/` to the route of the preferred language
(`RequestHandler`), the session dump and the `csrfField` of the debug page (`ExceptionHandler`, `sendHttpResponseAndExit()`
is final and exits), the `csrfField` of `HtmlDocument`, a successful `SessionFileUploadStorage::store()`. Check the
example app in the browser after step 2 (login, form, table with filter).

**Today's storage layout** (everything yuf writes into `$_SESSION`; all top-level keys):

| Key | Value shape | Written by |
| --- | --- | --- |
| `sessionCreated` | `int` (timestamp) | session handler (new session, `regenerateId()`) |
| `trustedRemoteAddress` | `string` | session handler |
| `trustedUserAgent` | `string` | session handler |
| `lastActivity` | `int` (timestamp) | session handler (every request) |
| `preferredLanguage` | `string` (language code) | session handler, called by `RequestHandler::resolveRoute()` |
| `auth_userSession` | `['isLoggedIn' => bool, 'authSessionId' => int]` (`authSessionId` missing until the first login; after logout `false` and `0`) | `AuthSession` |
| `csrftoken` | `string` (base64, 44 characters) | `CsrfToken` |
| `table` | `[tableIdentifier => ['sort_column' => string, 'sort_direction' => 'ASC'/'DESC', 'pagination_page' => string]]` | `DbResultTable` (also `TableFilter::reset()` through `setCurrentPaginationPage()`) |
| `columnFilter` | `[tableFilterIdentifier . '_' . fieldIdentifier => [sameIdentifier => string]]` (text: the text; options: the option identifier; date: `'Y-m-d H:i:s'` or `''`) | `TextFilterField`, `OptionsFilterField`, `DateFilterField` |
| `tableFilter` | `[filterIdentifier => [index => string]]` | only the protected `TableFilter::getFromSession()`/`saveToSession()` for own filters; yuf's own fields do not use it |
| `searchHelper` | `[instanceName => [field => string / list<int|string> / 'd.m.Y' string]]` | `SearchHelper` |
| `<pointer>` | `list<['name' => string, 'type' => string, 'size' => int, 'path' => string]>` | `SessionFileUploadStorage` (top-level, the pointer is chosen by the form) |

`clearUserData()` keeps exactly `sessionCreated`, `trustedRemoteAddress`, `trustedUserAgent`, `lastActivity` and
`preferredLanguage`. Own project data (e.g. `sess_breadcrumb`, `requestedPageAfterLogin`) is removed too.

**Findings that need a decision for step 2** (nothing was fixed):

1. **Forms without session (differs from design.md section 3/4).** Today `Form` always adds a `CsrfTokenField` with the
   `SessionCsrfTokenSource`; the field does not check whether sessions are enabled. Without session the field renders
   a token (it creates `$_SESSION`) and every posted token is rejected, so a POST form cannot be submitted unless
   `removeCsrfProtection()` is called. design.md says "without session there is no CSRF token source … the behaviour
   is kept", but kept would mean always-failing validation. Decide what `Form` does with `FormContext::$csrfTokenSource
   === null`: no CSRF field and validation passes (pinned today: only with `removeCsrfProtection()`), always fail
   (today), or throw at construction. A silent pass without session is a security downgrade only if someone relies on
   the failure.
2. **`TableFilter` without session:** input is never accepted (the token check cannot succeed), so a filter cannot be
   used without session. Same decision as above for `TableFilter` without a `CsrfTokenSource`.
3. **`enabled()` flips during the request.** `AbstractSessionHandler::enabled()` tests `$GLOBALS['_SESSION']`.
   Without session, any write (`CsrfToken::getToken()`, `AuthSession`, `DbResultTable`, the `init()` of every filter
   field, `SearchHelper`) creates the array, so `enabled()` becomes true mid-request. Result: `CsrfToken::renderAsHiddenPostField()`
   (used by `HtmlDocument`, `ExceptionHandler`, `TableFilter`) renders `''` before the first write and a token field
   that can never be accepted after it. With `?Session === null` this inconsistency disappears; decide that on
   purpose (the output of such pages changes).
4. **Keys are written on read:** `AuthSession::isLoggedIn()` writes `isLoggedIn = false`; `CsrfToken::getToken()` and
   `validateToken()` create the token (validating without token in the session creates one); `DbResultTable::getFromSession()`
   writes an empty array; every filter field writes `columnFilter[id] = []` when it is added; the table writes the
   default sorting and page on its first request; `SearchHelper::check…()` writes the default. `Session::get…()` must
   not write; the new code should decide where these defaults are written (or whether they are needed at all).
5. **Upload pointers are top-level keys** and the pointer regex allows `table`, `csrftoken`, `searchHelper`,
   `auth_userSession`, `columnFilter`, …: a pointer with such a name overwrites yuf data (and `clear()` deletes it).
   A common prefix (decided in design.md section 1) fixes this; pointers should not be able to collide with it.
6. **Preferred language:** a route without language falls back to the first available language, which then
   overwrites the user's preferred language (`testRouteWithoutLanguageWritesTheFirstAvailableLanguage`). Probably
   unintended.
7. **`AuthSession::getAuthSessionId()` of a logged-out session** returns a stored ID (`isLoggedIn = false`,
   `authSessionId = 5` → `5`; after a normal logout it is `0`). `isLoggedIn()` also clears the whole user data and
   regenerates the session ID when it finds a legacy state (`isLoggedIn = true` without int ID).
8. **Tables:** `DbResultTable::getCurrentSortDirection()` before `fillBySelectQuery()` throws a `TypeError` (returns
   `null` from a `string` method); changing the sorting keeps the page; the form action of `TableFilter`
   (`?<id>&find`) goes back to page 1 even if the CSRF token was wrong and the input was ignored; submitting the filter
   resets all fields first, so fields missing in the POST data become empty and an unknown option value resets the
   options field to its default; the page is stored as string, sort and page of two tables with the same identifier
   share state (identifiers are the session keys, see section 5 of design.md).
9. **`SearchHelper`:** `checkMultiFilter()` stores the keys of the option array as given (`int` for numeric keys) but
   the `…ID` value as string (`[1, '7']`); `reset`/`find` only reset the fields that are checked in that request; empty
   input replaces a remembered value by `''`; `checkDateRangeFilter()` stores `d.m.Y`, `DateFilterField` stores
   `Y-m-d H:i:s`. Not necessarily to change, but the value shapes of `Session` must hold all of them (`list<int|string>`
   included).
10. **CSRF with an empty stored token:** `CsrfToken::getToken()` uses `isset()`, so a stored `''` is kept and the
    empty posted token would then be valid. Not reachable today (the token is always generated); not pinned. The new
    `SessionCsrfTokenSource` should treat a non-string or empty stored token as missing.
11. **Debug page** dumps the raw `$_SESSION` including the CSRF token and the login state (only in debug mode, by
    design); the `Session` needs a way to export all data for it.
