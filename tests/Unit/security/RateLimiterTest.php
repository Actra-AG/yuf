<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\security;

use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;
use actra\yuf\tests\Double\clock\AdjustableClock;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RateLimiterTest extends TestCase
{
    private const string SECRET = 'a secret of at least thirty-two bytes';

    private string $directory;
    private AdjustableClock $clock;
    private RateLimiter $rateLimiter;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-rate-limiter-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR . 'rateLimit';
        // 12:00:00 UTC: the start of a minute, an hour and a window of 3600 seconds
        $this->clock = new AdjustableClock(now: new DateTimeImmutable(datetime: '2026-10-09 12:00:00 UTC'));
        $this->rateLimiter = $this->createRateLimiter();
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->getFiles() as $file) {
            unlink(filename: $file);
        }
        foreach ($this->getStartFiles() as $file) {
            unlink(filename: $file);
        }
        if (is_dir(filename: $this->directory)) {
            rmdir(directory: $this->directory);
        }
        if (is_dir(filename: dirname(path: $this->directory))) {
            rmdir(directory: dirname(path: $this->directory));
        }
    }

    public function testRejectsInvalidLimits(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimit(maxHits: 0, windowSeconds: 60);
    }

    public function testRejectsAWindowShorterThanOneSecond(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimit(maxHits: 1, windowSeconds: 0);
    }

    public function testRejectsAShortSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimiter(directory: $this->directory, secret: str_repeat(string: 'x', times: 31));
    }

    public function testRejectsAnInvalidPurgeProbability(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RateLimiter(directory: $this->directory, secret: RateLimiterTest::SECRET, purgeProbabilityPercent: 101);
    }

    public function testRejectsAConsumeWithoutLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rateLimiter->consume('key');
    }

    public function testBlocksWhenTheLimitIsReachedUntilTheWindowEnds(): void
    {
        $limit = new RateLimit(maxHits: 3, windowSeconds: 60);
        $this->clock->advanceSeconds(seconds: 15);

        for ($i = 1; $i <= 3; $i++) {
            $result = $this->rateLimiter->consume('key', $limit);
            $this->assertTrue($result->isAllowed);
            $this->assertSame(0, $result->retryAfterSeconds);
        }
        $blocked = $this->createRateLimiter()->consume('key', $limit);
        $this->assertFalse($blocked->isAllowed);
        $this->assertSame(45, $blocked->retryAfterSeconds);
        $this->assertTrue($this->rateLimiter->consume('other key', $limit)->isAllowed);

        $this->clock->advanceSeconds(seconds: 44);
        $this->assertFalse($this->rateLimiter->consume('key', $limit)->isAllowed);
        $this->clock->advanceSeconds(seconds: 1);
        $this->assertTrue($this->rateLimiter->consume('key', $limit)->isAllowed);
    }

    public function testCountsNothingWhenOneOfSeveralLimitsIsReached(): void
    {
        $perMinute = new RateLimit(maxHits: 2, windowSeconds: 60);
        $perHour = new RateLimit(maxHits: 3, windowSeconds: 3600);

        $this->assertTrue($this->rateLimiter->consume('key', $perMinute, $perHour)->isAllowed);
        $this->assertTrue($this->rateLimiter->consume('key', $perMinute, $perHour)->isAllowed);
        // Blocked by the minute: the hour must not count it
        $blocked = $this->rateLimiter->consume('key', $perMinute, $perHour);
        $this->assertFalse($blocked->isAllowed);
        $this->assertSame(60, $blocked->retryAfterSeconds);

        $this->clock->advanceSeconds(seconds: 60);
        $this->assertTrue($this->rateLimiter->consume('key', $perMinute, $perHour)->isAllowed);
        $blockedByHour = $this->rateLimiter->consume('key', $perMinute, $perHour);
        $this->assertFalse($blockedByHour->isAllowed);
        $this->assertSame(3540, $blockedByHour->retryAfterSeconds);
        // The minute did not count the hit blocked by the hour either
        $this->assertTrue($this->rateLimiter->consume('key', $perMinute)->isAllowed);
    }

    public function testRetryAfterIsTheLongestExceededWindow(): void
    {
        $perMinute = new RateLimit(maxHits: 1, windowSeconds: 60);
        $perHour = new RateLimit(maxHits: 1, windowSeconds: 3600);
        $this->rateLimiter->consume('key', $perMinute, $perHour);

        $this->assertSame(3600, $this->rateLimiter->consume('key', $perMinute, $perHour)->retryAfterSeconds);
    }

    public function testTheSameLimitTwiceCountsOnce(): void
    {
        $limit = new RateLimit(maxHits: 2, windowSeconds: 60);

        $this->assertTrue($this->rateLimiter->consume('key', $limit, $limit)->isAllowed);
        $this->assertTrue($this->rateLimiter->consume('key', $limit)->isAllowed);
        $this->assertFalse($this->rateLimiter->consume('key', $limit)->isAllowed);
    }

    public function testFilesAreReadableByTheOwnerOnlyAndDoNotNameTheKey(): void
    {
        $this->rateLimiter->consume('203.0.113.7', new RateLimit(maxHits: 5, windowSeconds: 60));

        $files = $this->getFiles();
        $this->assertCount(1, $files);
        $this->assertSame(
            hash_hmac(algo: 'sha256', data: '203.0.113.7|5|60', key: RateLimiterTest::SECRET),
            basename(path: $files[0]),
        );
        $this->assertSame(0o600, fileperms(filename: $files[0]) & 0o777);
        $this->assertSame(0o700, fileperms(filename: $this->directory) & 0o777);
        $this->assertStringNotContainsString('203.0.113.7', (string) file_get_contents(filename: $files[0]));
    }

    public function testADamagedFileStartsANewCount(): void
    {
        $limit = new RateLimit(maxHits: 1, windowSeconds: 60);
        $this->rateLimiter->consume('key', $limit);
        file_put_contents(filename: $this->getFiles()[0] ?? '', data: 'damaged');

        $this->assertTrue($this->rateLimiter->consume('key', $limit)->isAllowed);
        $this->assertFalse($this->rateLimiter->consume('key', $limit)->isAllowed);
    }

    public function testPurgeDeletesOnlyEndedWindows(): void
    {
        $this->assertSame(0, $this->rateLimiter->purgeExpired());
        $this->rateLimiter->consume('a', new RateLimit(maxHits: 5, windowSeconds: 60));
        $this->rateLimiter->consume('b', new RateLimit(maxHits: 5, windowSeconds: 3600));
        $this->rateLimiter->consume('c', new RateLimit(maxHits: 5, windowSeconds: 60));
        file_put_contents(filename: $this->directory . DIRECTORY_SEPARATOR . 'not-a-counter', data: '');

        $this->assertSame(0, $this->rateLimiter->purgeExpired());
        $this->clock->advanceSeconds(seconds: 60);
        $this->assertSame(1, $this->rateLimiter->purgeExpired(maxFiles: 1));
        $this->assertSame(1, $this->rateLimiter->purgeExpired());
        $this->assertCount(2, $this->getFiles());
        $this->clock->advanceSeconds(seconds: 3540);
        $this->assertSame(1, $this->rateLimiter->purgeExpired());
        $this->assertSame(['not-a-counter'], array_map(basename(...), $this->getFiles()));
    }

    public function testConsumePurgesWithTheGivenProbability(): void
    {
        $limit = new RateLimit(maxHits: 5, windowSeconds: 60);
        $this->rateLimiter->consume('a', $limit);
        $this->clock->advanceSeconds(seconds: 60);
        $alwaysPurging = $this->createRateLimiter(purgeProbabilityPercent: 100);

        $alwaysPurging->consume('b', $limit);

        $this->assertCount(1, $this->getFiles());
    }

    public function testRejectsPurgingZeroFiles(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rateLimiter->purgeExpired(maxFiles: 0);
    }

    public function testStorageErrorsThrow(): void
    {
        mkdir(directory: dirname(path: $this->directory));
        // A file where the directory should be
        touch(filename: $this->directory);
        try {
            $this->expectException(RuntimeException::class);

            $this->rateLimiter->consume('key', new RateLimit(maxHits: 1, windowSeconds: 60));
        } finally {
            unlink(filename: $this->directory);
        }
    }

    public function testParallelProcessesNeverExceedTheLimit(): void
    {
        mkdir(directory: $this->directory, recursive: true);
        $startFile = $this->directory . DIRECTORY_SEPARATOR . 'start';
        $workers = [];
        for ($i = 0; $i < 4; $i++) {
            // @phpstan-ignore disallowed.function (starts PHP workers with fixed arguments)
            $process = proc_open(
                command: [
                    PHP_BINARY,
                    dirname(path: __DIR__, levels: 2) . '/Double/security/rate-limiter-worker.php',
                ],
                descriptor_spec: [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                pipes: $pipes,
                env_vars: [
                    'YUF_DIRECTORY' => $this->directory,
                    'YUF_SECRET' => RateLimiterTest::SECRET,
                    'YUF_ATTEMPTS' => '100',
                    'YUF_MAX_HITS' => '150',
                    'YUF_START_FILE' => $startFile,
                ],
            );
            $this->assertIsResource($process);
            $stdout = $pipes[1] ?? null;
            $stderr = $pipes[2] ?? null;
            $this->assertIsResource($stdout);
            $this->assertIsResource($stderr);
            $workers[] = ['process' => $process, 'stdout' => $stdout, 'stderr' => $stderr];
        }
        touch(filename: $startFile);
        $allowed = 0;
        foreach ($workers as $worker) {
            $output = stream_get_contents(stream: $worker['stdout']);
            $errors = stream_get_contents(stream: $worker['stderr']);
            fclose(stream: $worker['stdout']);
            fclose(stream: $worker['stderr']);
            $this->assertSame(0, proc_close(process: $worker['process']), (string) $errors);
            $allowed += (int) $output;
        }

        // 400 attempts: exactly 150 allowed, no hit lost and none counted twice
        $this->assertSame(150, $allowed);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ipBucketProvider(): array
    {
        return [
            'IPv4' => ['203.0.113.7', '203.0.113.7'],
            'IPv6' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'],
            'IPv6 uppercase, same /64' => ['2001:DB8:1:2:FFFF::1', '2001:db8:1:2::/64'],
            'IPv6 short prefix' => ['2001:db8::1', '2001:db8::/64'],
            'IPv6 loopback' => ['::1', '::/64'],
            'IPv4-mapped IPv6' => ['::ffff:203.0.113.7', '203.0.113.7'],
            'IPv4-mapped IPv6 in hex' => ['::ffff:cb00:7107', '203.0.113.7'],
        ];
    }

    #[DataProvider('ipBucketProvider')]
    public function testIpBucket(string $ipAddress, string $expectedBucket): void
    {
        $this->assertSame($expectedBucket, RateLimiter::ipBucket(ipAddress: $ipAddress));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIpAddressProvider(): array
    {
        return [
            'empty' => [''],
            'host name' => ['example.com'],
            'IPv4 out of range' => ['256.1.1.1'],
            'IPv4 with prefix' => ['203.0.113.0/24'],
            'IPv6 with zone' => ['fe80::1%eth0'],
        ];
    }

    #[DataProvider('invalidIpAddressProvider')]
    public function testIpBucketRejectsInvalidValues(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        RateLimiter::ipBucket(ipAddress: $value);
    }

    private function createRateLimiter(int $purgeProbabilityPercent = 0): RateLimiter
    {
        return new RateLimiter(
            directory: $this->directory,
            secret: RateLimiterTest::SECRET,
            clock: $this->clock,
            purgeProbabilityPercent: $purgeProbabilityPercent,
        );
    }

    /**
     * @return list<string>
     */
    private function getStartFiles(): array
    {
        $startFile = $this->directory . DIRECTORY_SEPARATOR . 'start';

        return is_file(filename: $startFile) ? [$startFile] : [];
    }

    /**
     * @return list<string>
     */
    private function getFiles(): array
    {
        $files = glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*');

        return array_values(
            array: array_filter(
                array: $files === false ? [] : $files,
                callback: static fn(string $file): bool => basename(path: $file) !== 'start',
            ),
        );
    }
}
