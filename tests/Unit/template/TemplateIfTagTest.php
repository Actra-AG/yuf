<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\TemplateEngineTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The `if` and `else` tags (docs/template-engine/design.md, section 3.1).
 *
 * The comparison matrix pins the rules of `if`. Rows: operator and against attribute; columns: the compared values in
 * the order of values(); Y = the if block is rendered, N = the else block is rendered, E = a TemplateException.
 */
final class TemplateIfTagTest extends TemplateEngineTestCase
{
    private const string TEMPLATE = '<tst:if compare="v" operator="%s" against="%s">Y</tst:if><tst:else>N</tst:else>';

    /**
     * @return list<array{string, mixed}> label and value
     */
    private static function values(): array
    {
        return [
            ['null', null],
            ["''", ''],
            ['[]', []],
            ['false', false],
            ['true', true],
            ['0', 0],
            ['1', 1],
            ["'0'", '0'],
            ["'abc'", 'abc'],
            ['[1]', [1]],
        ];
    }

    /**
     * Operator, against, value, result: Y = the if branch is rendered, N = the else branch, E = a TemplateException.
     *
     * @return iterable<string, array{string, string, mixed, string}>
     */
    public static function comparisonProvider(): iterable
    {
        // The rules of design.md section 3.1.
        $matrix = [
            //                 null '' [] false true 0 1 '0' 'abc' [1]
            'eq' => [
                'null' => 'YYYYNYNNNN',
                '' => 'YYNYNNNNNN',
                'true' => 'NNNNYNYNYY',
                'false' => 'YYYYNYNYNN',
                'abc' => 'NNNNNNNNYN',
                '1' => 'NNNNNNYNNN',
                '0' => 'NNNNNYNYNN',
            ],
            'ne' => [
                'null' => 'NNNNYNYYYY',
                '' => 'NNYNYYYYYY',
                'true' => 'YYYYNYNYNN',
                'false' => 'NNNNYNYNYY',
                'abc' => 'YYYYYYYYNY',
                '1' => 'YYYYYYNYYY',
                '0' => 'YYYYYNYNYY',
            ],
            'gt' => [
                '0' => 'EEEEENYNEE',
                '1' => 'EEEEENNNEE',
                'abc' => 'EEEEEEEEEE',
            ],
            'ge' => [
                '0' => 'EEEEEYYYEE',
                '1' => 'EEEEENYNEE',
                'abc' => 'EEEEEEEEEE',
            ],
            'lt' => [
                '0' => 'EEEEENNNEE',
                '1' => 'EEEEEYNYEE',
                'abc' => 'EEEEEEEEEE',
            ],
            'le' => [
                '0' => 'EEEEEYNYEE',
                '1' => 'EEEEEYYYEE',
                'abc' => 'EEEEEEEEEE',
            ],
            'in' => [
                '' => 'NYNNNNNNNN',
                'true' => 'NNNNNNNNNN',
                'false' => 'NNNNNNNNNN',
                'abc' => 'NNNNNNNNYN',
                'a b' => 'NNNNNNNNNN',
                '1 0' => 'NNNNNYYYNN',
            ],
        ];

        foreach ($matrix as $operator => $rows) {
            foreach ($rows as $against => $results) {
                foreach (TemplateIfTagTest::values() as $index => [$label, $value]) {
                    yield $operator . ' against "' . $against . '" value ' . $label => [
                        $operator,
                        (string) $against,
                        $value,
                        $results[$index],
                    ];
                }
            }
        }
    }

    #[DataProvider('comparisonProvider')]
    public function testComparison(
        string $operator,
        string $against,
        mixed $value,
        string $expected,
    ): void {
        if ($expected === 'E') {
            $this->expectException(TemplateException::class);
        }

        $html = $this->render(
            source: sprintf(TemplateIfTagTest::TEMPLATE, $operator, $against),
            data: ['v' => $value],
        );

        $this->assertSame($expected, $html);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function caseInsensitiveProvider(): iterable
    {
        yield 'operator in upper case' => ['EQ', 'abc', 'Y'];
        yield 'operator in mixed case' => ['Ne', 'abc', 'N'];
        yield 'against TRUE' => ['eq', 'TRUE', 'Y'];
        yield 'against NULL' => ['eq', 'NULL', 'N'];
        yield 'against False' => ['ne', 'False', 'Y'];
    }

    #[DataProvider('caseInsensitiveProvider')]
    public function testOperatorAndKeywordsAreCaseInsensitive(string $operator, string $against, string $expected): void
    {
        $html = $this->render(
            source: sprintf(TemplateIfTagTest::TEMPLATE, $operator, $against),
            data: ['v' => 'abc'],
        );

        $this->assertSame($expected, $html);
    }

    public function testBlockWithoutElseIsRenderedOrSkipped(): void
    {
        $source = '<tst:if compare="v" operator="eq" against="a">Y</tst:if>';

        $this->assertSame('Y', $this->render(source: $source, data: ['v' => 'a']));
        $this->assertSame('', $this->render(source: $source, data: ['v' => 'b']));
    }

    public function testNestedIfAndElse(): void
    {
        $nested = '<tst:if compare="a" operator="eq" against="true"><tst:if compare="b" operator="eq" '
            . 'against="true">AB</tst:if><tst:else>A!B</tst:else></tst:if><tst:else>!A</tst:else>';

        $this->assertSame('A!B', $this->render(source: $nested, data: ['a' => true, 'b' => false]));
        $this->assertSame('AB', $this->render(source: $nested, data: ['a' => true, 'b' => true]));
        $this->assertSame('!A', $this->render(source: $nested, data: ['a' => false, 'b' => true]));
    }

    public function testCompareUsesDottedSelector(): void
    {
        $html = $this->render(
            source: '<tst:if compare="o.p" operator="eq" against="x">Y</tst:if>',
            data: ['o' => ['p' => 'x']],
        );

        $this->assertSame('Y', $html);
    }

    public function testMissingCompareValueThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="o" operator="eq" against="x">Y</tst:if>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "o" does not exist. Check that the view provides a replacement with this identifier in '
                . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testOperatorAttribute(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" against="a">Y</tst:if>');

        $this->assertSame('Y', $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']));
    }

    public function testAgainstAttributeIsRequired(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="eq">Y</tst:if>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Missing attribute "against" for the tag "if" in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testUnknownOperatorThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="xx" against="a">Y</tst:if>');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'Unknown operator "xx", valid are in, eq, ne, gt, ge, lt, le in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testApostropheInAgainst(): void
    {
        $html = $this->render(
            source: '<tst:if compare="v" operator="eq" against="it\'s">Y</tst:if>',
            data: ['v' => 'it\'s'],
        );

        $this->assertSame('Y', $html);
    }

    public function testElseWithoutPrecedingTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: 'a<tst:else>E</tst:else>b');

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in ' . $templateFile
                . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testTextBetweenIfAndElse(): void
    {
        $templateFile = $this->writeTemplate(
            source: '<tst:if compare="v" operator="eq" against="a">Y</tst:if>-<tst:else>E</tst:else>',
        );

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in ' . $templateFile
                . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testWhitespaceBetweenIfAndElseIsRenderedWithTheIfBranch(): void
    {
        $source = "<ul>\n    <tst:if compare=\"v\" operator=\"eq\" against=\"a\">\n        <li>Y</li>\n    </tst:if>\n"
            . "    <tst:else>\n        <li>N</li>\n    </tst:else>\n</ul>\n";

        // The line break right after a closing tag is swallowed by the PHP closing tag of the compiled code
        $this->assertSame(
            "<ul>\n            <li>Y</li>\n    \n    </ul>\n",
            $this->render(source: $source, data: ['v' => 'a']),
        );
        $this->assertSame(
            "<ul>\n            <li>N</li>\n    </ul>\n",
            $this->render(source: $source, data: ['v' => 'b']),
        );
    }

    public function testWhitespaceAroundIfWithoutElse(): void
    {
        $source = "<ul>\n    <tst:if compare=\"v\" operator=\"eq\" against=\"a\">\n        <li>Y</li>\n    "
            . "</tst:if>\n</ul>\n";

        $this->assertSame(
            "<ul>\n            <li>Y</li>\n    </ul>\n",
            $this->render(source: $source, data: ['v' => 'a']),
        );
        $this->assertSame("<ul>\n    </ul>\n", $this->render(source: $source, data: ['v' => 'b']));
    }

    public function testHasSnippet(): void
    {
        $source = '<tst:if compare="hasSnippet" operator="eq" against="%s">Y</tst:if><tst:else>N</tst:else>';
        $templateFile = $this->writeTemplate(source: sprintf($source, 'hello.html'));

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs(
            'The template data "hasSnippet" does not exist. Check that the view provides a replacement with this '
                . 'identifier in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }
}
