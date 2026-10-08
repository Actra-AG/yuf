<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\tests\Double\template\TemplateCharacterizationTestCase;
use Exception;
use ParseError;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Characterization of the template engine before the rewrite (docs/template-engine/plan.md, step 1).
 *
 * The comparison matrix pins the loose PHP comparison (==, !=, >, >=, <, <=, in_array()) that today's engine applies
 * to the value and the against attribute. Rows: operator and against attribute; columns: the compared values in the
 * order of values(); Y = the if block is rendered, N = the else block is rendered.
 */
final class TemplateIfTagTest extends TemplateCharacterizationTestCase
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
     * @return iterable<string, array{string, string, mixed, string}>
     */
    public static function comparisonProvider(): iterable
    {
        // The in operator splits the against attribute at spaces (the design says commas) and compares loosely.
        // against="null" with in is not part of the matrix: explode() of null is deprecated.
        $matrix = [
            //                 null '' [] false true 0 1 '0' 'abc' [1]
            'eq' => [
                'null' => 'YYYYNYNNNN',
                '' => 'YYNYNNNNNN',
                'true' => 'NNNNYNYNYY',
                'false' => 'YYYYNYNYNN',
                'abc' => 'NNNNYNNNYN',
                '1' => 'NNNNYNYNNN',
                '0' => 'NNNYNYNYNN',
            ],
            'ne' => [
                'null' => 'NNNNYNYYYY',
                '' => 'NNYNYYYYYY',
                'true' => 'YYYYNYNYNN',
                'false' => 'NNNNYNYNYY',
                'abc' => 'YYYYNYYYNY',
                '1' => 'YYYYNYNYYY',
                '0' => 'YYYNYNYNYY',
            ],
            'gt' => [
                '0' => 'NNYNYNYNYY',
                '1' => 'NNYNNNNNYY',
                'abc' => 'NNYNNNNNNY',
            ],
            'ge' => [
                '0' => 'NNYYYYYYYY',
                '1' => 'NNYNYNYNYY',
                'abc' => 'NNYNYNNNYY',
            ],
            'lt' => [
                '0' => 'YYNNNNNNNN',
                '1' => 'YYNYNYNYNN',
                'abc' => 'YYNYNYYYNN',
            ],
            'le' => [
                '0' => 'YYNYNYNYNN',
                '1' => 'YYNYYYYYNN',
                'abc' => 'YYNYYYYYYN',
            ],
            'in' => [
                '' => 'YYNYNNNNNN',
                'true' => 'NNNNYNYNNN',
                'false' => 'YYNYNNNNNN',
                'abc' => 'NNNNYNNNYN',
                'a b' => 'NNNNYNNNNN',
                '1 0' => 'NNNYYYYYNN',
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
    public function testComparison(string $operator, string $against, mixed $value, string $expected): void
    {
        $html = $this->renderSource(
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
        $html = $this->renderSource(
            source: sprintf(TemplateIfTagTest::TEMPLATE, $operator, $against),
            data: ['v' => 'abc'],
        );

        $this->assertSame($expected, $html);
    }

    public function testBlockWithoutElseIsRenderedOrSkipped(): void
    {
        $source = '<tst:if compare="v" operator="eq" against="a">Y</tst:if>';

        $this->assertSame('Y', $this->renderSource(source: $source, data: ['v' => 'a']));
        $this->assertSame('', $this->renderSource(source: $source, data: ['v' => 'b']));
    }

    public function testNestedIfAndElse(): void
    {
        $nested = '<tst:if compare="a" operator="eq" against="true"><tst:if compare="b" operator="eq" against="true">AB</tst:if><tst:else>A!B</tst:else></tst:if><tst:else>!A</tst:else>';

        $this->assertSame('A!B', $this->renderSource(source: $nested, data: ['a' => true, 'b' => false]));
        $this->assertSame('AB', $this->renderSource(source: $nested, data: ['a' => true, 'b' => true]));
        $this->assertSame('!A', $this->renderSource(source: $nested, data: ['a' => false, 'b' => true]));
    }

    public function testCompareUsesDottedSelector(): void
    {
        $html = $this->renderSource(
            source: '<tst:if compare="o.p" operator="eq" against="x">Y</tst:if>',
            data: ['o' => ['p' => 'x']],
        );

        $this->assertSame('Y', $html);
    }

    public function testMissingCompareValueThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="o" operator="eq" against="x">Y</tst:if>');

        $this->expectException(Exception::class);
        $this->expectExceptionCode(1);
        $this->expectExceptionMessageIs(
            'The data with offset "o" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    /**
     * Differs from docs/template-engine/design.md: the operator has no default today.
     */
    public function testOperatorAttributeIsRequired(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" against="a">Y</tst:if>');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': Could not parse the template: Missing attribute \'operator\' for custom tag \'if\' in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testAgainstAttributeIsRequired(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="eq">Y</tst:if>');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': Could not parse the template: Missing attribute \'against\' for custom tag \'if\' in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testUnknownOperatorThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="xx" against="a">Y</tst:if>');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': Unknown operator "xx"',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    /**
     * Code injection: the against attribute ends up in a single-quoted PHP literal without escaping. Fixed by the
     * rewrite (design section 1).
     */
    public function testApostropheInAgainstBreaksTheCompiledCode(): void
    {
        $this->expectException(ParseError::class);

        $this->renderSource(
            source: '<tst:if compare="v" operator="eq" against="it\'s">Y</tst:if>',
            data: ['v' => 'it\'s'],
        );
    }

    public function testElseWithoutPrecedingTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: 'a<tst:else>E</tst:else>b');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageIs(
            'Error while processing the template file ' . $templateFile . ': There is no custom tag that can be followed by an ElseTag',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testTextBetweenIfAndElseBelongsToTheIfBranch(): void
    {
        // Differs from docs/template-engine/design.md: the design only allows whitespace between if and else
        $source = '<tst:if compare="v" operator="eq" against="a">Y</tst:if>-<tst:else>E</tst:else>';

        $this->assertSame('Y-', $this->renderSource(source: $source, data: ['v' => 'a']));
        $this->assertSame('E', $this->renderSource(source: $source, data: ['v' => 'b']));
    }

    public function testWhitespaceBetweenIfAndElseIsRenderedWithTheIfBranch(): void
    {
        $source = "<ul>\n    <tst:if compare=\"v\" operator=\"eq\" against=\"a\">\n        <li>Y</li>\n    </tst:if>\n"
            . "    <tst:else>\n        <li>N</li>\n    </tst:else>\n</ul>\n";

        // The line break right after a closing tag is swallowed by the PHP closing tag of the compiled code
        $this->assertSame("<ul>\n            <li>Y</li>\n    \n    </ul>\n", $this->renderSource(source: $source, data: ['v' => 'a']));
        $this->assertSame("<ul>\n            <li>N</li>\n    </ul>\n", $this->renderSource(source: $source, data: ['v' => 'b']));
    }

    public function testWhitespaceAroundIfWithoutElse(): void
    {
        $source = "<ul>\n    <tst:if compare=\"v\" operator=\"eq\" against=\"a\">\n        <li>Y</li>\n    </tst:if>\n</ul>\n";

        $this->assertSame("<ul>\n            <li>Y</li>\n    </ul>\n", $this->renderSource(source: $source, data: ['v' => 'a']));
        $this->assertSame("<ul>\n    </ul>\n", $this->renderSource(source: $source, data: ['v' => 'b']));
    }

    public function testHasSnippetChecksTheSnippetsDirectory(): void
    {
        // Removed by the rewrite (design section 4); compare="hasSnippet" ignores the data
        $source = '<tst:if compare="hasSnippet" operator="eq" against="%s">Y</tst:if><tst:else>N</tst:else>';

        $this->assertSame('Y', $this->renderSource(source: sprintf($source, 'hello.html')));
        $this->assertSame('N', $this->renderSource(source: sprintf($source, 'missing.html')));
    }
}
