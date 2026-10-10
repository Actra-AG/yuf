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
  [docs/plans/done/standard-migration/remaining.md](docs/plans/done/standard-migration/remaining.md).

## Project-specific rules

- Zero runtime dependencies besides `actra/autoloader`; development dependencies are `actra/coding-standard` and
  PHPUnit only.
- In applications, `Core::fromEnvironment()` loads the yuf classes with `actra/autoloader`. The Composer PSR-4
  autoload (`autoload`, `autoload-dev`) serves tools and tests (`tests/bootstrap.php`).
- yuf ships no JavaScript and no CSS. No frontend review: changes of the HTML output are API changes and get an
  `UPGRADE.md` entry.
- Example app: `example/` (https://yuf.ddev.site/, uses the sources of `src/`).
- Skeleton project: `../yuf-skeleton` (https://github.com/Actra-AG/yuf-skeleton).

## Deviations from the global standard

- None.
