# AGENTS.md

Project-specific instructions for developers and AI assistants. This project follows the Actra coding standard
([vendor/actra/coding-standard/AGENTS.md](vendor/actra/coding-standard/AGENTS.md), installed as development dependency
`actra/coding-standard`; run `composer install` if `vendor/` is missing).

## Project context

- `actra/yuf` is a public Composer library (PHP framework).
- Example app: `example/` (https://yuf.ddev.site/, uses the sources of `src/`).
- Skeleton project: `../yuf-skeleton` (https://github.com/Actra-AG/yuf-skeleton).

## Project-specific rules

- No runtime Composer packages (PHP extensions only); development dependencies are `actra/coding-standard` and
  PHPUnit only.
- yuf ships no JavaScript and no CSS. The project has a frontend developer: changes of generated HTML need frontend
  review (coding standard, versioning.md section 4).
