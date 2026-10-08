# Plan: rewrite the template engine

Design: [design.md](design.md). Every step is released on its own (version numbers assume nothing else is released in
between), `composer check` stays green, the baseline only shrinks, `example/` is checked in the browser after every step
that changes rendering. Characterization tests first; hand-written doubles in `tests/Double/`.

## Steps

1. **Characterization tests (no release):** golden-output tests of today's engine for everything that stays:
   `text` (strings, numbers, booleans, `null`, `HtmlText` both kinds), `if` with every operator and `against` value
   used (`null`, `""`, `true`, `false`, strings, numbers) on values `null`, `''`, `[]`, `false`, `true`, `0`, strings,
   `else`, nested `for`, `loadSubTpl`, `lang`, `snippet` (`.html` and other), `print`, `date` (fixed clock if possible),
   `options`; `src/pagination/pagination.html` and `src/table/filter/tableFilter.html`; the example templates and the
   error pages. Fixtures in `tests/Fixture/template/`. These tests are kept and later run against the new engine.
2. **v4.24.0 – new engine, not wired:** `TemplateParser`, `TemplateNode` classes, `TemplateCompiler`,
   `TemplateRuntime`, `TemplateTag` + `TemplateTagContext`, `TemplateTagCollection`, built-in tags, `TemplateCache` +
   `DirectoryTemplateCache`, `TemplateEngine`, `TemplateException` in new namespaces (e.g. `actra\yuf\template\parser`,
   `…\compiler`, `…\runtime`, `…\tag`, `…\cache`; final names decided in the task, no clash with the old classes).
   Unit tests for parser (nesting, attributes, mismatched close tag, `<?php`), compiler (no static calls, literals via
   `var_export()`), runtime (selectors, scopes, comparison rules, escaping), cache (version key, atomic write) and every
   tag; the characterization tests of step 1 also run against the new engine (expected differences only for escaping
   and removed features, listed in the task). Additive, no ⚠️.
3. **v4.25.0 – replacement API says what it does (⚠️):** `HtmlText::fromText()` / `fromHtml()`,
   `HtmlReplacementCollection::addText()` / `addHtml()`, the same for `HtmlDataObject` and the collections; old names
   removed. Behaviour unchanged (still the old engine). Fix the comment in the example view. `UPGRADE.md` with a
   mechanical migration (`addEncodedText` → `addHtml`, `addUnencodedText` → `addText`) and the advice to review every
   `addHtml()` for user data.
4. **v4.26.0 – switch to the new engine (⚠️):** `Core` creates the engine per request; `ContentHandler` /
   `HtmlDocument`, `ExceptionHandler`, `HtmlSnippet`, `Pagination`, `TableFilter` and `ViewContext` use it; output of
   plain strings is escaped; the removed tags and features of design section 4 are gone; the old engine
   (`src/template/customtags/`, `htmlparser/`, `template/`) is deleted, and with it `Core::get()`,
   `LocaleHandler::get()` / `isRegistered()` and `tests/Double/CoreTestInstance` (check `LogFile` first: it still uses
   `Core::get()` – move it into this step or the `LogFile` plan). Baseline entries of the deleted code are removed.
5. **v4.27.0 – own tags:** `Core::prepareHttpResponse(templateTags: …)`, README section with an example tag, test with a
   hand-written tag.

## Follow-up in `actra/backend`

- After v4.25.0: rename `addEncodedText` → `addHtml` (70×), `addUnencodedText` → `addText` (3×), `HtmlText::encoded()`
  / `unencoded()` (192×); then review every `addHtml()` / `fromHtml()` for user data.
- After v4.26.0: no template change expected (it uses only `text`, `if`, `else`, `for`, `loadSubTpl`); check the
  rendered pages; `HtmlSnippet` / table rendering if it calls them directly.

## Handover notes

### Step 1 – done

Tests only; `src/` is unchanged, nothing is released. `composer check` is green.

**Structure (swappable engine).** All cases render through `TemplateCharacterizationTestCase::renderSource()` /
`renderFile()` (`tests/Double/template/`), which uses `OldTemplateRenderer` (old `TemplateEngine` + `DirectoryTemplateCache`
in a fresh temporary directory, removed in `tearDown()`). Step 2 adds a second renderer and switches the base class (or
runs the classes against both). `OldTemplateRenderer` closes the output buffer that the old engine leaves open when a
template throws. `CoreTestInstance::register()` got the optional argument `$snippetsDirectory` (fixture snippets in
`tests/Fixture/template/snippets/`). `TemplateLangTagTest` registers a `LocaleHandler` without language (no
`setlocale()`) and resets the private static `LocaleHandler::$registeredInstance` by reflection in `setUp()` /
`tearDown()` (no public way to unregister).

**Test classes (`tests/Unit/template/`), 485 new tests:** `TemplateTextTagTest` (59), `TemplateIfTagTest` (338, of them
the 320-case comparison matrix), `TemplateForTagTest` (19), `TemplateIncludeTagsTest` (12, `loadSubTpl` + `snippet`),
`TemplateLangTagTest` (7), `TemplateOutputTagsTest` (29, `print`, `date`, `options`), `TemplateSyntaxTest` (12, whitespace,
parser behaviour), `TemplateFilesTest` (9: `pagination.html` ×3, `tableFilter.html` ×3, example `default.html` + `index.html`,
`notFound.html`, `default.html` of the error docs). Expected whole-page output is pinned line by line including the
odd whitespace (see below). Test names marked "Differs" in comments pin behaviour that design.md changes on purpose.

**Comparison matrix of `if`** (loose PHP comparison; Y = if branch). Values in this order:
`null  ''  []  false  true  0  1  '0'  'abc'  [1]`

| operator | against | null '' [] false true 0 1 '0' 'abc' [1] |
|:--|:--|:--|
| eq | `null` | Y Y Y Y N Y N N N N |
| eq | `""` | Y Y N Y N N N N N N |
| eq | `true` | N N N N Y N Y N Y Y |
| eq | `false` | Y Y Y Y N Y N Y N N |
| eq | `abc` | N N N N Y N N N Y N |
| eq | `1` | N N N N Y N Y N N N |
| eq | `0` | N N N Y N Y N Y N N |
| ne | same as `eq` | the exact inverse of `eq` with the same `against` (all 7 rows) |
| gt/ge/lt/le | `0`, `1`, `abc` | in the provider (`TemplateIfTagTest::comparisonProvider()`) |
| in | `""`, `true`, `false`, `abc`, `a b`, `1 0` | in the provider; `against` split at spaces |

Key results: `against="null"` matches `null`, `''`, `[]`, `false` and `0` (but not `'0'`); `against=""` matches `null`,
`''` and `false` but **not** `[]` and not `0`; `against="true"` matches `true`, `1`, `'abc'`, `[1]` (not `0`, `'0'`, `[]`); `against="false"` matches `null`, `''`, `[]`, `false`, `0`, `'0'`. `true == 'abc'`, so
`eq abc` is Y for `true`. `gt/ge/lt/le` never throw (`'abc'` and arrays compare by PHP 8 rules).

**Differences to design.md that need a decision**

1. Escaping (section 5): the engine never escapes. Plain values (strings, via `ArrayObject` or `stdClass`), `lang`, `print` and
   `options` (keys and labels) are output raw. `HtmlText::unencoded()` is escaped by the replacement API with `ENT_QUOTES`
   (`'` becomes `&#039;`). `HtmlReplacementCollection` has no plain-string method, so a plain string reaches the engine only
   through `HtmlDataObject::addTextElement()`, the `encoded()`/`unencoded()` text or an `ArrayObject`.
2. `if` comparison (3.1): `[]` does not match `against=""` but matches `against="null"`; `0` matches `null` and `false` but
   not `''`; `in` splits `against` at spaces (not commas) and `in` with `against="null"` raises an `explode()` deprecation;
   `gt/ge/lt/le` with non-numeric values do not throw. The exact rules of 3.1 ("the exact list decided by the
   characterization tests") need to be written from the table above.
3. `if` attributes: `operator` and `against` are both required today (missing: plain `Exception`
   "Could not parse the template: Missing attribute …"); design says `operator` defaults to `eq`. An unknown operator is
   an `Exception` "Unknown operator". An apostrophe in `against` produces a `ParseError` when the compiled file runs
   (code injection, design section 1).
4. `else` (3, 2): text and whitespace between `</tst:if>` and `<tst:else>` are rendered with the if branch (so the line
   break/indentation between them appears in the output of the if branch only); there is no check that `else` follows an
   `if` directly (only "no custom tag before" throws, with a plain `Exception`).
5. `for` (3): a `var` that shadows an outer key removes that key after the loop (`unsetData()`): a later
   `{tst:text value='i'}` throws code 1. Inside the loop the outer value is shadowed. A `stdClass` is iterated by its
   properties, array keys are ignored, `null` is an empty list. `{word}`, `{a.b}` and `${a.b}` in the body are raw echo
   (`{foo}` with an unknown variable: PHP warning; `{_count}`: warning without `groups`, not testable).
6. Selectors (2): getters only work when a property of that name exists (`getComputed()` without property: "Don't know how
   to handle selector part"); a public method is called by name (`target.describe`, also with arguments
   `a.method(x)`); `ArrayObject` and arrays use their keys; failures are plain `Exception`s with these messages (missing
   top-level key: code 1, includes the template file; no line numbers).
7. `loadSubTpl`: `tplfile="{this}"` with a missing key is a `TypeError` (null to `string`); a missing file is
   "Could not find template file: <path>"; the tag cannot be used inline.
8. `lang`: `vars` is broken (`Error: Class "LangTag" not found`, both forms); text is output unescaped; placeholders
   stay when no `vars` are given; a missing key throws `Exception` "Missing language fragment for <key>".
9. `snippet`: a name with `..` can leave the snippets directory (pinned `../sub.html`); a missing `.html` snippet throws
   "Could not find template file"; a missing non-html snippet is only a PHP warning (not testable) and empty output; the
   snippets directory is compiled into the cache file.
10. `print`: only `DateTime` is formatted (`Y-m-d H:i:s`); `DateTimeImmutable` is dumped with `print_r()` (design:
    `DateTimeInterface`); `null` outputs `''`; strings are raw.
11. `date`: uses `date()` (real clock, not a `Clock`; asserted by regex only) and works only as element, inline throws
    "is not allowed to use inline".
12. `options`: `selected` is required (omitted: `Exception` code 1 for the offset `""`); design says optional. Keys and labels
    are unescaped; `optgroup` for nested arrays; selected values match by string comparison (`1` and `'1'`).
13. Parser (2): a mismatched close tag is accepted; `<?php … ?>` in a template is executed; `<tst:text>` is self-closing, so
    `<tst:text …></tst:text>` leaves `</tst:text>` in the output; element tags with single-quoted attributes are not
    recognized and stay as text; `hasSnippet` is pinned in `TemplateIfTagTest` (removed in design section 4).
14. Whitespace: a single line break directly after any tag (inline or element) is swallowed by the PHP closing tag of the
    compiled code; the indentation before a tag stays; pagination/table filter output therefore has blank, whitespace-only
    lines (pinned as is in `TemplateFilesTest`). The new engine will differ here unless it copies the rule; decide whether
    the pinned whitespace is normalised for the comparison (e.g. in the shared test case) or accepted as a difference.
15. Output buffer: the old engine leaves an output buffer open when a template throws (`ob_clean()` instead of
    `ob_end_clean()`); `OldTemplateRenderer` closes it.

**Not covered:** `elseif`, `for2`, `forgroup`, `option`, `checkbox`, `radio`, `checkboxOptions`, `radioOptions`,
`formComponent`, `formAddRemove`, `for` attributes `groups` / `classfirst` / `classlast` (removed or broken, design section 4);
the real clock of `date`; PHP warnings and deprecations of the compiled code (not testable because PHPUnit fails on
them; documented above); the `HtmlSnippet`, `ContentHandler` and `ExceptionHandler` wiring (not part of the engine; the
templates are rendered through the renderer with the replacement keys of `HtmlDocument` and `ExceptionHandler`).
