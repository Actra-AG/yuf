<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
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
abstract class AbstractTemplateIfTagTestCase extends TemplateCharacterizationTestCase
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
     * Operator, against, value, result of the old engine, result of the new engine: Y = the if branch is rendered,
     * N = the else branch, E = a TemplateException (new engine only).
     *
     * @return iterable<string, array{string, string, mixed, string, string}>
     */
    public static function comparisonProvider(): iterable
    {
        // Old: loose PHP comparison, the in operator splits the against attribute at spaces. against="null" with in is
        // not part of the matrix: explode() of null is deprecated in the old engine.
        // New: design.md section 3.1. A row has the result of the old engine and, where it differs, of the new engine.
        $matrix = [
            //                 null '' [] false true 0 1 '0' 'abc' [1]
            'eq' => [
                'null' => ['YYYYNYNNNN'],
                '' => ['YYNYNNNNNN'],
                'true' => ['NNNNYNYNYY'],
                'false' => ['YYYYNYNYNN'],
                'abc' => ['NNNNYNNNYN', 'NNNNNNNNYN'],
                '1' => ['NNNNYNYNNN', 'NNNNNNYNNN'],
                '0' => ['NNNYNYNYNN', 'NNNNNYNYNN'],
            ],
            'ne' => [
                'null' => ['NNNNYNYYYY'],
                '' => ['NNYNYYYYYY'],
                'true' => ['YYYYNYNYNN'],
                'false' => ['NNNNYNYNYY'],
                'abc' => ['YYYYNYYYNY', 'YYYYYYYYNY'],
                '1' => ['YYYYNYNYYY', 'YYYYYYNYYY'],
                '0' => ['YYYNYNYNYY', 'YYYYYNYNYY'],
            ],
            'gt' => [
                '0' => ['NNYNYNYNYY', 'EEEEENYNEE'],
                '1' => ['NNYNNNNNYY', 'EEEEENNNEE'],
                'abc' => ['NNYNNNNNNY', 'EEEEEEEEEE'],
            ],
            'ge' => [
                '0' => ['NNYYYYYYYY', 'EEEEEYYYEE'],
                '1' => ['NNYNYNYNYY', 'EEEEENYNEE'],
                'abc' => ['NNYNYNNNYY', 'EEEEEEEEEE'],
            ],
            'lt' => [
                '0' => ['YYNNNNNNNN', 'EEEEENNNEE'],
                '1' => ['YYNYNYNYNN', 'EEEEEYNYEE'],
                'abc' => ['YYNYNYYYNN', 'EEEEEEEEEE'],
            ],
            'le' => [
                '0' => ['YYNYNYNYNN', 'EEEEEYNYEE'],
                '1' => ['YYNYYYYYNN', 'EEEEEYYYEE'],
                'abc' => ['YYNYYYYYYN', 'EEEEEEEEEE'],
            ],
            'in' => [
                '' => ['YYNYNNNNNN', 'NYNNNNNNNN'],
                'true' => ['NNNNYNYNNN', 'NNNNNNNNNN'],
                'false' => ['YYNYNNNNNN', 'NNNNNNNNNN'],
                'abc' => ['NNNNYNNNYN', 'NNNNNNNNYN'],
                'a b' => ['NNNNYNNNNN', 'NNNNNNNNNN'],
                '1 0' => ['NNNYYYYYNN', 'NNNNNYYYNN'],
            ],
        ];

        foreach ($matrix as $operator => $rows) {
            foreach ($rows as $against => $results) {
                foreach (AbstractTemplateIfTagTestCase::values() as $index => [$label, $value]) {
                    yield $operator . ' against "' . $against . '" value ' . $label => [
                        $operator,
                        (string) $against,
                        $value,
                        $results[0][$index],
                        (array_key_exists(key: 1, array: $results) ? $results[1] : $results[0])[$index],
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
        string $expectedByOldEngine,
        string $expectedByNewEngine,
    ): void {
        $expected = $this->forEngine(old: $expectedByOldEngine, new: $expectedByNewEngine);
        if ($expected === 'E') {
            $this->expectException(TemplateException::class);
        }

        $html = $this->renderSource(
            source: sprintf(AbstractTemplateIfTagTestCase::TEMPLATE, $operator, $against),
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
            source: sprintf(AbstractTemplateIfTagTestCase::TEMPLATE, $operator, $against),
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

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'The data with offset "o" does not exist for template file ' . $templateFile . '. Check, if the correct BaseView class has been found/executed and set the correct replacements.',
            newMessage: 'The template data "o" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 1',
            oldCode: 1,
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testOperatorAttribute(): void
    {
        // Differs: the old engine requires the operator, in the new engine it defaults to eq
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" against="a">Y</tst:if>');
        if (!$this->isNewEngine()) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessageIs(
                'Error while processing the template file ' . $templateFile . ': Could not parse the template: Missing attribute \'operator\' for custom tag \'if\' in ' . $templateFile . ' on line 1',
            );
        }

        $this->assertSame('Y', $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']));
    }

    public function testAgainstAttributeIsRequired(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="eq">Y</tst:if>');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Error while processing the template file ' . $templateFile . ': Could not parse the template: Missing attribute \'against\' for custom tag \'if\' in ' . $templateFile . ' on line 1',
            newMessage: 'Missing attribute "against" for the tag "if" in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testUnknownOperatorThrows(): void
    {
        $templateFile = $this->writeTemplate(source: '<tst:if compare="v" operator="xx" against="a">Y</tst:if>');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Error while processing the template file ' . $templateFile . ': Unknown operator "xx"',
            newMessage: 'Unknown operator "xx", valid are in, eq, ne, gt, ge, lt, le in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']);
    }

    public function testApostropheInAgainst(): void
    {
        // Differs: the old engine puts the against attribute into a PHP literal without escaping (code injection,
        // design section 1), the new engine compiles it with var_export()
        if (!$this->isNewEngine()) {
            $this->expectException(ParseError::class);
        }

        $html = $this->renderSource(
            source: '<tst:if compare="v" operator="eq" against="it\'s">Y</tst:if>',
            data: ['v' => 'it\'s'],
        );

        $this->assertSame('Y', $html);
    }

    public function testElseWithoutPrecedingTagThrows(): void
    {
        $templateFile = $this->writeTemplate(source: 'a<tst:else>E</tst:else>b');

        $this->expectEngineException(
            oldClass: Exception::class,
            oldMessage: 'Error while processing the template file ' . $templateFile . ': There is no custom tag that can be followed by an ElseTag',
            newMessage: 'The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in ' . $templateFile . ' on line 1',
        );

        $this->renderFile(templateFile: $templateFile);
    }

    public function testTextBetweenIfAndElse(): void
    {
        // Differs: the old engine renders text between if and else with the if branch, the new engine only allows
        // whitespace (design section 2)
        $templateFile = $this->writeTemplate(
            source: '<tst:if compare="v" operator="eq" against="a">Y</tst:if>-<tst:else>E</tst:else>',
        );
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs('The tag <tst:else> must directly follow </tst:if> (only whitespace may be in between) in ' . $templateFile . ' on line 1');
        }

        $this->assertSame('Y-', $this->renderFile(templateFile: $templateFile, data: ['v' => 'a']));
        $this->assertSame('E', $this->renderFile(templateFile: $templateFile, data: ['v' => 'b']));
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

    public function testHasSnippet(): void
    {
        // Differs: the old engine checks the snippets directory for compare="hasSnippet" (removed, design section 4),
        // in the new engine it is an ordinary selector
        $source = '<tst:if compare="hasSnippet" operator="eq" against="%s">Y</tst:if><tst:else>N</tst:else>';
        $templateFile = $this->writeTemplate(source: sprintf($source, 'hello.html'));
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs(
                'The template data "hasSnippet" does not exist. Check that the view provides a replacement with this identifier in ' . $templateFile . ' on line 1',
            );
        }

        $this->assertSame('Y', $this->renderFile(templateFile: $templateFile));
        $this->assertSame('N', $this->renderSource(source: sprintf($source, 'missing.html')));
    }
}
