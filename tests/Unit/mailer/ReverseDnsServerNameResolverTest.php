<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\common\FileCache;
use actra\yuf\mailer\ReverseDnsServerNameResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReverseDnsServerNameResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{string|false, string}>
     */
    public static function lookupResultProvider(): iterable
    {
        yield 'host name' => ['mail.example.com', 'mail.example.com'];
        yield 'single label' => ['localhost', 'localhost'];
        yield 'failed lookup' => [false, '192.0.2.1'];
        yield 'the address itself' => ['192.0.2.1', '192.0.2.1'];
        yield 'empty' => ['', '192.0.2.1'];
        yield 'line break' => ["mail.example.com\r\nMAIL FROM:<x@example.com>", '192.0.2.1'];
        yield 'trailing line break' => ["mail.example.com\n", '192.0.2.1'];
        yield 'space' => ['mail example.com', '192.0.2.1'];
        yield 'leading hyphen' => ['-mail.example.com', '192.0.2.1'];
        yield 'angle brackets' => ['<mail>.example.com', '192.0.2.1'];
        yield 'too long' => [str_repeat(string: 'a', times: 300), '192.0.2.1'];
    }

    #[DataProvider('lookupResultProvider')]
    public function testChoosesAPlainHostNameOrTheAddress(string|false $lookupResult, string $expected): void
    {
        $this->assertSame(
            $expected,
            ReverseDnsServerNameResolver::chooseServerName(lookupResult: $lookupResult, serverAddress: '192.0.2.1'),
        );
    }

    public function testInvalidAddressIsNotLookedUp(): void
    {
        $resolver = new ReverseDnsServerNameResolver();

        $this->assertSame('localhost', $resolver->resolve(serverAddress: ''));
        $this->assertSame('localhost', $resolver->resolve(serverAddress: 'not an address'));
    }

    public function testCachedNameIsUsedWithoutLookup(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-server-name-'
            . bin2hex(string: random_bytes(length: 8));
        $cache = new FileCache(directory: $directory);
        $cache->set(key: 'yuf-server-name|192.0.2.1', value: 'cached.example.com', lifetimeInSeconds: 60);

        try {
            $this->assertSame(
                'cached.example.com',
                new ReverseDnsServerNameResolver(cache: $cache)->resolve(serverAddress: '192.0.2.1'),
            );
        } finally {
            $cache->delete(key: 'yuf-server-name|192.0.2.1');
            rmdir(directory: $directory);
        }
    }

    public function testLookedUpNameIsCached(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-server-name-'
            . bin2hex(string: random_bytes(length: 8));
        $cache = new FileCache(directory: $directory);

        try {
            $serverName = new ReverseDnsServerNameResolver(cache: $cache)->resolve(serverAddress: '127.0.0.1');

            $this->assertSame($serverName, $cache->get(key: 'yuf-server-name|127.0.0.1'));
        } finally {
            $cache->delete(key: 'yuf-server-name|127.0.0.1');
            rmdir(directory: $directory);
        }
    }
}
