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

- `actra/yuf` is a public Composer library (PHP framework); every public class, argument name and HTML output is API.
- Minimum PHP version: 8.5. Releases are Git tags with a section in `UPGRADE.md`.
- The code meets the coding standard (empty PHPStan baseline). State and open points:
  [docs/standard-migration/remaining.md](docs/standard-migration/remaining.md).

## Project-specific rules

- Zero runtime dependencies besides `actra/autoloader`. Development dependencies are `actra/coding-standard` and
  PHPUnit only: no mocking, fixture or faker libraries. Test doubles are hand-written classes in `tests/Double/`.
- All yuf classes, including the tests, are loaded by `actra/autoloader` (registered in `tests/bootstrap.php`), not by
  Composer.
- PHPStan and PHP-CS-Fixer also check `example/`. Only generated code is excluded: `src/phone/data/` and
  `example/app/cache/`.
- Exceptions: specific SPL exceptions or the yuf exceptions.
- Keep the security features (CSP nonces, CSRF tokens, IP whitelists) working and covered by tests.
- yuf ships no JavaScript.
- `.ddev/config.yaml` provides PHP 8.5 without a database.
- `example/` is a minimal running app (https://yuf.ddev.site/) using the sources of `src/`. Keep it working and check
  it in the browser after changes to routing, views, templates or HTML output.
- Documentation: `README.md` links the user docs `docs/<topic>.md`; change them together with the code. Plans, designs
  and analyses of a refactoring live in `docs/<topic>/` and are excluded from the package in `.gitattributes`. Upgrade
  notes up to v4.49.0 are in `docs/upgrade/`.
- Every release: check whether `../yuf-skeleton` (https://github.com/Actra-AG/yuf-skeleton, the starting point of
  `composer create-project`) needs an update: the `actra/yuf` constraint, code affected by a ⚠️ entry of `UPGRADE.md`,
  setup or settings changed in the docs. Report the result; if it needs an update, give the prompt for a separate
  session in that project.

## Deviations from the global standard

- None.
