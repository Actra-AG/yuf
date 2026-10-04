# Code Quality and Testing

These rules apply to everyone working on `actra/yuf` and to all new and changed code. Existing code that does not
meet them yet is brought up to this standard when it is changed.

## 1. Tooling

yuf has zero runtime dependencies (besides `actra/autoloader`), and keeps it that way. Only two dev-only tools are
allowed (`require-dev`, never a runtime requirement for consumers of the library):

| Tool    | Purpose                   | Why not local code                                              |
|:--------|:--------------------------|:----------------------------------------------------------------|
| PHPStan | Static analysis, level 10 | A type checker cannot reasonably be written in-house            |
| PHPUnit | Unit tests                | De-facto standard, wide IDE support, no runtime footprint       |

No PHPStan extensions or plugins, no mocking libraries, no fixture/faker libraries, no code style tools. Test doubles
are small hand-written classes in `tests/`.

## 2. Commands

Composer scripts (requires PHP 8.5 and the extensions listed in `composer.json`):

```bash
composer phpstan           # static analysis
composer phpstan:baseline  # regenerate phpstan-baseline.neon
composer test              # all tests
composer check             # phpstan + test
```

With DDEV (optional, `.ddev/config.yaml` provides PHP 8.5 without a database), prefix the commands with `ddev`, e.g.
`ddev composer check`.

Every task and every commit must end with a green `composer check`.

## 3. PHPStan

- `phpstan.neon` in the project root: `level: 10`, `phpVersion: 80500`, analysed paths `src/`, `tests/` and the PHP code
  of `example/`. Only generated code (phone number metadata in `src/phone/data/`, the example's template cache) is
  excluded. Do not lower the level or add exclusions.
- **Baseline for legacy code:** existing errors go into `phpstan-baseline.neon`.
    - New files must not appear in the baseline. `tests/` never has baseline entries.
    - When you change an existing file, fix its baseline entries and regenerate the baseline. The baseline may only
      shrink.
    - `@phpstan-ignore` is only allowed with an identifier and a reason, e.g.
      `// @phpstan-ignore argument.type (PDO returns mixed, value validated above)`.

## 4. Coding Rules (PHP 8.5)

### 4.1 Structure

- **One class, one purpose.** The name says what it does. If you need "and" to describe it, split it.
- Small methods (rule of thumb: ≤ 20 lines). Flat nesting: early returns, no `else` after `return`.
- Separate pure logic from I/O. Logic classes (validation, parsing, rendering of a given model, calculations) do not
  access `$_GET`, `$_POST`, `$_SESSION`, `$_SERVER`, the file system, the database or the clock directly. They get
  their input as arguments and are unit tested.
- Dependencies are passed in through the constructor. No new static state, singletons or global functions. Existing
  static accessors are replaced by explicit dependencies when their code is changed.
- If time matters, inject a `Clock` interface instead of calling `time()` or `new DateTimeImmutable()` in logic.
- Prefer composition over inheritance. Abstract base classes only for a real "is a" relation; interfaces for
  extension points that projects implement (e.g. renderers, rules, template tags).
- Keep good existing patterns. New abstractions need a reason.

### 4.2 Types

- Every PHP file starts with the copyright header followed by `declare(strict_types=1);`:
  ```php
  <?php
  /**
   * @copyright Actra AG - https://www.actra.ch
   * @license   MIT
   */

  declare(strict_types=1);
  ```
  `tests/Unit/FileHeaderTest.php` enforces this for `src/`, `tests/` and `example/`.
- `final` classes by default. `readonly` classes or properties for value objects. Non-final only for intended
  extension points.
- Fully typed properties, parameters, constants and return types. No `mixed` in own code. PHPDoc only for what PHP
  cannot express (`list<FormField>`, `array<string, string>`, `non-empty-string`).
- No untyped "options" arrays. Use small readonly value objects or named arguments instead.
- Nullable types are written as `?Type`.
- **Enums first.** Every fixed set of values (states, types, modes, results, HTML attribute values) is a backed enum,
  never string/int constants or magic strings. Behaviour of a value lives on the enum and uses `match`. New enums end
  with `Enum` (like `RequestMethodEnum`); existing enums are renamed when their code is changed.
- Narrow external `mixed` (request data, DB rows, JSON, session) right at the boundary, with explicit checks in one
  place, and throw a meaningful exception on invalid data.
- Use PHP 8.5 features where they make code clearer (pipe operator `|>`, `#[\NoDiscard]` on methods whose result must
  be used, `clone()` with properties, property hooks, asymmetric visibility). Never just to show off.

### 4.3 Style

- Named arguments for all calls, as in the existing code. Exception: methods marked `@no-named-arguments` (e.g.
  PHPUnit's `assert*()`) are called with positional arguments.
- Refer to the own class by its name (`FormField::create()`), not `self::` / `static::`, as in the existing code.
- Compare with `=== null` / `!== null`, never `is_null()`. Strict comparisons (`===`) only.
- No abbreviations in names (`$formField`, not `$ff`).
- Comments explain *why*, not *what*. Keep them short.
- No dead code, no commented-out code, no `TODO` without a linked task in `docs/`.
- Exceptions: throw specific SPL exceptions (`InvalidArgumentException`, `LogicException`, …) or the yuf exceptions
  with a message that tells the developer what is wrong and how to fix it.

### 4.4 Security

- All output is HTML-escaped by default. Unescaped output must be explicit (e.g. `HtmlText::unencoded()`).
- SQL only with bound parameters. Identifiers that cannot be bound are validated against a whitelist.
- Keep the existing security features (CSP nonces, CSRF tokens, IP whitelists) working and covered by tests.

### 4.5 HTML output and JavaScript

- Generated HTML (forms, tables, pagination, templates) works without JavaScript and is valid, accessible HTML
  (labels, `aria-*` where needed).
- yuf ships no JavaScript. If it ever does: vanilla ES modules, one module per purpose, attached via `data-*`
  attributes, no inline `onclick`, progressive enhancement only.
- Changes to generated HTML (markup, CSS classes, attributes) are breaking changes for projects and must be listed in
  `UPGRADE.md`.

## 5. Public API and Upgrades

- yuf is a public library: every public class, method, argument name (named arguments!), enum case and generated
  output is API.
- Breaking changes are allowed, but:
    - no feature may be lost; if something is removed, its replacement is documented,
    - every breaking change is listed in `UPGRADE.md` (topmost unreleased section, marked with ⚠️) with a short
      before/after example,
    - breaking changes are released as a new major version.
- A breaking change forces projects to adapt their code or styling. A bug fix that corrects clearly unintended
  behaviour (wrong results, exceptions, invalid SQL/HTML, security issues) is not breaking, even if results change; it
  is listed in `UPGRADE.md` without ⚠️ when users may notice it.

## 6. Tests

### 6.1 Layout

```
tests/
  bootstrap.php   # registers actra/autoloader for src/ and tests/
  Unit/           # pure logic, no I/O, fast
  Double/         # hand-written fakes when needed (FixedClock, InMemorySession, …)
phpunit.xml
```

Namespace and directory mirror `src/`: `actra\yuf\tests\Unit\datacheck\…` in `tests/Unit/datacheck/` tests
`actra\yuf\datacheck\…`. Like in production, all yuf classes (including tests) are loaded by `actra/autoloader`, not
by Composer. PHPUnit 13: data providers via `#[DataProvider]` attributes, no annotations.

### 6.2 What to test

- **Unit (mandatory for every new or refactored logic class):** validation rules, parsers, template tags, renderers
  (given model → expected HTML), value objects, enums with behaviour.
- **Before refactoring existing code**, add characterization tests that capture its current behaviour and output, so
  lost features show up as failing tests.
- Code that needs a real database or HTTP is kept thin and tested via its pure parts.
- Test names describe behaviour: `testRequiredRuleFailsForEmptyString()`.
- One assertion topic per test. Use data providers for tables of cases.

### 6.3 Definition of Done (every task)

1. `composer check` is green (PHPStan level 10 without new baseline entries, all tests pass).
2. New and refactored logic classes have unit tests.
3. `UPGRADE.md` lists every breaking change; `README.md` is updated where needed.
4. Handover notes are written in the plan (`docs/<topic>/plan.md`), if the task belongs to one.