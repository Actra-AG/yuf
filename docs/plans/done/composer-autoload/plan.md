# Plan: Composer as the only autoloader

yuf and the projects built on it load classes with two autoloaders: Composer's (tools, tests, Composer packages and,
since v5.0.0, yuf wherever `vendor/autoload.php` is included) and `actra/autoloader` (registered by
`Core::fromEnvironment()`, today mostly for the classes of `app/`). One loader is simpler and faster: Composer's class
map, generated at deploy time, is one array lookup per class, held in OPcache, without writes at runtime.

Status: done for yuf (v5.1.0), the skeleton and drogeriehaas.ch (2026-10-10); the other projects follow with their
own migration to yuf 5.

## Why `actra/autoloader` is not extended instead

To replace Composer it would need the autoload rules of every dependency (PSR-4, PSR-0, class maps, `files` for
functions), a class map generated at deploy time instead of a cache written by the web process, and the integration
that PHPStan, PHPUnit and IDEs get from `vendor/autoload.php`. That is a rebuild of Composer's autoload dumper.

## What changes for whom

- Composer becomes required to build an application, not to run it: `vendor/` (with `vendor/autoload.php`) is built
  locally or in CI and deployed as files; the server needs no Composer.
- yuf without Composer (download and `autoloaderPath:`) is no longer supported. yuf's runtime dependencies drop to
  none.

## Steps

### Step 1 – yuf (breaking, next big release)

- `Core::fromEnvironment()` registers no autoloader: remove `autoloaderPath:`, the `require_once` of the autoloader and
  of `DirectoryPathResolver.php`, `Core::AUTOLOADER_CACHE_FILE_NAME` and the registration of `app\`. The entry point
  includes `vendor/autoload.php` first.
- `composer.json`: remove `actra/autoloader` from `require`.
- Measure before/after (`docs/plans/done/performance/plan.md`, step 4): request time of the example with the
  `actra/autoloader` cache vs. `composer dump-autoload --optimize` (and `--classmap-authoritative`).
- Docs: README (no "download without Composer", no runtime dependency), `docs/setup.md` (entry point, deploy with
  `composer install --no-dev --optimize-autoloader`, optional `--classmap-authoritative`, `opcache.preload`),
  `docs/testing.md` (bootstrap is `require vendor/autoload.php` only), AGENTS.md, `example/` entry point.
- UPGRADE.md ⚠️ with before/after: `"autoload": {"psr-4": {"app\\": "app/"}}` in the project's `composer.json`,
  `require __DIR__ . '/../vendor/autoload.php';` in `public/index.php` and CLI scripts, no `autoloaderPath:`, delete
  `app/cache/autoloader.php`, test bootstrap without `Autoloader`.

### Step 2 – yuf-skeleton

- PSR-4 for `app\`, entry point and test bootstrap with `vendor/autoload.php` only, deploy command in the README;
  remove `actra/autoloader`. Check `/` and `/auto/` (`ClassNameViewFactory` relies on `class_exists()` of lowercase
  view classes: check that PSR-4 finds them; otherwise a class map or a fixed naming rule).

### Step 3 – projects (actra.ch, drogeriehaas.ch, all others on yuf)

- Same migration as the skeleton; remove `actra/autoloader`. actra/backend needs no change (already Composer only).

### Step 4 – `actra/autoloader`

- Dropped (decision 4): `actra/autoloader` stays maintained for projects outside yuf.

## Decisions (user, 2026-10-10)

1. yuf requires Composer at build time (not on the server); no compatibility for the download variant.
2. Now, as one big release of yuf: v5.1.0 (breaking changes in a minor release, versioning.md section 2).
3. Deploy with `composer install --no-dev --optimize-autoloader` (class map with PSR-4 fallback).
4. `actra/autoloader` stays maintained for projects outside yuf (step 4 is dropped: no archive).

## Handover notes

### Step 1 (2026-10-10, v5.1.0)

- `Core::fromEnvironment()` registers no autoloader (`autoloaderPath:`, `AUTOLOADER_CACHE_FILE_NAME` and the
  `require_once` of the resolver removed); `actra/autoloader` removed from `require`; the example's `app\` classes are
  in `autoload-dev`; example, `index.example.php`, README, setup, testing, AGENTS.md and UPGRADE.md updated.
- Measured (example home page, median of 200 requests): 10.0–10.8 ms with `actra/autoloader`, 10.4–12.0 ms with
  Composer (plain and optimized): no difference beyond the noise of about 1 ms.
- Next: step 2 (skeleton) and step 3 (projects) in their own sessions.

### Steps 2 and 3 (2026-10-10)

- Skeleton (v1.14.0) and drogeriehaas.ch load all classes with Composer; actra/backend already did. The other projects
  on yuf (actra.ch and the projects without a version constraint) were postponed by the user: they take the Composer
  migration with their move to yuf 5 (yuf UPGRADE.md v5.1.0).
