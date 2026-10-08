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

### Step 2 (v4.24.0) – done

New engine built next to the old one, not wired. `Core`, `HtmlDocument`, `HtmlSnippet`, `Pagination`, `TableFilter` and
the old classes are unchanged. `composer check` is green, the baseline is unchanged (760 entries, no entry for new code).

**Classes (`src/template/`).** Public API: `TemplateEngine`, `TemplateData`, `TemplateException`, and in `tag/`
`TemplateTag`, `TemplateTagContext`, `TemplateTagCollection`, `TextTag`, `LoadSubTplTag`, `LangTag`, `SnippetTag`,
`PrintTag`, `DateTag`, `OptionsTag`; in `cache/` `TemplateCache`, `DirectoryTemplateCache`. `@internal`: `parser/`
(`TemplateParser`, `TemplateNode`, `TextNode`, `TagNode`, `TemplateTreeBuilder`, `OpenTagFrame`), `compiler/`
(`TemplateCompiler`, `IfElseNode`), `runtime/` (`TemplateRuntime`, `TemplateLoader`, `TemplateScopes`,
`SelectorResolver`, `ValueComparator`, `ComparisonOperatorEnum`, `ValueFormatter`, `TrustedHtml`).

**Public signatures.**

- `TemplateEngine::__construct(TemplateCache $cache, TemplateTagCollection $tags, string $namespacePrefix = 'tst')`,
  `render(string $templateFile, TemplateData $data): string` (one engine per request; no static state).
- `TemplateData::__construct(array $values = [])`, `TemplateData::fromReplacements(HtmlReplacementCollection)`,
  `with(string $identifier, mixed $value): TemplateData`. `fromReplacements()` marks every string of the replacements as
  trusted HTML (`TrustedHtml`): `HtmlReplacement::getDataForRenderer()` has rendered `HtmlText`, the items of
  `HtmlTextCollection` and the strings of `HtmlDataObject` / `HtmlDataObjectCollection` already, so `HtmlDocument` pages
  stay byte-identical later. Plain strings in `new TemplateData(...)`, `ArrayObject` or objects are escaped.
- `TemplateTag`: `getName(): string`, `render(TemplateTagContext $context, array $attributes, ?Closure $body): string`
  (`$body` renders the children of `<tst:name>…</tst:name>`, `null` for inline tags and `<tst:name/>`).
- `TemplateTagContext`: `resolve(string $selector)`, `escape(mixed $value): string`, `text(mixed $value): string`
  (unescaped text for paths and comparisons), `renderTemplate(string $templateFile): string`,
  `requireAttribute(array $attributes, string $name): string`.
- `TemplateTagCollection::__construct(TemplateTag ...$tags)`, `createDefault(LocaleHandler, string $snippetsDirectory,
  Clock): TemplateTagCollection`, `with(TemplateTag $tag): TemplateTagCollection`, `find(string $name): ?TemplateTag`. A
  name that is used twice and the names `if`, `else`, `for` (compiled by the engine, `TemplateCompiler::NATIVE_TAGS`) throw an
  `InvalidArgumentException`.
- `TemplateCache`: `find(string $templateFile): ?string` (path of an up-to-date compiled file), `store(string
  $templateFile, string $compiledCode): string`. `DirectoryTemplateCache::__construct(string $cacheDirectory, string
  $templateBaseDirectory)`: file `<cache>/v<TemplateCompiler::FORMAT_VERSION>/<relative path>.php`, templates outside the base
  directory (or with `..`) under `external/<sha256 of the path>.php`; outdated when the template is as new or newer
  (`>=`, so an edit in the same second is not missed); atomic write (temporary file in the same directory +
  `rename()`), directories `0775`, files `0664`, `opcache_invalidate()` after a write.
- `TemplateException(string $reason, ?string $templateFile = null, ?int $templateLine = null, ?Throwable $previous =
  null)`, message `<reason> in <file> on line <n>`; the properties are `reason`, `templateFile`, `templateLine`;
  `withLocation()` returns a copy with file and line. Tags throw it without a location; `TemplateRuntime` adds the file and
  line of the tag (an exception that names a file already, e.g. from a sub-template, is kept).

**Design decisions in the code (not in design.md).**

- Parsing: HTML comments are matched first, so tags in comments stay text (as today). CDATA is not special. `<tst:name …>`
  needs a close tag, there are no self-closing tag names any more (see differences). An element tag with attributes in
  single quotes is not recognized and stays text (as today). Attribute values of element tags are trimmed (as today).
- Compiler: output is `echo 'literal';` (via `var_export()`), so a template line break after a tag is removed in the
  compiler (`\r\n`, `\n` or `\r`, like PHP after `?>`): after inline tags, self-closing tags, the opening tag of a body
  and every closing tag, except `</tst:if>` that is followed by `<tst:else>`. `if` + whitespace + `else` is joined in a
  grouping step (`IfElseNode`); anything else between them, or an `else` without `if`, is a `TemplateException`. `operator`
  is checked at compile time, missing `compare`/`against`/`var`/`value` too. Compiled code only uses `$runtime`
  (`compare()`, `iterate()`, `pushScope()`/`popScope()`, `renderTag()`), no `::`, no `new`, no `$this`.
- Execution is in `TemplateRuntime::renderTemplate()` (static closure with only the runtime, `ob_end_clean()` of every buffer the
  template opened when anything is thrown), not in `TemplateEngine`, because sub-templates (`loadSubTpl`, `snippet`) need
  the same runtime, scopes and loader. `TemplateEngine::render()` creates one runtime per call.
- Selector order: array / `ArrayAccess` key, public property, getter `get|is|has`+Ucfirst, public method; getters and
  methods must be public, non-static and without required arguments (checked with reflection, no `$object->$name`). The
  values of arrays and objects are untyped, so `mixed` appears in exactly these places (documented): `TemplateData`
  values, `TemplateScopes`, `SelectorResolver::narrow()`, `ValueFormatter`, `ValueComparator`, `TemplateTagContext::escape()`
  and `text()`, `TemplateRuntime::escape()` / `text()` / `pushScope()` / `iterate()`.
- `for` iterates arrays, `Traversable` (also generators), the public properties of an object and `null` (empty);
  everything else is a `TemplateException`.
- `lang`: `vars` is the selector of an array (`['name' => 'Anna']`), not a comma separated list of data names (design.md);
  a missing key is a `TemplateException`.
- `snippet`: lexical check (empty/absolute/`..`/backslash/NUL) plus `realpath()` containment, so a symbolic link out of the
  snippets directory is rejected too; a missing snippet is an exception for `.html` and other files.
- `options`: `selected` may be a single value or an array; every item is compared as text (a boolean is `'1'` / `''`, like
  in `text`); an item that is an array or object is an error; keys and labels are escaped, `TrustedHtml` labels are not.
- Values from `HtmlReplacementCollection::addInt()` arrive as `float` (`HtmlReplacement::getDataForRenderer()` has a float return
  type), the output is the same (`5.0` is output as `5`).

**Tests.** 3114 tests in total, 381 new unit tests: `TemplateParserTest` (27), `TemplateCompilerTest` (38), `SelectorResolverTest`
(31), `ValueComparatorTest` (98), `ValueFormatterTest` (29), `TemplateScopesTest` (5), `DirectoryTemplateCacheTest` of the new cache
(13), `TemplateDataTest` (9), `TemplateExceptionTest` (4), `TemplateEngineTest` (28), tag tests (99: `TextTagTest`,
`LoadSubTplTagTest`, `LangTagTest`, `SnippetTagTest`, `PrintTagTest`, `DateTagTest`, `OptionsTagTest`,
`TemplateTagCollectionTest`). Doubles in `tests/Double/template/`: `ShoutTag` (own tag with and without body),
`ThrowingTag`, `NamedTag`, `StringableValue`, `SelectorProbe`, `KeyedObject`, `NewEngineTestCase`, `NewTemplateRenderer`,
`TemplateWorkDirectory`, `TemplateRenderer` (interface).

**Characterization tests on both engines.** The eight classes of step 1 are now abstract (`AbstractTemplate…TestCase`; the file
name does not end in `Test.php`, so PHPUnit does not collect them) with one final subclass per engine
(`OldEngineTemplate…Test`, `NewEngineTemplate…Test`, only `isNewEngine()` differs). `TemplateRenderer` has
`OldTemplateRenderer` (now also registers `Core` and the `LocaleHandler` itself and resets the `LocaleHandler`) and
`NewTemplateRenderer` (fixed clock 2026-01-02 03:04:05; `HtmlReplacementCollection` → `TemplateData::fromReplacements()`, array /
`ArrayObject` → plain `TemplateData`). A test that pins a difference asks `forEngine(old:, new:)`, `expectEngineException()` or
`isNewEngine()` and states both results; nothing is skipped. 485 cases per engine, 970 in total: 326 cases of the new
engine have the same expected value as the old one (byte-identical, including pagination, table filter, example templates and
error pages), 159 differ on purpose (below).

**Intended differences (159 cases on the new engine).**

- `if` comparison matrix, 113 of 320 cells: `eq abc` / `eq 1` / `eq 0` and their `ne` no longer match `true` / `false` (a
  boolean has no text); `in` compares texts only (`null`, `false`, `true` are no texts; `in ''` only matches `''`);
  `gt`/`ge`/`lt`/`le` throw a `TemplateException` for every value or `against` that is not numeric (`null`, `''`, `[]`, booleans,
  `'abc'`), numbers and numeric strings compare numerically (24 rows differ).
- `if` attributes and syntax (8 cases): `operator` is optional (default `eq`); apostrophe in `against` works (was a `ParseError`);
  missing `against`, unknown operator, `else` without `if`, text between `</tst:if>` and `<tst:else>`, missing compare value and
  `compare="hasSnippet"` (now an ordinary missing value) are `TemplateException`s with file and line.
- Escaping and `print` (10 cases): plain string in `text` (inline, element) and `print` (inline, element), `{tst:text}` inside an
  attribute, `options` keys/labels, `print` of an array (escaped `print_r()`, inline, element); `print` of a `DateTimeImmutable` is
  formatted (inline, element).
- Syntax (7 cases): empty template renders `''`; mismatched close tag and `<?php` are `TemplateException`s; missing template file
  and unknown tag (element, inline) are `TemplateException`s; `<tst:text …></tst:text>` works (the old engine left
  `</tst:text>` in the output).
- Selectors (8 cases): all failing selectors and a missing top-level value throw `TemplateException`s with other messages (7);
  a getter without a property of that name now works (`computed`).
- `for` (2 cases): the loop variable never removes an outer value; `{i.html}` inside a `for` stays text (no raw echo).
- `loadSubTpl` (3 cases): missing data key is a `TemplateException` (was `TypeError`), inline form works; missing file has another
  message.
- `snippet` (2 cases): a name with `..` throws; a missing `.html` snippet has another message.
- `lang` (3 cases): missing key has another message; `vars='name'` with a string is a `TemplateException` (old: `Error: Class
  "LangTag" not found`); real `vars` are tested in `LangTagTest`.
- `date` inline works; `options` without `selected` works; `options` without `options` has another message (3 cases).
- Not a difference: the whitespace rules (line break after a tag removed, whitespace between `</tst:if>` and `<tst:else>` in the
  `if` branch) are implemented, so the pagination, table filter, example and error page outputs are byte-identical.

**Open points.**

- `<?xml … ?>` (also `<?`) in a template is rejected like `<?php`; a template that needs an XML declaration gets it from the view.
- `<tst:text value="x">` without `/` or a close tag is now "not closed" (no template of yuf, the example or `actra/backend` does
  this).
- `DirectoryTemplateCache` ignores the namespace prefix in the key (one engine uses one prefix).
- `HtmlReplacementCollection::addInt()` values arrive as `float`; harmless for output and for `eq`.
- The README does not describe the new engine yet (it is not used); step 5 adds the section.
- Step 4 must replace `tests/Double/template/OldTemplateRenderer`, the `Old…Test` subclasses and `CoreTestInstance`, and keep the
  `NewEngine…` classes as the only run of the characterization tests (then the `isNewEngine()` branches can be removed).

### Step 3 (v4.25.0) – done

Renames only; rendered HTML is unchanged (characterization tests of both engines pass with renamed setup calls).

- `HtmlText::fromHtml()` / `fromText()`, `HtmlReplacementCollection::addHtml()` / `addText()`,
  `HtmlReplacement::html()` / `text()` (now accept `null`), `HtmlDataObject::addHtml()` / `addText()` (replaces
  `addTextElement()`), `DetailDataObject` argument `isHtml`. No aliases; table and migration in `UPGRADE.md`.
- `HtmlReplacement::object(?stdClass)` is now `dataObject(?HtmlDataObject)` (decision: all object data passes through
  `HtmlDataObject`); `getDataForRenderer()` unwraps `->data`.
- `HtmlTextCollection`, `HtmlDataObjectCollection`, `HtmlEncoder` have no "encoded" names and are unchanged.
- The comment and values in `example/app/view/frontend/IndexView.php` use `addText()` and say so.
- Follow-up in `actra/backend`: rename the calls as in the follow-up list above (migration steps in `UPGRADE.md`), then
  review every `addHtml()` / `fromHtml()` for user data.

