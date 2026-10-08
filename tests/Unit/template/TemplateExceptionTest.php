<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template;

use actra\yuf\template\TemplateException;
use PHPUnit\Framework\TestCase;

final class TemplateExceptionTest extends TestCase
{
    public function testMessageWithoutLocation(): void
    {
        $exception = new TemplateException(reason: 'Something is wrong');

        $this->assertSame('Something is wrong', $exception->getMessage());
        $this->assertNull($exception->templateFile);
        $this->assertNull($exception->templateLine);
    }

    public function testMessageWithFileOnly(): void
    {
        $exception = new TemplateException(reason: 'Something is wrong', templateFile: 'page.html');

        $this->assertSame('Something is wrong in page.html', $exception->getMessage());
    }

    public function testMessageWithFileAndLine(): void
    {
        $exception = new TemplateException(reason: 'Something is wrong', templateFile: 'page.html', templateLine: 7);

        $this->assertSame('Something is wrong in page.html on line 7', $exception->getMessage());
        $this->assertSame('Something is wrong', $exception->reason);
        $this->assertSame('page.html', $exception->templateFile);
        $this->assertSame(7, $exception->templateLine);
    }

    public function testWithLocationKeepsTheReasonAndTheOriginalAsPrevious(): void
    {
        $original = new TemplateException(reason: 'Something is wrong');

        $located = $original->withLocation(templateFile: 'page.html', templateLine: 3);

        $this->assertSame('Something is wrong in page.html on line 3', $located->getMessage());
        $this->assertSame('Something is wrong', $located->reason);
        $this->assertSame($original, $located->getPrevious());
        $this->assertSame('Something is wrong', $original->getMessage());
    }


}
