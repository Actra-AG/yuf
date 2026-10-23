<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

/**
 * Script of `RateLimiterTest::testParallelProcessesNeverExceedTheLimit()`: waits until the start file exists, then
 * calls `RateLimiter::consume()` `YUF_ATTEMPTS` times and prints the number of allowed hits.
 *
 * Environment variables: `YUF_DIRECTORY`, `YUF_SECRET`, `YUF_ATTEMPTS`, `YUF_MAX_HITS`, `YUF_START_FILE`
 */

use actra\yuf\clock\FixedClock;
use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;

require __DIR__ . '/../../../vendor/autoload.php';

ini_set(option: 'display_errors', value: 'stderr');
$directory = getenv(name: 'YUF_DIRECTORY');
$secret = getenv(name: 'YUF_SECRET');
$attempts = getenv(name: 'YUF_ATTEMPTS');
$maxHits = getenv(name: 'YUF_MAX_HITS');
$startFile = getenv(name: 'YUF_START_FILE');
if (
    !is_string(value: $directory)
    || !is_string(value: $secret)
    || !is_string(value: $attempts)
    || !is_string(value: $maxHits)
    || !is_string(value: $startFile)
) {
    exit(1);
}
$rateLimiter = new RateLimiter(
    directory: $directory,
    secret: $secret,
    clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-10-09 12:00:00 UTC')),
    purgeProbabilityPercent: 0,
);
$limit = new RateLimit(maxHits: (int) $maxHits, windowSeconds: 3600);
$deadline = microtime(as_float: true) + 10;
while (!is_file(filename: $startFile) && microtime(as_float: true) < $deadline) {
    usleep(microseconds: 1000);
}
$allowed = 0;
for ($i = 0; $i < (int) $attempts; $i++) {
    if ($rateLimiter->consume('203.0.113.7', $limit)->isAllowed) {
        $allowed++;
    }
}
echo $allowed;
