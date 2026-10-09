# Design: template engine

The template engine in `src/template/` is rewritten behind the same template syntax. It is the largest remaining area
of [docs/plans/done/standard-migration/remaining.md](../standard-migration/remaining.md) (221 of 760 baseline entries) and the last
user of `Core::get()` and `LocaleHandler::get()` in compiled code.

Decisions of the user: rewrite with the same syntax; keep the used and working tags, remove the broken or
unused ones with documented replacements; escape output by default with a renamed replacement API; projects can add
their own tags (extension point).

## 1. Findings in today's engine

- Parsing: `HtmlDoc` matches `tst:` tags with regular expressions, does not check that close tags match, and parses only
  double-quoted attributes. The DOCTYPE branch is unreachable.
- Compiling: every tag replaces its node by PHP source. Attribute values are concatenated into single-quoted PHP literals
  without escaping `'` or `\` (code injection from template text into PHP). `<?php` in a template passes into the
  compiled file.
- Executing: the compiled file is `require`d inside `TemplateEngine::getResultAsHtml()`, so it shares `$this` and the
  local variables of the engine. Sub-templates share the engine state (data pool, current file). An exception leaves an
  output buffer open (`ob_clean()` instead of `ob_end_clean()`).
- Static calls in compiled code: `LangTag::getText()` → `LocaleHandler::get()`, `LoadSubTplTag::requireFile()`,
  `PrintTag::generateOutput()`, `OptionsTag::render()`, `FormComponentTag::render()`, `FormAddRemoveTag::render()`,
  `SnippetTag::requireFile()`. `IfTag` (`compare="hasSnippet"`) and `SnippetTag` write `Core::get()->snippetsDirectory`
  into the compiled file at compile time.
- Cache: invalidated only by the modification time of the template. There is no version key, so after a yuf upgrade
  old compiled files that call renamed methods stay valid and fail. Writes are not atomic; directories are created with
  `0777`.
- Broken tags: `elseif` (alternative syntax mixed with braces), `for2` with `else`, the `vars` attribute of `lang`
  (calls a missing method), `checkboxOptions` / `radioOptions` (call a missing method), `for` with `classfirst` /
  `classlast` but without `class` (invalid nested ternary), `checkbox` / `radio` with non-numeric values, `options`
  without `selected`, `formAddRemove` without `pool`.
- Escaping: by design, data is encoded when it enters the replacements (`addUnencodedText()` encodes,
  `addEncodedText()` takes data that is already HTML), so the engine stores only safe values and never escapes. The
  names describe the stored state instead of what the caller passes, and `HtmlReplacement::object()` lets a plain
  `stdClass` bypass the encoding. `tst:for` rewrites every `{word}` in its body
  (also in inline JavaScript or CSS) into a raw `echo`.

## 2. Supported syntax

Unchanged for existing templates, except the removed features in section 4.

- Inline tag: `{tst:name attr='value' …}`; attribute values in single quotes.
- Element tag: `<tst:name attr="value" …/>` or `<tst:name …>…</tst:name>`; attribute values in double quotes. A close
  tag that does not match the open tag is a syntax error (file and line in the message).
- Selector (attribute values that name data): `a.b.c`. The first part is a key of the template data; each further part
  is, in this order: an array or `ArrayAccess` key, a public property, a public getter `getB()` / `isB()` / `hasB()`
  (also without a property of that name), or a public method `b()` without arguments (today any public method is
  called by name). A missing value throws a `TemplateException` with the selector, file and line (as today, but with a specific
  exception).
- `{this}` in the `tplfile` attribute of `loadSubTpl` (a data key in braces) stays.
- Whitespace as today (the characterization tests compare byte by byte): a single line break directly after a tag is
  removed, the indentation before a tag stays, and whitespace between `</tst:if>` and `<tst:else>` belongs to the `if`
  branch.
- Text outside `tst:` tags is copied unchanged. `<?php`, `<?=` and `<?` in a template are a syntax error: templates are
  markup, not code.

## 3. Tags

| Tag | Attributes | Behaviour |
|:----|:-----------|:----------|
| `text` (inline, element) | `value` (selector) | Outputs the value escaped (section 5). |
| `if` | `compare` (selector), `operator` (`eq`, `ne`, `gt`, `ge`, `lt`, `le`, `in`; case-insensitive; default `eq`, today required), `against` (required) | Block, optionally followed by `else`. Comparison rules in section 3.1. |
| `else` | – | Must follow the closing `</tst:if>` with only whitespace in between; anything else is a syntax error. |
| `for` | `value` (selector of an iterable), `var` (name) | Repeats the body for each item; `var` is visible only inside the body and does not overwrite or delete an outer value with the same name. Nested loops work. |
| `loadSubTpl` | `tplfile` (path or `{key}`) | Renders another template with the same data. A missing key or file is a `TemplateException`. |
| `lang` (inline, element) | `key`, `vars` (optional, selector of an array) | Text of the `LocaleHandler` with `[NAME]` placeholders. The text is trusted (language files are project files and may contain HTML) and output as is; the values of `vars` are escaped. `vars` works again. |
| `snippet` (inline, element) | `name` | Renders a snippet of the snippets directory: `.html` as template, other files as file content. A name that leaves the snippets directory (`..`, absolute path) is an error. |
| `print` (inline, element) | `var` (selector) | Debug output of a value (scalars, `DateTimeInterface` as `Y-m-d H:i:s`, today only `DateTime`; arrays/objects as `print_r`), escaped. |
| `date` (inline, element) | `format` | Current date of the `Clock` in the given format (today `date()`, element only). |
| `options` | `options` (selector), `selected` (selector, optional; today required) | `<option>` elements (`<optgroup>` for nested arrays), keys and labels escaped; selection by string comparison. |

### 3.1 Comparison in `if`

Today `if` compares loosely (`==` / `!=`), and templates rely on it. The new rules are explicit and give the same
results as today for the `against` values used in yuf, the example and `actra/backend` (`null`, `""`, `true`,
`false`); the 320 cases of `TemplateIfTagTest` pin them. `ne` is always the inverse of `eq`.

| `against` | `eq` is true for the value |
|:----------|:---------------------------|
| `null` | `null`, `''`, `[]`, `false`, `0`, `0.0` (not `'0'`) |
| `""` | `null`, `''`, `false` (not `[]`, not `0`) |
| `true` | the value is truthy in PHP (`true`, non-zero numbers, non-empty strings except `'0'`, non-empty arrays) |
| `false` | the value is falsy in PHP (`null`, `''`, `'0'`, `0`, `0.0`, `[]`, `false`) |
| anything else | string comparison: the value is a string, `int`, `float` or `Stringable` and equals `against` as string (`1` equals `"1"`). Today's quirks with other types (e.g. `true` equals `"abc"`) are dropped. |

- `gt`, `ge`, `lt`, `le`: numeric comparison; a non-numeric value or `against` is a `TemplateException` (today PHP's
  comparison of mixed types, unused).
- `in`: the value equals (string comparison) one of the items of `against`, split at spaces as today.
- Unknown operator: `TemplateException`.

## 4. Removed (⚠️, with replacements in `UPGRADE.md`)

| Removed | Replacement |
|:--------|:------------|
| `elseif` | nested `if` / `else` (was broken) |
| `for2`, `forgroup` | `for`; groups and counters prepared by the view |
| `option`, `checkbox`, `radio`, `checkboxOptions`, `radioOptions`, `formComponent`, `formAddRemove` | the form components of `src/form/` (rendered by the view and added as HTML) |
| `if compare="hasSnippet"` | a boolean from the view |
| `for` attributes `groups`, `classfirst`, `classlast`, `class`, the `_count` value | prepared by the view |
| raw `{var}`, `{var.prop}`, `${var.prop}` inside `for` | `{tst:text value='var.prop'}` (escaped) |
| selectors with method calls and arguments (`a.method(x)`) | a getter or a value prepared by the view |
| PHP code in templates | values from the view |

None of these is used by yuf's own templates, the example or `actra/backend`.

## 5. Escaping

- Template data are the values of `HtmlReplacementCollection` (and the items of `for` loops).
- `text`, `print`, `options` and the `vars` values of `lang` escape every string, `int`, `float` and `Stringable` with
  `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`. `null` is output as `''`, a boolean as today (`'1'` /
  `''`). Arrays and other objects are a `TemplateException`.
- Of the template data, only an `HtmlText` created from HTML is output as is. The replacement API says what it does:
  - `HtmlText::fromText(string $text)` (escaped when rendered, was `unencoded()`), `HtmlText::fromHtml(string $html)`
    (trusted HTML, was `encoded()`).
  - `HtmlReplacementCollection::addText()` (was `addUnencodedText()`), `addHtml()` (was `addEncodedText()`),
    `addHtmlText()` unchanged.
  - The same naming for `HtmlDataObject` and the collections (inventory in the plan).
- Trusted as is: `HtmlText` created from HTML, the texts of language files (`lang`) and non-`.html` snippets.
- Escaping is for HTML text and quoted attribute values. Values in `<script>` or `<style>` are not escaped for these
  contexts; use `data-*` attributes or JSON prepared by the view (documented in the README).

## 6. Architecture

All classes `final`; the tag interface is the only extension point.

- `TemplateParser` (pure): template source + file name → tree of `TemplateNode` value objects (`TextNode`,
  `TagNode` with name, attributes, children, line).
- `TemplateCompiler` (pure): tree → PHP source. The compiled code uses only the variable `$runtime` and literals created
  with `var_export()`; control flow (`if`, `else`, `for`) is compiled to PHP blocks that call runtime helpers
  (`$runtime->resolve()`, `$runtime->compare()`, `$runtime->pushScope()` / `popScope()`); every other tag (built-in or
  own) is compiled to `$runtime->renderTag('name', [attributes], line, body)`.
- `TemplateRuntime`: holds the data scopes, resolves selectors, renders tags, escapes. Executed compiled files run in
  a static closure (`static fn (TemplateRuntime $runtime) => require $file`), so they cannot reach engine internals.
- `TemplateTag` (interface, extension point): `getName(): string` and
  `render(TemplateTagContext $context, array<string, string> $attributes, ?Closure $body): string` (returns HTML; the
  context offers `resolve(selector)` and `escape(value)`). Built-in tags implement it and get their dependencies through
  the constructor: `LangTag(LocaleHandler)`, `SnippetTag(snippetsDirectory, …)`, `DateTag(Clock)`.
- `TemplateTagCollection`: the built-in tags plus the project's own; a name that is already registered is an error
  (own tags cannot replace built-ins).
- `TemplateCache` (interface) with `DirectoryTemplateCache`: the cache key contains the template path and
  `TemplateCompiler::FORMAT_VERSION` (changed with every change of the compiled code), so an upgrade never executes an
  old compiled file. Atomic writes (temporary file + `rename()`), directories with `0775`.
- `TemplateEngine`: `__construct(TemplateCache $cache, TemplateTagCollection $tags)`,
  `render(string $templateFile, TemplateData $data): string`. Errors are `TemplateException` (syntax: file and line).
- Wiring: `Core::prepareHttpResponse(templateTags: …)` takes the project's own tags; `Core` creates one
  `TemplateEngine` per request (with the `LocaleHandler`, the snippets directory and the clock) and passes it to
  `ContentHandler` / `HtmlDocument`, `ExceptionHandler` and the views (`ViewContext::$templateEngine`).
  `HtmlSnippet::render()`, `Pagination` and `TableFilter` get the engine as argument (⚠️). `Core::get()`,
  `LocaleHandler::get()` and `tests/Double/CoreTestInstance` are removed.

## 7. Decided details

- Booleans in `{tst:text}` are output as today (`'1'` / `''`).
- Snippets that are not `.html` files (e.g. inline SVG) are output as is; a name that leaves the snippets directory is
  an error.
- `else` after `for` is not supported.
