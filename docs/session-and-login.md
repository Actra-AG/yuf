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
- `getStringList()` returns `?list<string>` (a list of strings only) and `getStringMap()` `?array<string, string>`
  (string keys and values only). `getStruct(key:, map:)` gives the stored array to a mapper and returns its result,
  `null` if the key is missing or no array. The mapper narrows the array and returns `null` for an invalid shape:

```php
final readonly class Cart
{
    public function __construct(public int $items) {}

    /** @param array<array-key, mixed> $data */
    public static function fromSessionArray(array $data): ?Cart
    {
        return is_int(value: $data['items'] ?? null) ? new Cart(items: $data['items']) : null;
    }
}

$cart = $session->getStruct(key: 'cart', map: Cart::fromSessionArray(...)); // ?Cart
```
- `getId()`, `regenerateId()`, `close()` and `export()` (all data, for the debug page) complete the API.
- The key `yuf` is reserved: all data of yuf lives below `$_SESSION['yuf']` in the sections of `SessionSectionEnum`
  (`handler`, `auth`, `csrf`, `tables`, `tableFilters`, `search`, `uploads`, `uploadPointers`). Own data is stored next
  to it.

### Lifecycle

- The PHP session starts lazily, on the first read or write (also through CSRF protection or `AuthSession`). A request
  that never uses it takes no lock, sends no cookie and creates no session file.
- A route with a language remembers it as preferred language only if the view used the session anyway (login, form
  with CSRF protection): the language alone never starts a session or takes its lock. `/` reads it only from an
  existing session, else it uses the browser language. A route with `usesRequestLanguage` reads the language of an
  existing session and never writes it; `LanguageSwitch` does ([setup.md](setup.md#languages)).
- `prepareHttpResponse()` writes and closes a started session after the view, so parallel requests of the user do not
  wait for each other. Afterwards every write (`set()`, `remove()`, `regenerateId()`, `AuthSession::logIn()`, a new
  CSRF token, …) and a first access throw a `LogicException`: write the session while the view runs, not in a
  destructor or shutdown function.
- A long-running view (export, report) calls `$this->context->session?->close()` after its last write.

### Garbage collection and save path

`SessionSettings` collects expired sessions with a chance of `gcProbability / gcDivisor` per request, by default
1/1000. `FileSessionHandler` accepts the PHP form `"N;path"` and `"N;MODE;path"` as save path: the directory is the
last part, the levels `N` are the subdirectories PHP uses (they must exist; yuf creates only the directory itself).

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

- `logIn()` gives the session a new ID (session fixation) and removes the CSRF token (a new one is created when it is
  needed). Impersonation (`logIn()` with the auth session ID of another user) does the same.
- `logOut()` resets the login and calls `Session::clearUserData()`: everything except the section `handler` is
  removed (breadcrumb, table and search state, uploads, CSRF token, own data), so the next user of the browser sees
  nothing of the previous one. Data that must survive a logout (a message for the login page) is written after
  `logOut()`. Call `clearUserData()` directly to clear the session without a logout.
- The session handler only accepts session IDs it issued itself, binds a session to the address and user agent of its
  client and replaces it, empty, if one of them changes or the session is expired.

### Authenticator: checks without login, two-factor flow

A project extends `Authenticator` (`createAuthUserByUserName()`, `logAuthResult()`, `checkLoginCredentials()`).
`passwordLogin()` and the project's own methods built on `doLogin()` check and log in in one step. The parts are
available separately:

| Method | Visibility | What it does |
| --- | --- | --- |
| `precheck(userName:)` | public | May this user get a token (reset link, one-time code)? Unknown user, IP whitelist, `checkLoginCredentials()`, inactive, lock-out; no password, no counting. Logs rejections like a login, so attacks stay visible; a success is not logged. |
| `verifyPassword(userName:, inputPassword:)` | public | Every check of `passwordLogin()` and the password itself (wrong ones are counted, outdated hashes rehashed), without logging in. Logs rejections. |
| `verifyCredentials(authMethod:, userName:, password:)` | protected | The same for any method; `null` as password skips the password check, so it is not callable from a view. |
| `logInVerifiedUser(authMethod:, authUser:, userName:)` | protected | Logs the success and the user in. |

`precheck()`, `verifyPassword()` and `verifyCredentials()` return `AuthResultEnum|AuthUser`: the reason of the
rejection (`$authResult` has it too), or the user. Like a login they are not repeatable on one authenticator after a
rejection, and throw while somebody is logged in. Show the user one message for every rejection.

A login with a second factor verifies in the first request and logs in in the second. The project exposes its own
public methods; `logInVerifiedUser()` stays protected, so no view can log in a user nobody verified:

```php
final class AppAuthenticator extends Authenticator
{
    // Request 1: the password
    public function startTwoFactorLogin(string $userName, string $password): bool
    {
        $result = $this->verifyPassword(userName: $userName, inputPassword: $password);
        if ($result instanceof AuthResultEnum) {
            return false; // same message for every reason
        }
        // Remember the pending user in the session (`Session::set()`), send the code (`afterResponse()`)

        return true;
    }

    // Request 2: the code (a new authenticator; check it, then delete it, so it works once)
    public function finishTwoFactorLogin(string $userName, string $code): bool
    {
        $authUser = $this->createAuthUserByUserName(userName: $userName);
        if ($authUser === null || !$this->isCodeValid(authUser: $authUser, code: $code)) {
            return false;
        }

        return $this->logInVerifiedUser(authMethod: AuthMethodEnum::PASSWORD, authUser: $authUser, userName: $userName);
    }
}
```

`logInVerifiedUser()` checks the user again against what can change between the requests (IP whitelist,
`checkLoginCredentials()`, inactive, lock-out; a rejection is logged and returns `false`), but it cannot know whether
the password and the code were verified: call it only after your own check, with a user you loaded again from the
stored ID (never keep the object in the session). Wrong codes belong to the project: count and limit them (a failed
code should be counted like a password: `registerWrongPasswordAttempt()` before the check,
`releaseWrongPasswordAttempt()` after the right code, see "Lock-out"). `logAuthResult()` stays protected: the methods
above write the log.

### Rate limit

The constructor of `Authenticator` (and `MicrosoftAuthenticator`) has the required argument `loginRateLimit:`
(`null` as explicit opt-out):

```php
parent::__construct(
    httpRequest: $httpRequest,
    authSession: $authSession,
    maxAllowedWrongPasswordAttempts: 5,
    loginRateLimit: new LoginRateLimit(
        rateLimiter: $rateLimiter, // see rate-limiting.md
        failMode: RateLimitFailModeEnum::CLOSED,
        logger: $logger,
        // clientLimits: and accountLimits: override the defaults
    ),
);
```

- `passwordLogin()` and every method built on `doLogin()`, `verifyPassword()` / `verifyCredentials()` and
  `precheck()` count the attempt first: per client IP address (`login|ip|<ip bucket>`, default 20 per 15 minutes and
  100 per day), then per account name (`login|account|<name>`, lower case and trimmed, default 10 per 15 minutes). A
  client blocked by its own limit uses up nothing of the account limit.
- A blocked attempt is rejected before the user is loaded (no password hash, no counter in the database),
  `$authResult` is `AuthResultEnum::ERROR_RATE_LIMITED` and logged like any rejection (`logAuthResult()`, user ID
  `null`), `$retryAfterSeconds` tells when the next attempt is allowed. Unknown account names are counted like existing
  ones, so the limit does not tell which accounts exist. Show the same message as for a wrong password, or a message
  "too many attempts, try again later" that does not depend on the account.
- `logInVerifiedUser()` and `logInIdentifiedUser()` are not counted (verified in an earlier, counted step or by the
  identity provider).
- Anyone can use up the account limit of a name for the current window by trying it from many IP addresses: keep the
  account window short. The lock-out below stays the protection against slow guessing.
- `failMode: CLOSED` is recommended: if the rate limit directory is not writable, logins fail (and are logged) instead
  of losing the limit.

### Lock-out (wrong passwords)

Wrong passwords are counted per user, **in the database, before the password is checked**, so parallel requests cannot
try more passwords than allowed. The lock is permanent: a user who reached `$maxAllowedWrongPasswordAttempts` is refused
(`ERROR_OUT_TRIED`), also with the right password, until the project sets a new password and resets the counter
itself. A successful login does **not** reset the counter (the attempt of the right password is given back, so it only
counts wrong ones). The project implements two methods of `AuthUser`, each one statement:

```php
// Counts one attempt if the user is below the limit; false = limit reached, no password check
protected function dbRegisterWrongPasswordAttempt(int $maxAllowedWrongPasswordAttempts): bool
{
    // UPDATE users SET wrong_login_attempts = wrong_login_attempts + 1
    //   WHERE id = :id AND wrong_login_attempts < :max
    return $this->updateUsers(id: $this->id, max: $maxAllowedWrongPasswordAttempts) === 1; // affected rows
}

// Gives the attempt back after the right password
protected function dbReleaseWrongPasswordAttempt(): void
{
    // UPDATE users SET wrong_login_attempts = wrong_login_attempts - 1
    //   WHERE id = :id AND wrong_login_attempts > 0
}
```

Never read the counter and write it afterwards. `AuthUser::$wrongPasswordAttempts` is the value of the request (for
the lock-out check before the password); the database decides. A second factor counts the same way:
`registerWrongPasswordAttempt()` of the `Authenticator` before the check, `releaseWrongPasswordAttempt()` after the
right code. `dbConfirmSuccessfulLogin()` must not reset the counter either; the project's "set password" code does
(`wrong_login_attempts = 0`).

### Login with a Microsoft account

`MicrosoftAuthenticator` (OpenID Connect, ID token by form post) identifies the user by the **object ID and the tenant
ID of the token** (claims `oid` and `tid`, GUIDs), never by the email address: users and tenant administrators can
change it, and Microsoft does not verify it. The project implements the lookup and stores both IDs per user:

```php
protected function findAuthUserByMicrosoftAccount(string $objectId, string $tenantId): ?AuthUser
{
    // SELECT ... FROM users WHERE microsoft_object_id = :oid AND microsoft_tenant_id = :tid (GUIDs in lower case)
}
```

`MicrosoftIdToken::getEmail()` is available for display only. Existing users have no stored IDs: link them explicitly,
never by matching the email address automatically. Two ways: an administrator links the Microsoft account of a user
(the project shows the `oid` and `tid` of a login that failed with `ERROR_UNKNOWN_USER_NAME`, the log has the object
ID as user name), or a migration script fills the columns from the directory of the tenant (Microsoft Graph, `id` of
the user).

The nonce is handled by yuf: `redirectToMicrosoftLogin(tenantId:, clientId:, redirectUri:)` creates it (32 random
bytes), sends it to Microsoft and keeps it in its own cookie (`__Secure-yuf-sso-nonce`: `Secure`, `HttpOnly`,
`SameSite=None`, path of the redirect URI, ten minutes). `microsoftIdTokenLogin(tenantId:, clientId:, redirectUri:,
microsoftIdToken:)` (the same `redirectUri`) compares it with the token, and deletes the cookie in every case: a nonce
is used once, a token without the cookie of the same browser fails. The project stores no nonce, and the session
cookie keeps `Strict`.

### Users without password

`AuthUser::$password` is `?Password`; `null` is a user without password (identity provider, one-time code, web token).
There is no right for the password login: it is allowed exactly when the user has a password. A password login of a
user without password gives `ERROR_NO_PASSWORD_LOGIN_ACTIVE`, is not counted as wrong attempt and rehashes nothing; it
costs the time of a verification (`Password::spendVerificationTime()`), so the answer time does not tell which users
have no password. All other login methods (`doLogin()` with `null`, `verifyCredentials()`, `precheck()`,
`logInVerifiedUser()`) work for these users. To block the password login of a user, set the password to `null`.

### Texts of the login results

`AuthResultEnum::label(messages: new AuthResultMessages())` returns the text of a result as `HtmlText` (plain text,
escaped), e.g. for a list of login attempts. The defaults are German, `AuthResultMessages::english()` has English
texts, and an own instance (named arguments, one per result) gives any language.

## Passwords and secret tokens

`Password::generateNew()` hashes with `password_hash()` (Argon2id; `PASSWORD_DEFAULT` without Argon2): store `salt`
(empty) and `hash` (at least 255 characters). Passwords of the earlier salt-and-SHA-256 format are still verified; the
`Authenticator` rehashes them (and hashes with weaker costs) after a successful login through
`AuthUser::rehashPassword()` / `dbUpdatePassword()`. A legacy hash takes the time of a verification as well, and a login
for an unknown user hashes with the same algorithm (`Password::spendVerificationTime()`), so the answer time tells
neither. Show one message for every failed login, whether the user name is
unknown or the password wrong (`Authenticator::$authResult` is for the log, not for the user).

`Password` is for passwords that humans choose. For random secrets the application generates (API keys, reset links,
remember-me tokens) use `SecretTokenHash`:

```php
$token = SecretTokenHash::generate();                        // $token->secret: show once
$stored = $token->hash->hash;                                // store this
new SecretTokenHash(hash: $stored)->isValid(secret: $given); // hash_equals()
SecretTokenHash::tryFrom(hash: $stored);                     // ?SecretTokenHash, null for another format
```

The secret has 32 random bytes (base64url), the hash is SHA-256 (64 hex characters). A fast hash is safe for a 256 bit
secret and avoids an Argon2id check (50 ms, 64 MB) on every API request. Never use it for something a human types.
