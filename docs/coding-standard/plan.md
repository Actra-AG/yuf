# Plan: adopt the shared tooling of `actra/coding-standard`

yuf uses `actra/coding-standard` (v1.1) as development dependency. Its rules replace the former `docs/code-quality.md`;
`AGENTS.md` only keeps the project-specific rules. The shared PHPStan and PHP-CS-Fixer configurations are wired in,
but the existing code does not meet them yet, so `composer check` is red until the tasks below are done.

## State after the switch

- `phpstan.neon` includes `vendor/actra/coding-standard/config/phpstan.neon` (level 10, bleeding edge, strict rules,
  deprecation rules, PHPUnit extension, disallowed calls).
- `phpstan-baseline.neon` was regenerated with the new rules: 616 → 1179 entries, all in `src/`. The 118 errors in
  `tests/` are **not** in the baseline (`tests/` never has baseline entries), so `composer phpstan` fails until task 2
  is done. Most frequent identifiers in `src/`: `method.missingOverride`, `disallowed.function` (named arguments of
  `in_array()`/`array_search()` with `strict`, `is_null()`, …), `offsetAccess.notFound`, `disallowed.isset`.
- `.php-cs-fixer.dist.php` checks `src/`, `tests/` and `example/` (without generated code). `composer cs` fails for
  nearly every file.
- `tests/Unit/FileHeaderTest.php` was removed: PHP-CS-Fixer (`header_comment`, `declare_strict_types`) enforces the
  file header now. The header gets a blank line after `<?php` (PER Coding Style).

## Tasks

1. **Code style (`style` commit, no other changes):** run `ddev composer cs:fix` and review the diff. Check the risky
   fixers by hand, they can change behaviour: `strict_param` (adds `strict: true` to `in_array()` etc.),
   `strict_comparison` (`==` → `===`), `random_api_migration`, `modernize_types_casting`. Fix
   `src/common/StringUtils.php` by hand: PHP-CS-Fixer reports a lint error after fixing it. Run all tests and check
   `example/` in the browser. Changed behaviour of public methods goes into `UPGRADE.md`.
2. **Tests:** fix the 118 PHPStan errors in `tests/` (mostly `#[\Override]` on `setUp()`/`tearDown()`, short ternary,
   explicit comparisons, deprecated PHPUnit API). `composer check` must be green afterwards.
3. **Baseline:** shrink it area by area as part of the refactoring (see the other plans in `docs/`). The rule "the
   baseline may only shrink" applies again from now on.

## Handover notes

### Task 1 (code style) – done

- `composer cs:fix` reformatted 393 files. Afterwards `composer cs` is green, all tests pass, `example/` works.
- PHP-CS-Fixer bugs fixed by hand:
    - `pow_to_exponentiation` produced invalid code with named arguments (`1024 ** num: …`) in
      `StringUtils::formatBytes()`: written as `1024 ** $pow`.
    - `strict_param` added a positional `true` after named arguments (fatal error "Cannot use positional argument after
      named argument" in 19 files): changed to `strict: true`.
    - Since `actra/coding-standard` v1.1.1, these risky fixers (`strict_param`, `strict_comparison`,
      `modernize_types_casting`, `pow_to_exponentiation`, `random_api_migration`) are disabled; PHPStan reports the
      cases and they are fixed by hand.
- Behaviour kept where the risky fixers changed it:
    - `strict_comparison` turned `$value == 0` into `$value === 0` in `FloatSanitizer` (float vs. int, always false):
      now `=== 0.0`. Regression test: `FloatSanitizerTest`.
    - `For2Tag`: `step` and `grab` are attribute strings, so `=== 0` / `=== 1` never matched (lost the endless loop
      guard): compared numerically now.
    - Strict `in_array()` compared numeric array keys (int) with string values: option/checkbox/radio selection in the
      templates (new `@internal CustomTagsHelper::isSelected()`, test: `OptionsSelectionTest`) and
      `SearchHelper::checkMultiFilter()` (key cast to string).
- Changes for `UPGRADE.md` (added in v4.8.1 with task 2):
    - `AuthWebToken` decodes Base64 strictly: tokens with invalid characters are rejected (`UnauthorizedException`)
      instead of decoding the remaining characters.
    - `StringUtils::randomString()`, `StringUtils::generateSalt()` and the temporary file name of `CSVFile` use
      `random_int()` (cryptographically secure) instead of `mt_rand()` / `rand()`.
- Pre-existing bug, fixed in v4.8.2: `ContentType::createDefault()` checks `in_array()` against the values
  of an array whose keys are the types, so `forceDownloadByDefault` is always `true`.
- Baseline: 1179 → 1057 entries.

### Task 2 (tests) – done

- `composer check` is green.
- `#[Override]` added in 30 files of `tests/`.
- The deprecated `expectExceptionMessage()` is replaced by `expectExceptionMessageIsOrContains()` (same behaviour;
  most tests check only a part of the message). Use `expectExceptionMessageIs()` in new tests.
- Removed checks that PHPStan already proves (`testIsAClock()` of both clocks, `assertInstanceOf()` on typed return
  values). `assertArrayHasKey()` before reading array offsets, `assertInstanceOf(PDOStatement::class, …)` after
  `prepare()`, `random_bytes()` instead of `uniqid()` for temporary directories.
- `@phpstan-ignore` with reason: SHA-1 in the tests of `UploadedFile::getHash()` (identifier, not security), the test
  session handler that does not call the parent constructor, the characterization test of the deprecated
  `SearchHelper::getBooleanQuery()`.
- Released as v4.8.1. Remaining: task 3 (shrink the baseline with the refactoring of each area).

### Task 3 (baseline) – ongoing

- `#[Override]` added in 112 files of `src/` (all `method.missingOverride` entries, no behaviour change): baseline
  1057 → 794 entries.
- Remaining entries per area: `src/template` is the largest, followed by `src/common`, `src/form`, `src/core` and
  `src/phone`. Most frequent identifiers: `argument.type`, `missingType.iterableValue`, `offsetAccess.notFound`.
- `src/form/` has no baseline entries again (v4.9.0): explicit `array_key_exists()` instead of `isset()`, `match`
  instead of `switch` for the layouts of the option fields, `random_bytes()` instead of `uniqid()`,
  `FormField::$topFormComponent` as property hook with a nullable backing property and `hasTopFormComponent()`.
  `UploadedFile::getHash()` uses SHA-256 instead of SHA-1 (changes the posted remove value, listed in `UPGRADE.md`).

## Security check against `standards/security.md` (2026-10-07)

Fixed in v4.9.1: `IpValidator::isInWhitelist()` (any IPv6 range allowed every IPv6 address, shifted IPv4 ranges,
invalid ranges). Open, postponed by the user:

1. Patch (no API change):
    - `HttpResponse` sends `Strict-Transport-Security: max-age=<cache max-age>`; file responses with `maxAge: 0`
      (e.g. `CSVFile`) send `max-age=0`, which removes HSTS in the browser. Use a separate HSTS max-age.
    - `CsrfToken::validateToken()` compares with `===` instead of `hash_equals()`.
    - `CsrfToken::getToken()` and `CspNonce::generate()` use `openssl_random_pseudo_bytes()` instead of
      `random_bytes()`.
    - `AbstractSessionHandler` calls `session_regenerate_id()` without `delete_old_session: true`.
2. Minor (breaking, `UPGRADE.md` with ⚠️):
    - `CsrfTokenField` accepts the token from the query string, `CsrfToken::renderAsGetParam()` builds such URLs
      (no tokens in URLs).
    - `TableFilter` renders the CSRF token in its POST form but never validates it.
    - The CSP nonce is stored in the session and reused for all requests instead of a new nonce per request.
    - `X-Content-Type-Options: nosniff` and `Referrer-Policy` are not sent.

