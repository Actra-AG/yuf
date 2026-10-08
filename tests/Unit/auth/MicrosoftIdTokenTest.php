<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\JsonWebKeySetSource;
use actra\yuf\auth\MicrosoftIdToken;
use actra\yuf\clock\FixedClock;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\tests\Double\auth\FakeKeySetSource;
use actra\yuf\tests\Double\auth\TestJwtIssuer;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verification of Microsoft ID tokens with a generated key: the key set is read from the cache directory, so no
 * network access is needed.
 */
final class MicrosoftIdTokenTest extends TestCase
{
    private const int NOW = 1_800_000_000;
    private const string TENANT_ID = '11111111-2222-3333-4444-555555555555';
    private const string CLIENT_ID = 'client-id';
    private const string NONCE = 'nonce-1';

    private string $cacheDirectory;
    private TestJwtIssuer $issuer;

    #[Override]
    protected function setUp(): void
    {
        $this->cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-sso-test-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->cacheDirectory);
        $this->issuer = new TestJwtIssuer();
        $this->writeKeySet(json: $this->issuer->createKeySetJson());
    }

    #[Override]
    protected function tearDown(): void
    {
        $paths = glob(pattern: $this->cacheDirectory . '*');
        foreach ($paths === false ? [] : $paths as $path) {
            unlink(filename: $path);
        }
        rmdir(directory: $this->cacheDirectory);
    }

    public function testValidTokenIsAcceptedAndNamesTheUser(): void
    {
        $token = $this->createToken(jwt: $this->issuer->createJwt(payload: $this->createPayload()));

        $this->assertSame('user@example.org', $token->getUserName());
    }

    public function testTokenWithTamperedPayloadIsRejected(): void
    {
        $segments = explode(separator: '.', string: $this->issuer->createJwt(payload: $this->createPayload()));
        $segments[1] = TestJwtIssuer::encodeSegment(data: ['email' => 'admin@example.org'] + $this->createPayload());

        $this->assertRejected(jwt: implode(separator: '.', array: $segments), message: 'Signature verification failed');
    }

    public function testTokenSignedWithAnotherKeyIsRejected(): void
    {
        $jwt = new TestJwtIssuer()->createJwt(payload: $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Signature verification failed');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidAlgorithmProvider(): iterable
    {
        yield 'none' => [['alg' => 'none']];
        yield 'HS256' => [['alg' => 'HS256']];
        yield 'RS512' => [['alg' => 'RS512']];
        yield 'lower case' => [['alg' => 'rs256']];
        yield 'missing' => [[]];
    }

    /**
     * @param array<string, mixed> $header
     */
    #[DataProvider('invalidAlgorithmProvider')]
    public function testOtherAlgorithmsAreRejected(array $header): void
    {
        $jwt = $this->issuer->createJwt(payload: $this->createPayload(), header: $header);

        $this->assertRejected(jwt: $jwt, message: 'Missing or invalid alg');
    }

    public function testTokenWithoutKeyIdIsRejected(): void
    {
        $segments = explode(separator: '.', string: $this->issuer->createJwt(payload: $this->createPayload()));
        $segments[0] = TestJwtIssuer::encodeSegment(data: ['alg' => 'RS256']);

        $this->assertRejected(jwt: implode(separator: '.', array: $segments), message: 'Missing kid');
    }

    public function testTokenWithTwoSegmentsIsRejected(): void
    {
        $this->assertRejected(jwt: 'aaa.bbb', message: 'The JWT does not consist of three segments.');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['exp' => MicrosoftIdTokenTest::NOW - 3600] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or expired exp');
    }

    public function testTokenThatIsNotValidYetIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['nbf' => MicrosoftIdTokenTest::NOW + 3600] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or outdated nbf');
    }

    public function testTokenForAnotherClientIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['aud' => 'other-client'] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or invalid aud');
    }

    public function testTokenForAnotherTenantIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['tid' => 'other-tenant'] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or invalid tid');
    }

    public function testTokenWithAnotherNonceIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['nonce' => 'replayed'] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Invalid ssoNonce');
    }

    public function testKeySetWithoutKeysIsRejected(): void
    {
        unlink(filename: $this->getKeyFilePath());
        $message = null;
        $keySetSource = new FakeKeySetSource(json: '{"keys":[]}');

        try {
            $this->createToken(
                jwt: $this->issuer->createJwt(payload: $this->createPayload()),
                keySetSource: $keySetSource,
            );
        } catch (UnauthorizedException $exception) {
            $message = $exception->getMessage();
        }

        $this->assertSame('The key set contains no usable key.', $message);
    }

    public function testKeyWithPrivateComponentIsRejected(): void
    {
        unlink(filename: $this->getKeyFilePath());

        $this->assertRejected(
            jwt: $this->issuer->createJwt(payload: $this->createPayload()),
            message: 'Failed to parse JWK: RSA private key is not supported',
            keySetSource: new FakeKeySetSource(
                json: $this->issuer->createKeySetJson(additionalFields: ['d' => 'AQAB']),
            ),
        );
    }

    public function testCertificateChainThatDoesNotMatchIsRejected(): void
    {
        $other = new TestJwtIssuer();
        unlink(filename: $this->getKeyFilePath());

        $this->assertRejected(
            jwt: $this->issuer->createJwt(payload: $this->createPayload()),
            message: 'Invalid Certificate',
            keySetSource: new FakeKeySetSource(
                json: $this->issuer->createKeySetJson(additionalCertificates: [$other->certificateBase64]),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createPayload(): array
    {
        return [
            'nbf' => MicrosoftIdTokenTest::NOW - 10,
            'iat' => MicrosoftIdTokenTest::NOW - 10,
            'exp' => MicrosoftIdTokenTest::NOW + 3600,
            'aud' => MicrosoftIdTokenTest::CLIENT_ID,
            'tid' => MicrosoftIdTokenTest::TENANT_ID,
            'iss' => 'https://login.microsoftonline.com/' . MicrosoftIdTokenTest::TENANT_ID . '/v2.0',
            'nonce' => MicrosoftIdTokenTest::NONCE,
            'email' => 'user@example.org',
        ];
    }

    private function writeKeySet(string $json): void
    {
        file_put_contents(filename: $this->getKeyFilePath(), data: $json);
    }

    private function createToken(string $jwt, ?JsonWebKeySetSource $keySetSource = null): MicrosoftIdToken
    {
        return new MicrosoftIdToken(
            tenantId: MicrosoftIdTokenTest::TENANT_ID,
            clientId: MicrosoftIdTokenTest::CLIENT_ID,
            ssoNonce: MicrosoftIdTokenTest::NONCE,
            jwtString: $jwt,
            cacheDirectory: $this->cacheDirectory,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . MicrosoftIdTokenTest::NOW)),
            keySetSource: $keySetSource ?? new FakeKeySetSource(json: $this->issuer->createKeySetJson()),
        );
    }

    private function assertRejected(string $jwt, string $message, ?JsonWebKeySetSource $keySetSource = null): void
    {
        try {
            $this->createToken(jwt: $jwt, keySetSource: $keySetSource);
        } catch (UnauthorizedException $exception) {
            $this->assertSame($message, $exception->getMessage());

            return;
        }
        MicrosoftIdTokenTest::fail('The token was accepted.');
    }

    public function testTokenOfAnotherIssuerIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['iss' => 'https://evil.example.com/v2.0'] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or invalid iss');
    }

    public function testTokenWithoutIssuerIsRejected(): void
    {
        $payload = $this->createPayload();
        unset($payload['iss']);

        $this->assertRejected(jwt: $this->issuer->createJwt(payload: $payload), message: 'Missing or invalid iss');
    }

    public function testClaimsThatAreNotStringsAreRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['aud' => [MicrosoftIdTokenTest::CLIENT_ID]] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or invalid aud');
    }

    public function testTimeClaimsThatAreNotNumbersAreRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: ['exp' => '9999999999'] + $this->createPayload());

        $this->assertRejected(jwt: $jwt, message: 'Missing or expired exp');
    }

    public function testTokenWithMoreThanThreeSegmentsIsRejected(): void
    {
        $jwt = $this->issuer->createJwt(payload: $this->createPayload()) . '.extra';

        $this->assertRejected(jwt: $jwt, message: 'The JWT does not consist of three segments.');
    }

    public function testTokenWithInvalidBase64IsRejectedWithoutTheToken(): void
    {
        $this->assertRejected(
            jwt: '!!!.bbb.ccc',
            message: 'The JWT contains a segment that is not valid base64url.',
        );
    }

    public function testTokenWithInvalidJsonIsRejected(): void
    {
        $segment = TestJwtIssuer::base64Url(raw: '{not json');

        $this->assertRejected(
            jwt: $segment . '.' . $segment . '.' . $segment,
            message: 'The JWT contains invalid JSON.',
        );
    }

    public function testTokenWithJsonThatIsNoObjectIsRejected(): void
    {
        $segment = TestJwtIssuer::base64Url(raw: '[1,2]');

        $this->assertRejected(
            jwt: $segment . '.' . $segment . '.' . $segment,
            message: 'The JWT contains JSON that is not an object.',
        );
    }

    public function testTokenWithoutEmailHasNoUserName(): void
    {
        $payload = $this->createPayload();
        unset($payload['email']);
        $token = $this->createToken(jwt: $this->issuer->createJwt(payload: $payload));

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Missing email');

        $token->getUserName();
    }

    public function testUnknownKeyIdIsDownloadedAndCached(): void
    {
        $issuer = new TestJwtIssuer(keyId: 'new-key');
        $keySetSource = new FakeKeySetSource(json: $issuer->createKeySetJson());

        $token = $this->createToken(
            jwt: $issuer->createJwt(payload: $this->createPayload()),
            keySetSource: $keySetSource,
        );

        $this->assertSame('user@example.org', $token->getUserName());
        $this->assertSame(1, $keySetSource->downloads);
        $this->assertSame($issuer->createKeySetJson(), file_get_contents(filename: $this->getKeyFilePath()));
    }

    public function testSecondUnknownKeyIdWithinTheRefreshIntervalIsNotDownloaded(): void
    {
        $issuer = new TestJwtIssuer(keyId: 'new-key');
        $keySetSource = new FakeKeySetSource(json: $issuer->createKeySetJson());
        touch(filename: $this->getKeyFilePath(), mtime: MicrosoftIdTokenTest::NOW - 10);

        $this->assertRejected(
            jwt: $issuer->createJwt(payload: $this->createPayload()),
            message: 'Unknown key ID',
            keySetSource: $keySetSource,
        );

        $this->assertSame(0, $keySetSource->downloads);
    }

    public function testTenantIdWithOtherCharactersIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MicrosoftIdToken(
            tenantId: '../evil',
            clientId: MicrosoftIdTokenTest::CLIENT_ID,
            ssoNonce: MicrosoftIdTokenTest::NONCE,
            jwtString: 'a.b.c',
            cacheDirectory: $this->cacheDirectory,
        );
    }

    private function getKeyFilePath(): string
    {
        return $this->cacheDirectory . 'ssoMicrosoftKeys-' . MicrosoftIdTokenTest::TENANT_ID . '.json';
    }
}
