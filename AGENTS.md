# AGENTS.md

Persistent instructions for developers and AI assistants working in this repository.

## Project context

- `actra/yuf` is a public Composer library (PHP framework) that other projects build upon.
- Versioning is done with Git tags (`vMAJOR.MINOR.PATCH`, SemVer). Breaking changes require a new major version.
- Zero runtime dependencies besides `actra/autoloader`. Do not add Composer packages without asking.
- Ongoing goal: refactor the library (especially legacy parts like `src/form/` and `src/template/`) to modern PHP 8.5
  and PHPStan level 10. Backwards compatibility is **not** required, but **no feature may be lost** and every breaking
  change must be documented in `UPGRADE.md` with clear before/after instructions.

## Code quality

- Follow [docs/code-quality.md](docs/code-quality.md). It is binding for all new and changed code.
- Key rules: `declare(strict_types=1);` and the copyright header in every PHP file, `final` by default, fully typed,
  no `mixed` in own code, enums for every fixed set of values, named arguments, one purpose per class, pure logic
  separated from I/O.
- Leave every file you touch cleaner than you found it, but keep each change focused on one topic. Do not reformat
  unrelated code.
- Run `composer check` before finishing a task (see `docs/code-quality.md`, section 2); it must be green. The
  PHPStan baseline may only shrink.
- Without local PHP 8.5, run PHP and Composer commands through DDEV (`ddev composer check`). If DDEV is not running,
  ask the user to start it (`ddev start`).
- `example/` is a minimal running app (https://yuf.ddev.site/) using the sources of `src/`. Keep it working when changing
  the library, and check it in the browser after changes to routing, views, templates or HTML output.

## Refactoring workflow

- Refactor one area (e.g. `src/form/`, `src/template/`) at a time. Plans and handover notes live in
  `docs/<topic>/plan.md`; follow the plan and append handover notes there.
- Before changing behaviour, write down (or test) what the existing code does, so no feature gets lost.
- A breaking change is a change that forces projects to adapt their code or styling: renamed/removed class, method,
  argument or enum case, changed signature or return type, a documented behaviour that no longer works as before,
  changed HTML output. It goes into the topmost unreleased section of `UPGRADE.md`, marked with ⚠️, including a short
  before/after example.
- A bug fix is not a breaking change, even if results change, when the old behaviour was clearly unintended (wrong
  results, exceptions, invalid SQL/HTML, security issues) and projects need no code change. List noticeable fixes in
  `UPGRADE.md` without ⚠️ and release them as patch (or minor together with new features).

## Response style

- Be concise. No filler text, no introductory or concluding pleasantries.
- Do not summarize or restate the problem unless asked.
- Mention assumptions when relevant.
- Do not mention the attached context unless it is needed for the answer.

## Files

- Do not add a final newline at the end of newly created files.
- `.gitignore` whitelists tracked files. New top-level files or directories (e.g. `docs/`, `tests/`, `phpstan.neon`)
  must be added there, otherwise they are not committed.

## Git & commits

- Never run `git commit`, `git add` or `git push` on your own. Prepare the commit message and let the user commit.
- Inspect the actual changes (`git status`, `git diff`, `git diff --staged`) before proposing a commit message.
- Commit messages follow the existing history (Conventional Commits): `type(scope): summary`, `!` for breaking
  changes, then an empty line, a `- ` bullet list of the changes and an optional `Attention:` paragraph. Without the
  empty line, Git treats the whole message as subject.

## Before commit suggestions

When asked to review changes before commit, inspect the changed files and answer:

1. Read `README.md` and say whether it needs to be updated.
2. Read `UPGRADE.md` and say whether it needs to be updated (always for breaking changes).
3. Suggest a commit message following the style of previous commit messages.
4. Check the existing Git tags (`git tag --sort=-v:refname`) and suggest the next release tag (SemVer: breaking change
   → major, feature → minor, fix → patch).