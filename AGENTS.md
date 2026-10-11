# AGENTS.md

Persistent instructions for developers and AI assistants working in this repository.

## Global standard

This project follows the Actra coding standard, installed as development dependency `actra/coding-standard`
(https://github.com/Actra-AG/coding-standard).

- Read [vendor/actra/coding-standard/AGENTS.md](vendor/actra/coding-standard/AGENTS.md) and the standards linked
  there before working on this project. They are binding. If `vendor/` is missing, run `composer install` first.
- Before every change to yuf, check for a newer release of `actra/coding-standard`
  (`ddev composer show -a actra/coding-standard`). If there is one, raise the constraint in `composer.json`, run
  `ddev composer update actra/coding-standard`, apply its `UPGRADE.md` entries since the previous version and release
  the result with the next yuf release; name the entries that are not applied yet in the final report.
- The rules below only **add** project-specific rules or state explicit deviations (with reason). They take precedence
  over the global standard where they conflict.

## Project context

- `actra/yuf` is a public Composer library (PHP framework); every public class, argument name and HTML output is API.
- Minimum PHP version: 8.5. Releases are Git tags with a section in `UPGRADE.md`.
- The code meets the coding standard (empty PHPStan baseline). State and open points:
  [docs/plans/done/standard-migration/remaining.md](docs/plans/done/standard-migration/remaining.md).

## Project-specific rules

- Zero runtime dependencies; development dependencies are `actra/coding-standard` and PHPUnit only.
- All classes are loaded by Composer (PSR-4: `autoload` for `src/`, `autoload-dev` for `tests/` and the example's
  `app\` classes).
- yuf ships no JavaScript and no CSS. Its generated HTML keeps the look of existing output (coding standard,
  versioning.md section 4): new markup only as opt-in; any other change of existing markup only with the frontend
  developer and a ⚠️ `UPGRADE.md` entry with before/after.
- Example app: `example/` (https://yuf.ddev.site/, uses the sources of `src/`).
- Skeleton project: `../yuf-skeleton` (https://github.com/Actra-AG/yuf-skeleton), checked on every release
  (`vendor/actra/coding-standard/standards/versioning.md`, section 1).

## Deviations from the global standard

- Release cycle (temporary): while the new base of the Actra libraries is built, changes are collected into few,
  big releases instead of short development cycles (`standards/versioning.md`, section 1). Reason: building the
  foundation fast. Remove this deviation when the base is finished.
