<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\RandomMimeIdGenerator;
use PHPUnit\Framework\TestCase;

final class RandomMimeIdGeneratorTest extends TestCase
{
    public function testIdsConsistOfLettersAndDigitsOnly(): void
    {
        $id = new RandomMimeIdGenerator()->generate();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40,44}$/D', $id);
    }

    public function testEveryIdIsNew(): void
    {
        $generator = new RandomMimeIdGenerator();

        $this->assertNotSame($generator->generate(), $generator->generate());
    }
}
