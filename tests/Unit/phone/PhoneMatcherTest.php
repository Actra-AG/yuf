<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\phone;

use actra\yuf\phone\PhoneMatcher;
use PHPUnit\Framework\TestCase;

final class PhoneMatcherTest extends TestCase
{
    public function testFindLocatesTheFirstMatchAnywhere(): void
    {
        $matcher = new PhoneMatcher(pattern: '(\d+)-(\d+)', subject: 'ab 12-345 cd');

        $this->assertTrue($matcher->find());
        $this->assertSame(3, $matcher->start());
        $this->assertSame(9, $matcher->end());
        $this->assertSame('12-345', $matcher->group(group: 0));
        $this->assertSame('12', $matcher->group(group: 1));
        $this->assertSame('345', $matcher->group(group: 2));
        $this->assertNull($matcher->group(group: 3));
        $this->assertSame(2, $matcher->groupCount());
    }

    public function testFindWithoutMatchLeavesTheMatcherEmpty(): void
    {
        $matcher = new PhoneMatcher(pattern: 'x', subject: 'y');

        $this->assertFalse($matcher->find());
        $this->assertNull($matcher->start());
        $this->assertNull($matcher->end());
        $this->assertNull($matcher->group(group: 0));
        $this->assertNull($matcher->groupCount());
    }

    public function testLookingAtMatchesOnlyAtTheStart(): void
    {
        $this->assertTrue(new PhoneMatcher(pattern: '(\d+)', subject: '12ab')->lookingAt());
        $this->assertFalse(new PhoneMatcher(pattern: '(\d+)', subject: 'ab12')->lookingAt());
    }

    public function testLookingAtReportsTheEndOfTheMatch(): void
    {
        $matcher = new PhoneMatcher(pattern: '(\d+)', subject: '12ab');

        $matcher->lookingAt();

        $this->assertSame(0, $matcher->start());
        $this->assertSame(2, $matcher->end());
        $this->assertSame('12', $matcher->group(group: 1));
    }

    public function testMatchesRequiresTheWholeSubject(): void
    {
        $this->assertTrue(new PhoneMatcher(pattern: '(\d+)', subject: '12')->matches());
        $this->assertFalse(new PhoneMatcher(pattern: '(\d+)', subject: '12ab')->matches());
        $this->assertFalse(new PhoneMatcher(pattern: '(\d+)', subject: 'ab12')->matches());
    }

    public function testMatchesTakesTheLongestAlternativeOfThePatternAtTheStart(): void
    {
        $this->assertFalse(new PhoneMatcher(pattern: '\d|\d\d', subject: '12')->matches());
    }

    public function testPatternMatchesCaseInsensitively(): void
    {
        $this->assertTrue(new PhoneMatcher(pattern: 'abc', subject: 'ABC')->matches());
    }

    public function testSlashInThePatternNeedsNoEscaping(): void
    {
        $matcher = new PhoneMatcher(pattern: 'a/b', subject: 'xa/b');

        $this->assertTrue($matcher->find());
        $this->assertSame(1, $matcher->start());
    }

    public function testOffsetsAreCharacterOffsets(): void
    {
        $matcher = new PhoneMatcher(pattern: 'ö', subject: 'äö');

        $this->assertTrue($matcher->find());
        $this->assertSame(1, $matcher->start());
        $this->assertSame(2, $matcher->end());
    }

    public function testUnmatchedOptionalGroupIsNull(): void
    {
        $matcher = new PhoneMatcher(pattern: '(ä)(x)?', subject: 'äö');

        $this->assertTrue($matcher->find());
        $this->assertSame('ä', $matcher->group(group: 1));
        $this->assertNull($matcher->group(group: 2));
        $this->assertSame(1, $matcher->groupCount());
    }

    public function testReplaceFirstReplacesOnlyTheFirstMatch(): void
    {
        $matcher = new PhoneMatcher(pattern: '(\d)(\d)', subject: '12 34');

        $this->assertSame('21 34', $matcher->replaceFirst(replacement: '$2$1'));
    }

    public function testReplaceAllReplacesEveryMatch(): void
    {
        $matcher = new PhoneMatcher(pattern: '(\d)(\d)', subject: '12 34');

        $this->assertSame('2-1 4-3', $matcher->replaceAll(replacement: '$2-$1'));
    }

    public function testReplaceWithoutMatchReturnsTheSubject(): void
    {
        $matcher = new PhoneMatcher(pattern: 'x', subject: '12');

        $this->assertSame('12', $matcher->replaceAll(replacement: 'y'));
        $this->assertSame('12', $matcher->replaceFirst(replacement: 'y'));
    }
}
