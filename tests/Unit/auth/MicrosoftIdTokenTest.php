<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\MicrosoftIdToken;
use actra\yuf\exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

/**
 * The key file is read from the cache directory; with a prepared file without keys, no network access is needed and
 * the failure message names the used file.
 */
final class MicrosoftIdTokenTest extends TestCase
{
    public function testPublicKeysAreReadFromTheCacheDirectory(): void
    {
        $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-sso-test-' . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        mkdir(directory: $cacheDirectory);
        $keyFilePath = $cacheDirectory . 'ssoMicrosoftKeys.json';
        file_put_contents(filename: $keyFilePath, data: '{"keys":[]}');

        $message = null;

        try {
            new MicrosoftIdToken(
                tenantId: 'tenant',
                clientId: 'client',
                ssoNonce: 'nonce',
                jwtString: $this->createJwt(),
                cacheDirectory: $cacheDirectory,
            );
        } catch (UnauthorizedException $exception) {
            $message = $exception->getMessage();
        } finally {
            unlink(filename: $keyFilePath);
            rmdir(directory: $cacheDirectory);
        }

        $this->assertSame('Failed to parse key file:' . $keyFilePath, $message);
    }

    private function createJwt(): string
    {
        $header = rtrim(string: strtr(string: base64_encode(string: '{"alg":"RS256","kid":"key1"}'), from: '+/', to: '-_'), characters: '=');
        $payload = rtrim(string: strtr(string: base64_encode(string: '{"email":"user@example.org"}'), from: '+/', to: '-_'), characters: '=');

        return $header . '.' . $payload . '.c2lnbmF0dXJl';
    }
}
