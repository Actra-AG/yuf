<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\IdTokenTimeClaimsValidator;
use actra\yuf\clock\FixedClock;
use actra\yuf\exception\UnauthorizedException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class IdTokenTimeClaimsValidatorTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private function createValidator(): IdTokenTimeClaimsValidator
    {
        return new IdTokenTimeClaimsValidator(
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . IdTokenTimeClaimsValidatorTest::NOW))
        );
    }

    private function createPayload(int $notBefore, int $issuedAt, int $expires): stdClass
    {
        $payload = new stdClass();
        $payload->nbf = $notBefore;
        $payload->iat = $issuedAt;
        $payload->exp = $expires;

        return $payload;
    }

    public function testValidTokenIsAccepted(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now - 10, issuedAt: $now - 10, expires: $now + 3600)
        );

        $this->addToAssertionCount(1);
    }

    public function testTokenIsStillAcceptedOneSecondBeforeTheLeewayEnds(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now + 60, issuedAt: $now + 60, expires: $now - 59)
        );

        $this->addToAssertionCount(1);
    }

    public function testTokenIsRejectedExactlyWhenTheLeewayAfterExpiryEnds(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Missing or expired exp');
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now - 10, issuedAt: $now - 10, expires: $now - 60)
        );
    }

    public function testTokenThatIsNotValidYetIsRejected(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Missing or outdated nbf');
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now + 61, issuedAt: $now, expires: $now + 3600)
        );
    }

    public function testTokenIssuedInTheFutureIsRejected(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Missing or outdated iat');
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now, issuedAt: $now + 61, expires: $now + 3600)
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function claimProvider(): iterable
    {
        yield 'nbf' => ['nbf'];
        yield 'iat' => ['iat'];
        yield 'exp' => ['exp'];
    }

    #[DataProvider('claimProvider')]
    public function testMissingClaimIsRejected(string $claim): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $payload = $this->createPayload(notBefore: $now, issuedAt: $now, expires: $now + 3600);
        unset($payload->$claim);

        $this->expectException(UnauthorizedException::class);
        $this->createValidator()->assertValid(payload: $payload);
    }
}