# Session and login

## Session

`Core::prepareHttpResponse()` creates the session handler and one `Session` per request: `$core->session` and
`$this->context->session` in a view (`null` with `individualSessionHandler: false`). The `Session` is the only way to
read and write session data: **projects must not use `$_SESSION`**, also not for their own data (cart, flash
messages, `requestedPageAfterLogin`). Only `NativeSessionStorage` and the session handler touch `$_SESSION`.

```php
$session = $this->context->session;                 // ?Session
$session?->set(key: 'cart', value: ['items' => 3]); // string|int|float|bool|array|null, arrays recursively
$items = $session?->getArray(key: 'cart');          // getString(), getInt(), getFloat(), getBool()
$session?->has(key: 'cart');
$session?->remove(key: 'cart');
```

- The typed getters return `null` for a missing key or a value of another type and never write. Objects are rejected.
- `getId()`, `regenerateId()`, `close()` and `export()` (all data, for the debug page) complete the API.
- The key `yuf` is reserved: all data of yuf lives below `$_SESSION['yuf']` in the sections of `SessionSectionEnum`
  (`handler`, `auth`, `csrf`, `tables`, `tableFilters`, `search`, `uploads`). Own data is stored next to it.

### Lifecycle

- The PHP session starts lazily, on the first read or write (also through CSRF protection or `AuthSession`). A request
  that never uses it takes no lock, sends no cookie and creates no session file.
- A route with a language remembers it as preferred language only if the visitor has a session
  (`Session::isActive()`); `/` reads it only from such a session, else it uses the browser language.
- `prepareHttpResponse()` writes and closes a started session after the view, so parallel requests of the user do not
  wait for each other. Afterwards every write (`set()`, `remove()`, `regenerateId()`, `AuthSession::logIn()`, a new
  CSRF token, …) and a first access throw a `LogicException`: write the session while the view runs, not in a
  destructor or shutdown function.
- A long-running view (export, report) calls `$this->context->session?->close()` after its last write.

### Unique identifiers

Identifiers are session keys: **form names, table identifiers, filter identifiers and the instance names of
`SearchState` must be unique per page.** Two tables with the same identifier share sorting and page, two forms with the
same name share the sent indicator. yuf does not check this.

### Without a session

Forms and table filters work without CSRF protection then: without a session cookie there is nothing to abuse. The
`FormContext` has no `CsrfTokenSource`, so a form has no CSRF field. Tables, `SearchState` and upload storage need a
`Session`: build one on an `ArraySessionStorage`, the state then lives for one request.

## Login

`AuthSession` (`$this->context->authSession`) holds the login: `logIn()`, `logOut()`, `isLoggedIn()` and
`getAuthSessionId()` (throws a `LogicException` when nobody is logged in).

- `logIn()` gives the session a new ID (session fixation).
- `logOut()` resets the login and calls `Session::clearUserData()`: everything except the section `handler` is
  removed (breadcrumb, table and search state, uploads, CSRF token, own data), so the next user of the browser sees
  nothing of the previous one. Data that must survive a logout (a message for the login page) is written after
  `logOut()`. Call `clearUserData()` directly to clear the session without a logout.
- The session handler only accepts session IDs it issued itself, binds a session to the address and user agent of its
  client and replaces it, empty, if one of them changes or the session is expired.

## Passwords and secret tokens

`Password::generateNew()` hashes with `password_hash()` (Argon2id; `PASSWORD_DEFAULT` without Argon2): store `salt`
(empty) and `hash` (at least 255 characters). Passwords of the earlier salt-and-SHA-256 format are still verified; the
`Authenticator` rehashes them (and hashes with weaker costs) after a successful login through
`AuthUser::rehashPassword()` / `dbUpdatePassword()`. Show one message for every failed login, whether the user name is
unknown or the password wrong (`Authenticator::$authResult` is for the log, not for the user).

`Password` is for passwords that humans choose. For random secrets the application generates (API keys, reset links,
remember-me tokens) use `SecretTokenHash`:

```php
$token = SecretTokenHash::generate();                        // $token->secret: show once
$stored = $token->hash->hash;                                // store this
new SecretTokenHash(hash: $stored)->isValid(secret: $given); // hash_equals()
```

The secret has 32 random bytes (base64url), the hash is SHA-256 (64 hex characters). A fast hash is safe for a 256 bit
secret and avoids an Argon2id check (50 ms, 64 MB) on every API request. Never use it for something a human types.
