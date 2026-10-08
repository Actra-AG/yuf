<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\runtime;

use actra\yuf\template\runtime\TrustedHtml;
use ArrayObject;
use PHPUnit\Framework\TestCase;

final class TrustedHtmlTest extends TestCase
{
    public function testStringIsTheHtml(): void
    {
        $this->assertSame('<b>x</b>', new TrustedHtml(html: '<b>x</b>')->html);
    }

    public function testFunctionRunsOnFirstReadOnlyAndOnce(): void
    {
        $calls = new ArrayObject();
        $trustedHtml = new TrustedHtml(
            html: static function () use ($calls): string {
                $calls[] = 'called';

                return '<i>lazy</i>';
            },
        );

        $this->assertCount(0, $calls);
        $this->assertSame('<i>lazy</i>', $trustedHtml->html);
        $this->assertSame('<i>lazy</i>', $trustedHtml->html);
        $this->assertCount(1, $calls);
    }
}
