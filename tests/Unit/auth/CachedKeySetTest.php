<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\CachedKeySet;
use actra\yuf\clock\FixedClock;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\tests\Double\auth\FakeKeySetSource;
use actra\yuf\tests\Double\auth\TestJwtIssuer;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CachedKeySetTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private string $directory;
    private string $cacheFilePath;
    private TestJwtIssuer $issuer;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-keyset-test-'
            . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $this->directory);
        $this->cacheFilePath = $this->directory . DIRECTORY_SEPARATOR . 'keys.json';
        $this->issuer = new TestJwtIssuer();
    }

    #[Override]
    protected function tearDown(): void
    {
        $paths = glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*');
        foreach ($paths === false ? [] : $paths as $path) {
            unlink(filename: $path);
        }
        rmdir(directory: $this->directory);
    }

    private function createKeySet(FakeKeySetSource $source): CachedKeySet
    {
        return new CachedKeySet(
            cacheFilePath: $this->cacheFilePath,
            source: $source,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . CachedKeySetTest::NOW)),
        );
    }

    public function testMissingCacheIsDownloadedAndWrittenWithMode0600(): void
    {
        $source = new FakeKeySetSource(json: $this->issuer->createKeySetJson());

        $this->createKeySet(source: $source)->getKey(keyId: 'key1');

        $this->assertSame(1, $source->downloads);
        $this->assertSame($this->issuer->createKeySetJson(), file_get_contents(filename: $this->cacheFilePath));
        $this->assertSame(0o600, fileperms(filename: $this->cacheFilePath) & 0o777);
        $this->assertSame([$this->cacheFilePath], glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*'));
    }

    public function testKnownKeyIsReadFromTheCacheWithoutDownload(): void
    {
        file_put_contents(filename: $this->cacheFilePath, data: $this->issuer->createKeySetJson());
        $source = new FakeKeySetSource(json: '');

        $this->createKeySet(source: $source)->getKey(keyId: 'key1');

        $this->assertSame(0, $source->downloads);
    }

    public function testBrokenCacheIsReplacedByANewDownload(): void
    {
        file_put_contents(filename: $this->cacheFilePath, data: 'not json');
        touch(filename: $this->cacheFilePath, mtime: CachedKeySetTest::NOW - 1);
        $source = new FakeKeySetSource(json: $this->issuer->createKeySetJson());

        $this->createKeySet(source: $source)->getKey(keyId: 'key1');

        $this->assertSame(1, $source->downloads);
        $this->assertSame($this->issuer->createKeySetJson(), file_get_contents(filename: $this->cacheFilePath));
    }

    public function testBrokenDownloadDoesNotReplaceTheCache(): void
    {
        file_put_contents(filename: $this->cacheFilePath, data: $this->issuer->createKeySetJson());
        touch(filename: $this->cacheFilePath, mtime: CachedKeySetTest::NOW - 1000);
        $source = new FakeKeySetSource(json: '<html>error</html>');

        try {
            $this->createKeySet(source: $source)->getKey(keyId: 'other');
            CachedKeySetTest::fail('The unknown key was accepted.');
        } catch (UnauthorizedException) {
            $this->assertSame($this->issuer->createKeySetJson(), file_get_contents(filename: $this->cacheFilePath));
        }
    }

    public function testUnknownKeyIsDownloadedAfterTheRefreshInterval(): void
    {
        $other = new TestJwtIssuer(keyId: 'other');
        file_put_contents(filename: $this->cacheFilePath, data: $this->issuer->createKeySetJson());
        touch(filename: $this->cacheFilePath, mtime: CachedKeySetTest::NOW - 300);
        $source = new FakeKeySetSource(json: $other->createKeySetJson());

        $this->createKeySet(source: $source)->getKey(keyId: 'other');

        $this->assertSame(1, $source->downloads);
    }

    public function testUnknownKeyIsNotDownloadedWithinTheRefreshInterval(): void
    {
        file_put_contents(filename: $this->cacheFilePath, data: $this->issuer->createKeySetJson());
        touch(filename: $this->cacheFilePath, mtime: CachedKeySetTest::NOW - 299);
        $source = new FakeKeySetSource(json: $this->issuer->createKeySetJson());

        try {
            $this->createKeySet(source: $source)->getKey(keyId: 'other');
            CachedKeySetTest::fail('The unknown key was accepted.');
        } catch (UnauthorizedException $exception) {
            $this->assertSame('Unknown key ID', $exception->getMessage());
        }
        $this->assertSame(0, $source->downloads);
    }

    public function testKeyThatIsMissingAfterTheDownloadIsUnknown(): void
    {
        $source = new FakeKeySetSource(json: $this->issuer->createKeySetJson());

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Unknown key ID');

        $this->createKeySet(source: $source)->getKey(keyId: 'other');
    }

    public function testMissingCacheDirectoryIsReported(): void
    {
        $keySet = new CachedKeySet(
            cacheFilePath: $this->directory . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'keys.json',
            source: new FakeKeySetSource(json: $this->issuer->createKeySetJson()),
        );

        $this->expectException(RuntimeException::class);

        $keySet->getKey(keyId: 'key1');
    }
}
