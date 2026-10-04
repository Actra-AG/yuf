<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\model;

use actra\yuf\form\model\UploadedFile;
use PHPUnit\Framework\TestCase;

final class UploadedFileTest extends TestCase
{
    public function testValuesAreReadable(): void
    {
        $file = new UploadedFile(name: 'a.txt', type: 'text/plain', size: 5, path: '/tmp/x/php1');

        $this->assertSame('a.txt', $file->name);
        $this->assertSame('text/plain', $file->type);
        $this->assertSame(5, $file->size);
        $this->assertSame('/tmp/x/php1', $file->path);
    }

    public function testHashIsTheSha1OfThePath(): void
    {
        $file = new UploadedFile(name: 'a.txt', type: 'text/plain', size: 5, path: '/tmp/x/php1');

        $this->assertSame(sha1(string: '/tmp/x/php1'), $file->getHash());
    }

    public function testHashDoesNotDependOnTheNameOrTheSize(): void
    {
        $first = new UploadedFile(name: 'a.txt', type: 'text/plain', size: 5, path: '/tmp/x/php1');
        $second = new UploadedFile(name: 'b.pdf', type: 'application/pdf', size: 7, path: '/tmp/x/php1');

        $this->assertSame($first->getHash(), $second->getHash());
    }
}