# Forms security: rate limits for public forms and logins, upload hardening

Status: done (2026-10-23), see "Handover notes" for the deviations from the proposal. Target release:
**v7.0.0** (breaking, see "Versioning").

## 1. Behaviour today

### Spam protection (`form\spam`)

- `Form::addSpamProtection(SpamProtection)` adds a honeypot `TextField` and a `SpamRenderTimeField` (render time,
  HMAC-SHA256 over form name, timestamp and the CSRF token as session binding).
- `Form::validate()`: sent indicator → CSRF check (invalid: one form error, nothing else read) → all fields
  (`FormField::validate()`: read input, listeners, required, rules, child fields) → `validateSpamProtection()` (only if
  all other fields are valid: rejected e-mail address, honeypot, too fast, expired) → `globalErrorMessage`.
- No rate limit: a client can submit a public form as often as it likes; every submit runs all rules, including the DNS
  and SMTP checks of `EmailField` (`SystemMailDomainResolver`), the project's processing and its mails.
- Projects limit by hand (actra.ch: `domain_bestellen.php` consumes `'domainOrder|' . ipBucket` with its own limits
  and fails open on storage errors).

### Login (`auth\Authenticator`)

- Wrong passwords are counted per user in the database before the check (`registerWrongPasswordAttempt()`), lock-out
  is permanent until a new password is set. Unknown users and users without password spend the time of one
  verification (`Password::spendVerificationTime()`), every rejection is logged (`logAuthResult()`).
- No limit per IP address or per account name: one client can try many accounts (credential stuffing) at Argon2id
  cost per attempt (CPU DoS), and `precheck()` (token requests: reset links, one-time codes) can be flooded; unknown
  account names are not counted at all.

### Uploads (`form\upload`, `FileField`)

- Type: detected from the content (`FinfoFileTypeDetector`, `finfo` MIME type), must match an allow-list entry
  (`UploadFileType`: detected MIME type **and** extension of the client name); client MIME type ignored; no SVG
  helper. ✅
- Size: `maxFileSize` (default 10 MB) against `UploadInput::$size` (set by PHP, not the client); empty files rejected.
  Count: `maxFileUploadCount` (default 1), values < 1 are silently corrected to 1.
- Storage: `SessionFileUploadStorage`, root directory required, created 0700, files 0600, symlink and foreign owner
  refused; whether it lies inside the document root is only documented, not checked. Stored file name =
  `basename(tmp_name)` (PHP's `phpXXXXXX`, 6 random characters) plus counter, no extension, so never executable by
  name. Pointers are random (128 bit) and bound to the session (`issue()` / `isIssued()`).
- Cleanup: PHP deletes unmoved temp files at the end of the request (rejected uploads included). Stored files stay
  until `clearData()` or 2 days (`removeExpired()`, which scans the root directory on every request that reads a file
  field). `Session::clearUserData()` (logout) removes the list, not the files (they expire).
- `Form(acceptUpload: false)` only controls the `enctype` attribute. A form without file field still reads `$_FILES`
  into `FormInput`; uploads posted to it are ignored, but a `FileField` in a form with `acceptUpload: false` is
  accepted silently (it then never receives files).
- php.ini: yuf sets only session settings (`AbstractSessionHandler`); nothing about request size, multipart parts or
  upload progress. `session.upload_progress.*` is `PHP_INI_PERDIR`, yuf cannot change it at runtime.

## 2. Threats

| Threat | Today | After |
| --- | --- | --- |
| Flooding a public form (mails, DNS/SMTP checks, DB writes, third-party APIs) | spam fields only, bots that wait 3 s pass | limit per IP bucket per form, optional global limit |
| Password guessing / credential stuffing over many accounts | per-account DB lock-out only for existing users | limit per IP and per account name (existing or not) |
| CPU DoS through password hashing | none | IP limit before any hash |
| Token request flood (`precheck()`) | logged only | same limits as login |
| Upload of active content (PHP, HTML, SVG) | allow-list by content + extension ✅ | unchanged, plus random names, document-root check |
| Polyglot / metadata in images (EXIF GPS, payload after image data) | stored as uploaded | optional re-encoding |
| Upload to forms that do not expect files | ignored, but parsed and in temp files | form rejects the request (one error, nothing processed) |
| php2shell (CVE-2026-109618/109619: memory bugs in multipart and session upload progress handling) | — | not preventable in PHP code (triggers before yuf runs): update PHP (8.5.12 / 8.4.27 / 8.3.36 / 8.2.35), php.ini settings documented; yuf only limits what reaches the application |

## 3. API proposal

### 3.1 `RateLimitFailModeEnum` (`actra\yuf\security`)

```php
enum RateLimitFailModeEnum { case OPEN; case CLOSED; }
```

What happens when the `RateLimiter` throws (storage not writable) or the client IP is invalid: `CLOSED` blocks,
`OPEN` allows. Always logged (`core\Logger::logException()`).

### 3.2 Public forms: `FormRateLimit` (`actra\yuf\form\spam`)

```php
final readonly class FormRateLimit
{
    /**
     * @param non-empty-list<RateLimit> $clientLimits Per client IP bucket and form (`form|<form name>|<ipBucket>`)
     * @param list<RateLimit> $globalLimits Per form for all clients (`form|<form name>`), checked after the client
     *     limits; [] = none
     */
    public function __construct(
        public RateLimiter $rateLimiter,
        public array $clientLimits,
        public RateLimitFailModeEnum $failMode,
        public Logger $logger,
        public array $globalLimits = [],
    ) {}
}
```

`SpamProtection` gets the **required** argument `rateLimit: ?FormRateLimit` (`null` = explicit opt-out, e.g. a
public form behind another limit). Backend forms do not use `addSpamProtection()`, so they pay nothing.

Behaviour in `Form::validate()`:

1. Sent indicator, CSRF check (unchanged: a request with an invalid token is not counted, so a cross-site page cannot
   use up the budget of a visitor's IP; it reads nothing anyway).
2. **Count every submit** (also invalid ones): `consume()` client limits, then global limits.
3. Blocked: the fields only **read** their input (typed values are rendered again; no rules, listeners, DNS checks;
   file fields store no new uploads), the form gets the error `FormMessages::$rateLimited`, `validate()` returns
   `false`. `Form::$retryAfterSeconds` (`public private(set) int`, 0 if not blocked) lets the view send
   `429` + `Retry-After`.
4. Not blocked: as today.

New text: `FormMessages::$rateLimited`

- en: `Too many submissions. Please try again in a few minutes.`
- de: `Zu viele Übermittlungen. Bitte versuchen Sie es in einigen Minuten erneut.`

Needed inside the form classes: `FormField::readValue(FormInput $input)` (final public: `startReadingInput()`,
`readAdditionalInput()`, `readInput()`, plus the new hook `readChildInput()` for toggle fields), and
`FormInput::withoutUploads()`.

Recommended limits in the docs (no defaults in the code, limits are project data): 10 per 10 minutes and 50 per day
per client; global only for forms that cost money or call third parties.

### 3.3 Logins: `LoginRateLimit` (`actra\yuf\auth`)

```php
final readonly class LoginRateLimit
{
    /**
     * @param non-empty-list<RateLimit> $clientLimits Per client IP bucket (`login|ip|<ipBucket>`)
     * @param non-empty-list<RateLimit> $accountLimits Per account name, lower case and trimmed
     *     (`login|account|<name>`), counted whether the account exists or not
     */
    public function __construct(
        public RateLimiter $rateLimiter,
        public array $clientLimits,
        public array $accountLimits,
        public RateLimitFailModeEnum $failMode,
        public Logger $logger,
    ) {}
}
```

- `Authenticator::__construct()` gets the **required** argument `?LoginRateLimit $loginRateLimit` (`null` = explicit
  opt-out, e.g. tests or a login behind another limit).
- Checked first in `doLogin()` (so `passwordLogin()` and the project methods built on it), `verifyCredentials()`
  (`verifyPassword()`) and `precheck()`: client limits, then account limits. Blocked: no user lookup, no password hash,
  `AuthResultEnum::ERROR_RATE_LIMITED` (new, value 15) is set and logged (`logAuthResult()`, user ID `null`),
  `Authenticator::$retryAfterSeconds` is set. The answer is the same whether the account exists or not.
- Not checked in `logInVerifiedUser()` and `logInIdentifiedUser()` (already verified in an earlier, limited step or by
  the identity provider).
- `AuthResultMessages::$errorRateLimited` (`Zu viele Anmeldeversuche` / `Too many login attempts`).
- Trade-off (documented): anyone can block an account name for the current window by using up its account limit
  from many IPs. Keep the account window short (recommended: 10 per 15 minutes per account, 20 per 15 minutes and 100
  per day per IP); the permanent DB lock-out stays the defense against slow guessing.

### 3.4 Uploads

| Rule | Change |
| --- | --- |
| Type from content, allow-list per field | unchanged (already done), tests kept |
| Size | additionally compare `filesize(tmp_name)` with the limit (the real file, not a reported number); unreadable = technical error |
| Count | `maxFileUploadCount < 1` throws `InvalidArgumentException` instead of the silent correction |
| Storage name | `bin2hex(random_bytes(16))`, no extension, created exclusively; never derived from `tmp_name` or the client name |
| Outside the document root | `SessionFileUploadStorage` gets the **required** `documentRoot: ?string` (`Core::$documentRoot`; `null` = opt-out, e.g. CLI/tests) and refuses (`RuntimeException`) a root directory inside it (`realpath()` comparison) |
| Never executable | unchanged: 0600, no extension, directory 0700 |
| Image re-encoding | opt-in `imageReencoder: ?ImageReencoder = null` on `FileField`; `GdImageReencoder` (needs `ext-gd`, added to `suggest`): JPEG/PNG/GIF/WebP are decoded and written again (drops EXIF/GPS and appended data), with a pixel limit (`maxPixels`, checked with `getimagesize()` before decoding) against decompression bombs; a file that cannot be re-encoded is rejected (`fileTypeNotAllowed`). Opt-in because it changes files (quality, animation, colour profiles) and needs an extension yuf does not require |
| Forms without file field | `Form` with a `FileField` but `acceptUpload: false` throws a `LogicException` when the field is added. A form with `acceptUpload: false` rejects a request that has any upload entry or a `multipart/form-data` body (`FormInput::$isMultipart` from the `Content-Type` header in `fromHttpRequest()`, argument in `fromArray()`): error `FormMessages::$invalidInput`, nothing else read (like an invalid CSRF token). Checked after CSRF and the rate limit |
| Temp files | unchanged (PHP deletes them); docs: call `clearData()` after processing |

### 3.5 php.ini (docs/setup.md, new section "Request and upload limits")

- Update PHP first: php2shell is fixed in 8.5.12 / 8.4.27 / 8.3.36 / 8.2.35 and triggers before any PHP code, so no
  yuf setting protects an unpatched PHP.
- `session.upload_progress.enabled = 0` (yuf does not use it; `PHP_INI_PERDIR`: php.ini, pool config or `.user.ini`)
- `file_uploads = 0` for pools/sites without uploads
- `max_file_uploads` = the largest `maxFileUploadCount` of the site (default 20)
- `upload_max_filesize` ≥ the largest `maxFileSize`, `post_max_size` slightly above `max_file_uploads ×
  upload_max_filesize`, as small as possible
- `max_input_vars` (default 1000, enough for most forms), `max_multipart_body_parts` (default -1 =
  `max_input_vars + max_file_uploads`; set explicitly)
- Web server: request body limit (`LimitRequestBody`, `client_max_body_size`) matching `post_max_size`.

## 4. Steps

1. `RateLimitFailModeEnum`, `FormRateLimit`, `SpamProtection(rateLimit:)`, `Form::validate()` with counting,
   `FormField::readValue()` / `readChildInput()`, `FormInput::withoutUploads()`, `FormMessages::$rateLimited`,
   `Form::$retryAfterSeconds`. Tests: counted on valid and invalid submits, not counted on invalid CSRF, block keeps
   typed values (also toggle children), no rules/listeners and no upload stored on block, fail open/closed with a
   failing storage, global limit after client limit, opt-out with `null`, German/English text.
2. `LoginRateLimit`, `Authenticator(loginRateLimit:)`, `AuthResultEnum::ERROR_RATE_LIMITED`,
   `AuthResultMessages::$errorRateLimited`. Tests: blocked before user lookup (no `createAuthUserByUserName()` call),
   unknown and known account counted the same, case-insensitive account key, IP before account, logged, fail modes,
   `precheck()` / `verifyPassword()` limited, `logInVerifiedUser()` not.
3. Uploads: random names, `filesize()` check, count exception, `documentRoot:`, `acceptUpload` rules and multipart
   rejection. Tests for each (pure parts unit-tested; `store()` stays untestable as today).
4. `ImageReencoder` / `GdImageReencoder` (if confirmed). Tests with generated images (skipped without `ext-gd`).
5. Docs: `docs/forms.md` (rate limit, uploads), `docs/rate-limiting.md` (forms, logins), `docs/session-and-login.md`
   (login limits), `docs/setup.md` (php.ini, php2shell), `README.md` features line; example app adapted; `UPGRADE.md`;
   `ddev composer check`.

## 5. Versioning

Breaking → **v7.0.0**. Draft `UPGRADE.md` section:

```markdown
## v7.0.0 (2026-10-xx)

Public forms and logins are rate limited, uploads are hardened ([docs/forms.md](docs/forms.md),
[docs/rate-limiting.md](docs/rate-limiting.md), [docs/setup.md](docs/setup.md#request-and-upload-limits)).

### ⚠️ `SpamProtection`: required `rateLimit:`

- Every public form counts its submits per client IP (also invalid ones) and is blocked with
  `FormMessages::$rateLimited` when a limit is reached; the typed values stay. Before: `new SpamProtection(clock:, secret:,
  emailField:, rejectedEmailAddresses:)`, after: add `rateLimit: new FormRateLimit(rateLimiter:, clientLimits: [...],
  failMode: RateLimitFailModeEnum::CLOSED, logger:)`, or `rateLimit: null` as explicit opt-out.
- Remove own `RateLimiter` calls for such forms. Send `429` with `Retry-After: $form->retryAfterSeconds` if wanted.

### ⚠️ `Authenticator`: required `loginRateLimit:`

- `parent::__construct(httpRequest:, authSession:, maxAllowedWrongPasswordAttempts:, loginRateLimit: new
  LoginRateLimit(...))` or `loginRateLimit: null`. Logins, `verifyPassword()` and `precheck()` are limited per IP and
  per account name; blocked attempts are logged as `AuthResultEnum::ERROR_RATE_LIMITED`.
- New enum case: add `ERROR_RATE_LIMITED` to exhaustive `match` expressions over `AuthResultEnum`.

### ⚠️ Uploads

- `SessionFileUploadStorage` needs `documentRoot:` (`$core->documentRoot`, `null` only without document root); a root
  directory inside the document root throws a `RuntimeException`.
- A `FileField` in a form without `acceptUpload: true` throws a `LogicException`; a form without `acceptUpload`
  rejects requests with uploads or a multipart body (`FormMessages::$invalidInput`).
- `FileField(maxFileUploadCount: 0)` throws an `InvalidArgumentException` (was silently 1).
- Stored files get random names (files stored before the update keep working until they expire).
- New, opt-in: `FileField(imageReencoder: new GdImageReencoder(maxPixels: ...))`.
- Set the php.ini values of docs/setup.md and update PHP (php2shell).
```

Skeleton (`../yuf-skeleton`): needs an update (spam protection and authenticator arguments, `documentRoot:`, php.ini
notes).

## 6. Decisions

1. Image re-encoding: opt-in (confirmed).
2. Limits: defaults in the code, overridable by the project (user decision; changes 3.2 and 3.3).
3. Rate limit after the CSRF check (confirmed): forged cross-site submits must not use up the budget of the visitors'
   IP addresses; requests with an invalid token read nothing anyway.

## Handover notes

### 2026-10-23: implementation

- Deviations from section 3: the limits are default arguments (`FormRateLimit`: 10/10 min + 50/day per IP;
  `LoginRateLimit`: 20/15 min + 100/day per IP, 10/15 min per account), so the argument order is
  `rateLimiter:, failMode:, logger:, clientLimits:, …`. Shared counting and fail mode: `security\RateLimitGate`
  (`@internal`); fail closed blocks with `retryAfterSeconds` 60. An invalid client IP address uses the fail mode too.
- The real-size check (`filesize()` = reported size) lives in `SessionFileUploadStorage::store()` (the only class with
  the real file), not in `FileField`. Re-encoding runs on the stored file after `store()` (`FileField::reencodeImage()`),
  so the size check sees the uploaded file.
- `FileField::$maxFileUploadCount` is `readonly` now (was `private(set)` for the silent correction).
- Not changed: `removeExpired()` still scans the upload root on every request that reads a file field (performance,
  out of scope); `Session::clearUserData()` removes the list of uploads, the files expire after two days.
- Frontend review: no template, CSS or JavaScript changed; new texts only (`FormMessages::$rateLimited`), rendered in
  the existing error markup of the form.
- Checked: `ddev composer check` green (21396 tests). The DDEV image runs PHP 8.5.8, below 8.5.12 (php2shell).
