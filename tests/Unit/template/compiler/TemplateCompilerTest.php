<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\compiler;

use actra\yuf\template\compiler\TemplateCompiler;
use actra\yuf\template\parser\TemplateParser;
use actra\yuf\template\TemplateException;
use ParseError;
use PhpToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TemplateCompilerTest extends TestCase
{
    private function compile(string $source): string
    {
        return new TemplateCompiler()->compile(
            nodes: new TemplateParser()->parse(source: $source, templateFile: 'page.html'),
            templateFile: 'page.html',
        );
    }

    /**
     * @return list<int> the token ids of the compiled code (throws a ParseError if the code is not valid PHP)
     */
    private function tokenIds(string $code): array
    {
        return array_values(
            array: array_map(
                callback: static fn(PhpToken $token): int => $token->id,
                array: PhpToken::tokenize(code: $code, flags: TOKEN_PARSE),
            ),
        );
    }

    public function testCompiledCodeStartsWithStrictTypesAndTheFormatVersion(): void
    {
        $code = $this->compile(source: 'text');

        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $code);
        $this->assertStringContainsString('format version ' . TemplateCompiler::FORMAT_VERSION, $code);
    }

    public function testTextBecomesAnEchoOfALiteral(): void
    {
        $this->assertStringEndsWith("echo 'a <b>x</b>';\n", $this->compile(source: 'a <b>x</b>'));
    }

    public function testEmptyTemplateCompilesToNoStatement(): void
    {
        $this->assertStringEndsNotWith(";\n", $this->compile(source: ''));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostileTextProvider(): iterable
    {
        yield 'apostrophe' => ["it's"];
        yield 'backslash' => ['a\\b\\'];
        yield 'closing PHP tag' => ['a ?> b'];
        yield 'PHP string terminators' => ["'; echo 'x'; // \" \\' "];
        yield 'dollar and interpolation' => ['$a {$b} ${c}'];
        yield 'null byte' => ["a\0b"];
        yield 'static call' => ['std::cout'];
    }

    #[DataProvider('hostileTextProvider')]
    public function testTextIsOnlyEverALiteral(string $text): void
    {
        $code = $this->compile(source: $text . "{tst:text value='x'}");

        $tokens = $this->tokenIds(code: $code);
        $this->assertCount(1, array_keys(array: $tokens, filter_value: T_OPEN_TAG, strict: true));
        $this->assertNotContains(T_INLINE_HTML, $tokens);
        $this->assertNotContains(T_CLOSE_TAG, $tokens);
    }

    #[DataProvider('hostileTextProvider')]
    public function testAttributeValuesAreOnlyEverLiterals(string $value): void
    {
        $value = str_replace(search: ['"', "'"], replace: '', subject: $value);
        $code = $this->compile(source: '<tst:custom a="' . $value . '"/><tst:if compare="' . $value . '" against="' . $value . '">x</tst:if>');

        $tokens = $this->tokenIds(code: $code);
        $this->assertCount(1, array_keys(array: $tokens, filter_value: T_OPEN_TAG, strict: true));
        $this->assertNotContains(T_CLOSE_TAG, $tokens);
    }

    public function testAttributeWithApostropheAndBackslashIsCompiledToAValidLiteral(): void
    {
        $code = $this->compile(source: "{tst:custom a='x\\y' b='?>'}<tst:if compare=\"v\" against=\"it's\">x</tst:if>");

        $this->tokenIds(code: $code);
        $this->assertStringContainsString("'a' => 'x\\\\y'", $code);
        $this->assertStringContainsString("'b' => '?>'", $code);
        $this->assertStringContainsString("'it\\'s'", $code);
    }

    public function testCompiledCodeHasNoStaticCallsAndNoDynamicCode(): void
    {
        $source = <<<'TPL'
            <p>{tst:text value='a'}</p>
            <tst:if compare="a" operator="ne" against="b">
                <tst:for value="list" var="item">
                    <tst:lang key="k" vars="v"/>{tst:snippet name='s.html'}
                    <tst:options options="o" selected="s"/>
                </tst:for>
            </tst:if>
            <tst:else>
                <tst:loadSubTpl tplfile="{this}"/>
                <tst:date format="Y"/>{tst:print var='a'}
            </tst:else>
            <tst:custom>body</tst:custom>
            TPL;

        $code = $this->compile(source: $source);

        $this->assertStringNotContainsString('::', $code);
        $this->assertStringNotContainsString('new ', $code);
        $this->assertStringNotContainsString('eval', $code);
        $this->assertStringNotContainsString('require', $code);
        $this->assertStringNotContainsString('include', $code);
        $this->assertSame(0, preg_match(pattern: '/\$(?!runtime\b|item\b)\w+/', subject: $code));
        $this->tokenIds(code: $code);
    }

    public function testInlineAndElementTagsBecomeRenderTagCalls(): void
    {
        $code = $this->compile(source: "{tst:text value='a.b'}\n<tst:lang key=\"k\"/>");

        $this->assertStringContainsString("echo \$runtime->renderTag('text', ['value' => 'a.b'], 1, null);\n", $code);
        $this->assertStringContainsString("echo \$runtime->renderTag('lang', ['key' => 'k'], 2, null);\n", $code);
    }

    public function testElementTagWithChildrenGetsABodyClosure(): void
    {
        $code = $this->compile(source: '<tst:custom x="1">in</tst:custom>');

        $this->assertStringContainsString("echo \$runtime->renderTag('custom', ['x' => '1'], 1, static function () use (\$runtime): string {\n", $code);
        $this->assertStringContainsString("    ob_start();\n    echo 'in';\n    return (string) ob_get_clean();\n});\n", $code);
    }

    public function testIfWithElse(): void
    {
        $code = $this->compile(source: '<tst:if compare="p" operator="GT" against="1">Y</tst:if><tst:else>N</tst:else>');

        $this->assertStringContainsString("if (\$runtime->compare('p', 'gt', '1', 1)) {\n    echo 'Y';\n} else {\n    echo 'N';\n}\n", $code);
    }

    public function testIfWithoutElseAndDefaultOperator(): void
    {
        $code = $this->compile(source: '<tst:if compare="p" against="a">Y</tst:if>');

        $this->assertStringContainsString("if (\$runtime->compare('p', 'eq', 'a', 1)) {\n    echo 'Y';\n}\n", $code);
        $this->assertStringNotContainsString('else', $code);
    }

    public function testForPushesAndPopsAScope(): void
    {
        $code = $this->compile(source: '<tst:for value="l" var="i">x</tst:for>');

        $this->assertStringContainsString(
            "foreach (\$runtime->iterate('l', 1) as \$item) {\n    \$runtime->pushScope('i', \$item);\n    echo 'x';\n    \$runtime->popScope();\n}\n",
            $code,
        );
    }

    public function testLineBreakDirectlyAfterATagIsRemoved(): void
    {
        $code = $this->compile(source: "{tst:text value='x'}\nb\n<tst:lang key=\"k\"/>\r\nc{tst:text value='y'}\n\nd");

        $this->assertStringContainsString("echo 'b\n';", $code);
        $this->assertStringContainsString("echo 'c';", $code);
        $this->assertStringContainsString("echo '\nd';", $code);
    }

    public function testLineBreakAfterTheOpeningAndClosingTagOfABlockIsRemoved(): void
    {
        $code = $this->compile(source: "<tst:for value=\"l\" var=\"i\">\nx\n</tst:for>\ny");

        $this->assertStringContainsString("echo 'x\n';", $code);
        $this->assertStringContainsString("echo 'y';", $code);
    }

    public function testWhitespaceBetweenIfAndElseBelongsToTheIfBranch(): void
    {
        $code = $this->compile(source: "<tst:if compare=\"v\" against=\"a\">Y</tst:if>\n  <tst:else>N</tst:else>\nz");

        $this->assertStringContainsString("echo 'Y';\n    echo '\n  ';\n} else {\n    echo 'N';\n}\necho 'z';\n", $code);
    }

    public function testWhitespaceAfterAnIfWithoutElseIsPlainText(): void
    {
        $code = $this->compile(source: "<tst:if compare=\"v\" against=\"a\">Y</tst:if>\n  z");

        $this->assertStringContainsString("}\necho '  z';\n", $code);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function syntaxErrorProvider(): iterable
    {
        yield 'if without compare' => ['<tst:if against="a">x</tst:if>', 'Missing attribute "compare" for the tag "if" in page.html on line 1'];
        yield 'if without against' => ['<tst:if compare="a">x</tst:if>', 'Missing attribute "against" for the tag "if" in page.html on line 1'];
        yield 'unknown operator' => [
            "\n<tst:if compare=\"a\" operator=\"like\" against=\"b\">x</tst:if>",
            'Unknown operator "like", valid are in, eq, ne, gt, ge, lt, le in page.html on line 2',
        ];
        yield 'for without value' => ['<tst:for var="i">x</tst:for>', 'Missing attribute "value" for the tag "for" in page.html on line 1'];
        yield 'for without var' => ['<tst:for value="l">x</tst:for>', 'Missing attribute "var" for the tag "for" in page.html on line 1'];
        yield 'else without if' => [
            'a<tst:else>x</tst:else>',
            'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in page.html on line 1',
        ];
        yield 'else after text' => [
            '<tst:if compare="a" against="b">x</tst:if>y<tst:else>z</tst:else>',
            'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in page.html on line 1',
        ];
        yield 'else after for' => [
            '<tst:for value="l" var="i">x</tst:for><tst:else>z</tst:else>',
            'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in page.html on line 1',
        ];
        yield 'second else' => [
            '<tst:if compare="a" against="b">x</tst:if><tst:else>y</tst:else><tst:else>z</tst:else>',
            'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in page.html on line 1',
        ];
    }

    #[DataProvider('syntaxErrorProvider')]
    public function testSyntaxErrorThrows(string $source, string $expectedMessage): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs($expectedMessage);

        $this->compile(source: $source);
    }



    public function testTokenCheckRejectsInvalidCode(): void
    {
        // Guards the helper of this test class: it must throw for code that is not valid PHP
        $this->expectException(ParseError::class);

        $this->tokenIds(code: "<?php\necho 'a;\n");
    }
}
