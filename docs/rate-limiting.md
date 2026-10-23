# Rate limiting

`RateLimiter` (namespace `actra\yuf\security`) limits how often a key may do something, e.g. how many requests of
a public form or check an IP address may send, without database. Every `RateLimit` counts hits in fixed windows:
`new RateLimit(maxHits: 5, windowSeconds: 60)` allows 5 hits per minute.

```php
$rateLimiter = new RateLimiter(
    directory: $core->cacheDirectory . 'rateLimit',
    secret: $core->environmentSettings->getString(key: 'rateLimit.secret'), // at least 32 bytes
);
$ipLimits = [new RateLimit(maxHits: 5, windowSeconds: 60), new RateLimit(maxHits: 50, windowSeconds: 86400)];
$globalLimits = [new RateLimit(maxHits: 1000, windowSeconds: 86400)];
try {
    $ipBucket = RateLimiter::ipBucket(ipAddress: $httpRequest->getRemoteAddress());
    $result = $rateLimiter->consume($ipBucket, ...$ipLimits);
    if ($result->isAllowed) {
        $result = $rateLimiter->consume('all', ...$globalLimits);
    }
} catch (RuntimeException) {
    $result = null; // storage error: fail closed (or open, if blocking would do more harm)
}
if ($result === null || !$result->isAllowed) {
    // answer "too many requests", with $result->retryAfterSeconds for a Retry-After header
}
```

- `consume(key, ...limits)` counts one hit in the current window of every limit, but only if none of them is reached:
  a blocked request counts nowhere. Check the limits of one client before the global ones (as above), so a single
  client uses up only its own budget. `retryAfterSeconds` is the time until the last exceeded window ends (0 if
  allowed).
- Windows are aligned to the Unix epoch: a window of 3600 seconds starts at the full hour, one of 86400 seconds at
  midnight UTC. At the start of a window a client may send up to twice `maxHits` within a short time (end of the old
  and start of the new window); add a shorter limit if that matters.
- `RateLimiter::ipBucket()` turns an IP address into the key: IPv4 unchanged, IPv6 as its /64 prefix
  (`2001:db8:1:2::/64`, one connection usually gets a whole /64 and could otherwise change its address on every
  request), IPv4-mapped IPv6 as IPv4. Invalid values throw an `InvalidArgumentException`. Behind a reverse proxy,
  configure the web server first, else all clients share one IP ([setup.md](setup.md#host-and-client-ip-address)).
- A failed storage (directory not writable) throws a `RuntimeException`: the caller decides whether to block (fail
  closed, for anything that costs money or calls a third party) or to allow the request.

## Storage

- One file per key and limit in `directory` (created with mode 0700, files 0600), named
  `hmac-sha256(secret, key|maxHits|windowSeconds)`: no IP address can be read from the file names, and without the
  secret they cannot be guessed by hashing all IPv4 addresses. Changing the secret or a limit starts new counters.
- Every hit is counted under an exclusive lock (`flock()`) of the files of all its limits (read, increment, write,
  unlock, in the same order for every call), so parallel requests never lose or double a hit. Use a directory on a
  local file system of the server, not served by the web server; `flock()` is not reliable on network file systems,
  and several servers do not share the counters.
- Files of ended windows are deleted by `purgeExpired(maxFiles: 1000)` (returns the number of deleted files).
  `consume()` calls it with a probability of 1 % (`purgeProbabilityPercent:` of the constructor; 0 disables it, then
  call `purgeExpired()` from a cron job), so no cron job is needed.

## Tests

Pass a `FixedClock` (`clock:`) and `purgeProbabilityPercent: 0` for deterministic tests, and a temporary directory.
