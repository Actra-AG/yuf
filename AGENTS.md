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
- yuf is loaded by Composer (PSR-4, `autoload`, `autoload-dev`) wherever `vendor/autoload.php` is included (tools,
  tests, applications with Composer packages); otherwise `Core::fromEnvironment()` loads it with `actra/autoloader`.
- yuf ships no JavaScript and no CSS. No frontend review: changes of the HTML output are API changes and get an
  `UPGRADE.md` entry.
- Example app: `example/` (https://yuf.ddev.site/, uses the sources of `src/`).
- Skeleton project: `../yuf-skeleton` (https://github.com/Actra-AG/yuf-skeleton).

## Deviations from the global standard

- Release cycle (temporary): while the new base of the Actra libraries is built, changes are collected into few,
  big releases instead of short development cycles (`standards/versioning.md`, section 1). Reason: building the
  foundation fast. Remove this deviation when the base is finished.
