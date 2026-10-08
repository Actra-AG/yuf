# AGENTS.md

Persistent instructions for developers and AI assistants working in this repository.

## Global standard

This project follows the Actra coding standard, installed as development dependency `actra/coding-standard`
(https://github.com/Actra-AG/coding-standard).

- Read [vendor/actra/coding-standard/AGENTS.md](vendor/actra/coding-standard/AGENTS.md) and the standards linked
  there before working on this project. They are binding. If `vendor/` is missing, run `composer install` first.
- The rules below only **add** project-specific rules or state explicit deviations (with reason). They take precedence
  over the global standard where they conflict.

## Project context

- `actra/yuf` is a public Composer library (PHP framework) that other projects build upon. Every public class, method,
  argument name, enum case and generated HTML output is API (see `standards/versioning.md`).
- Minimum PHP version: 8.5. Releases are Git tags with a section in `UPGRADE.md`.
- Ongoing goal: refactor the library (especially legacy parts like `src/form/` and `src/template/`) to modern PHP 8.5
  and the full coding standard. Plans and handover notes live in `docs/<topic>/plan.md`.
- Ongoing goal: bring the existing code up to the shared PHP-CS-Fixer and PHPStan configuration (see
  [docs/coding-standard/plan.md](docs/coding-standard/plan.md)).

## Project-specific rules

- Zero runtime dependencies besides `actra/autoloader`. Development dependencies are `actra/coding-standard` and
  PHPUnit only: no mocking, fixture or faker libraries. Test doubles are hand-written classes in `tests/Double/`.
- All yuf classes, including the tests, are loaded by `actra/autoloader` (registered in `tests/bootstrap.php`), not by
  Composer.
- PHPStan and PHP-CS-Fixer also check the PHP code of `example/`. Only generated code is excluded: the phone number
  metadata in `src/phone/data/` and the template cache of the example in `example/app/cache/`.
- Exceptions: specific SPL exceptions or the yuf exceptions.
- Keep the existing security features (CSP nonces, CSRF tokens, IP whitelists) working and covered by tests. They
  follow `standards/security.md` like all other code; this is no exception from it.
- yuf ships no JavaScript.
- `.ddev/config.yaml` provides PHP 8.5 without a database.
- `example/` is a minimal running app (https://yuf.ddev.site/) using the sources of `src/`. Keep it working when changing
  the library, and check it in the browser after changes to routing, views, templates or HTML output.
- Every yuf release: check whether `../yuf-skeleton` (https://github.com/Actra-AG/yuf-skeleton, the starting point of
  `composer create-project`) needs an update: the `actra/yuf` constraint for a new major version, code affected by a ⚠️
  entry of `UPGRADE.md`, setup or settings changed in the README. Report the result; if it needs an update, give the
  prompt for a separate session in that project.
- `.gitignore` whitelists tracked files: new top-level files or directories must be added there.

## Deviations from the global standard

- None.
