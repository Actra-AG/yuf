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
