# Signed login tickets (future release)

Goal: hand a login on a login host over to sites on **other servers**, without a shared directory or database. Today
(v8.12.0) the hand-over is server-side (`LoginHandOver`): login requests and tickets are files in a directory that the
login host and all sites share. This plan replaces or complements it with signed tickets once sites run on other
servers. Not started; a prototype was built and removed before the v8.12.0 release (design below is what it did).

## When

Only when a site that uses the shared login runs on another server than the login host. As long as all hosts share
one server, `LoginHandOver` is simpler and more secure (no keys, no data in URLs).

## Design

### Flow

1. The site redirects to the login page with the absolute return URL (or a login request ID, see "Open points") and a
   login nonce, and sets the nonce in a cookie (login CSRF protection).
2. After the login, the login host signs a ticket for the user and the site and redirects to the hand-over page of
   the site: `https://<site><handOverPath>?loginTicket=<JWT>&returnTo=<path and query, URL-encoded>`.
3. The hand-over page verifies the ticket, uses it up, compares its nonce with the cookie and logs the user in with
   `Authenticator::logInIdentifiedUser(authMethod: AuthMethodEnum::SSO, …)`, then redirects to the return path with
   `HttpResponse::redirectAndExit(sameSiteLaxSessionHandler:)` (the session cookie stays `SameSite=Strict`).

### Signature: Ed25519 (JWT `alg: EdDSA`, RFC 8037)

- Through `ext-openssl` (already required): `openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_ED25519])`,
  `openssl_sign()` / `openssl_verify()` with algorithm `0` ("no digest", there is no constant). Tested with PHP 8.5.8
  and OpenSSL 3.5.6. No `ext-sodium` needed.
- Chosen over RSA: 64-byte signatures (86 characters instead of 342–512 in the URL), one-line keys, no key size, no
  padding choices, deterministic. Chosen over ES256: no DER-to-raw conversion, no ECDSA nonce risks.
- Asymmetric on purpose: only the login host has the private key, every site has the public key, so a compromised
  site cannot issue tickets for others. No shared secret (HMAC).
- Only Ed25519 keys are accepted (key type checked after loading), so no other algorithm can be used with them.
- Keys: `openssl genpkey -algorithm ED25519 -out login-ticket.key` (login host only),
  `openssl pkey -in login-ticket.key -pubout -out login-ticket.pub` (every site); never in a repository.
- Key rotation: the site accepts a list of public keys (old and new while a key is replaced).

### Classes (prototype)

- `Ed25519Signer` (`@internal`): `loadPrivateKey(pem)`, `loadPublicKey(pem)` (throw `InvalidArgumentException` for
  other key types or encrypted keys), `sign(data, privateKey)`, `verify(data, signature, publicKey)`,
  `JWT_ALGORITHM = 'EdDSA'`.
- `LoginTicketIssuer` (login host): `__construct(privateKey, issuer, allowedAudiences, clock, lifetimeInSeconds = 60)`
  (lifetime 1–300; issuer and audiences must be lowercase host names), `issue(subject, audience, loginNonce): string`,
  `createHandOverUrl(returnUrl, handOverPath, subject, loginNonce): string` (return URL validated with
  `LoginRedirect::findHttpsHost()`, its host must be an allowed audience, hand-over path a local path without query).
- `LoginTicket extends AuthWebToken` (site): `__construct(jwtString, loginNonce, publicKeys, issuer, audience,
  usedLoginTicketStore, clock)`, `getSubject()`, `PARAMETER = 'loginTicket'`. The audience is configured, never taken
  from the `Host` header.
- `UsedLoginTicketStore` (interface): `markAsUsed(ticketId, expiresAt): bool`, atomic; `FileUsedLoginTicketStore`
  (one server: file per ticket named by SHA-256 of the ID, created with `fopen(…, 'x')`, modification time = expiry,
  expired files purged, directory `0700`). Sites on several servers need a shared implementation (table with a unique
  key on the ticket ID).
- `LoginNonceCookie` (site): `__Host-yuf-login-nonce` (`Secure`, `HttpOnly`, path `/`, 15 minutes), `issue()` keeps
  a valid nonce of an earlier redirect (several tabs), `consume()` reads and deletes it. Sent through
  `SsoNonceCookieWriter`. `ExceptionHandlerContext` got a `nonceCookieWriter` for the redirect.

### Ticket claims and checks

Header `{"alg": "EdDSA", "typ": "JWT"}`; payload `iss` (login host), `aud` (site host), `sub` (stable user ID),
`iat`, `nbf`, `exp` (default 60 seconds), `jti` (16 random bytes, hex), `nonce` (64 hex characters).

The site accepts a ticket only if, in this order:

1. `alg` is exactly `EdDSA` (fixed, never the one the token asks for; `none`, `HS256`, `RS256`, `eddsa` rejected);
2. the signature matches one of the public keys (checked before any claim is read);
3. `nbf`, `iat`, `exp` are numbers and valid with 30 seconds leeway (`IdTokenTimeClaimsValidator`), `exp - iat` is
   at most 300 seconds;
4. `iss` and `aud` equal the configured values (`hash_equals`, exact, case-sensitive);
5. `sub` is a non-empty string; `nonce` equals the nonce cookie of the browser; `jti` is 32 lowercase hex characters;
6. `jti` was not used before (`UsedLoginTicketStore::markAsUsed()`, expiry `exp` + leeway). A ticket that verifies
   is used up, even if the login fails afterwards.

### Tests of the prototype

Valid ticket and subject; used once; ticket of the issuer round trip; several public keys; leeway; every invalid claim
(other/uppercase/list audience, other issuer, empty/numeric/missing subject, short/uppercase/missing `jti`, expired,
not yet valid, issued in the future, too long valid, float expiry); ticket of another key; changed payload; invalid
algorithms; RSA-signed ticket; unsigned ticket; malformed ticket; missing, invalid and RSA public keys; issuer claims
and own `jti` per ticket; hand-over URL format and round trip; invalid hand-overs (host not allowed, look-alike host,
`http`, local return path, user info, port, relative/absolute/query hand-over path); invalid settings (issuer URL,
uppercase issuer, audience URL or port, lifetime 0 or 301); invalid UTF-8 subject; file store: marked once,
file name and expiry, directory mode, purge of expired files.

## Open points

- Login request ID instead of the absolute return URL, as in `LoginHandOver`, needs a shared store again; with signed
  tickets the return target travels in the URL (`returnTo` absolute, validated against allowed hosts on the login
  host, `LoginRedirect::findReturnUrl()` of v8.11.0, removed in v8.12.0) or signed by the site.
- A residual login CSRF risk: an attacker who learns the nonce of a victim (logs, `Referer`) can bind a ticket of
  their own account to it. Keep the login page free of third-party resources and the logs access-restricted.
- `sub` must be the same user on the login host and the site (shared user IDs, or a stored link like the Microsoft
  object ID).
- Whether `LoginHandOver` and signed tickets share one API on the site (e.g. an interface for the hand-over page).
