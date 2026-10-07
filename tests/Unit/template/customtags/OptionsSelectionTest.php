<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\customtags;

use actra\yuf\template\customtags\CustomTagsHelper;
use actra\yuf\template\customtags\OptionsTag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OptionsSelectionTest extends TestCase
{
    /**
     * @return iterable<string, array{int|string, array<int, mixed>, bool}>
     */
    public static function isSelectedProvider(): iterable
    {
        yield 'int key, string selection' => [1, ['1'], true];
        yield 'string key, int selection' => ['2', [2], true];
        yield 'string key, string selection' => ['a', ['a'], true];
        yield 'not selected' => [1, ['2', 3], false];
        yield 'empty selection' => [1, [], false];
        yield 'non-scalar selection' => [1, [[1]], false];
    }

    /**
     * @param array<int, mixed> $selection
     */
    #[DataProvider('isSelectedProvider')]
    public function testIsSelected(int|string $key, array $selection, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, CustomTagsHelper::isSelected(key: $key, selection: $selection));
    }

    public function testRenderOptionsSelectsIntKeyForStringSelection(): void
    {
        $html = OptionsTag::renderOptions(options: [1 => 'One', 2 => 'Two'], selection: ['2']);

        $this->assertSame(
            '<option value="1">One</option>' . PHP_EOL . '<option value="2" selected>Two</option>' . PHP_EOL,
            $html,
        );
    }
}
