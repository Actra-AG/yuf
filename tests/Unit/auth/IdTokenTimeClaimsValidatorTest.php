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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class IdTokenTimeClaimsValidatorTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    private function createValidator(): IdTokenTimeClaimsValidator
    {
        return new IdTokenTimeClaimsValidator(
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . IdTokenTimeClaimsValidatorTest::NOW)),
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
        $this->expectNotToPerformAssertions();

        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now - 10, issuedAt: $now - 10, expires: $now + 3600),
        );
    }

    public function testTokenIsStillAcceptedOneSecondBeforeTheLeewayEnds(): void
    {
        $this->expectNotToPerformAssertions();

        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now + 60, issuedAt: $now + 60, expires: $now - 59),
        );
    }

    public function testTokenIsRejectedExactlyWhenTheLeewayAfterExpiryEnds(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIsOrContains('Missing or expired exp');
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now - 10, issuedAt: $now - 10, expires: $now - 60),
        );
    }

    public function testTokenThatIsNotValidYetIsRejected(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIsOrContains('Missing or outdated nbf');
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now + 61, issuedAt: $now, expires: $now + 3600),
        );
    }

    public function testTokenIssuedInTheFutureIsRejected(): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIsOrContains('Missing or outdated iat');
        $this->createValidator()->assertValid(
            payload: $this->createPayload(notBefore: $now, issuedAt: $now + 61, expires: $now + 3600),
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

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function nonNumericClaimProvider(): iterable
    {
        yield 'nbf as string' => ['nbf', '1799999990'];
        yield 'iat as string' => ['iat', '1799999990'];
        yield 'exp as string' => ['exp', '1800003600'];
        yield 'exp as null' => ['exp', null];
        yield 'exp as array' => ['exp', [1_800_003_600]];
        yield 'exp as bool' => ['exp', true];
    }

    #[DataProvider('nonNumericClaimProvider')]
    public function testClaimThatIsNoNumberIsRejected(string $claim, mixed $value): void
    {
        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $payload = $this->createPayload(notBefore: $now - 10, issuedAt: $now - 10, expires: $now + 3600);
        $payload->$claim = $value;

        $this->expectException(UnauthorizedException::class);

        $this->createValidator()->assertValid(payload: $payload);
    }

    public function testNegativeLeewayIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IdTokenTimeClaimsValidator(leewayInSeconds: -1);
    }

    public function testClaimsAsFloatsAreAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        $now = IdTokenTimeClaimsValidatorTest::NOW;
        $payload = $this->createPayload(notBefore: $now, issuedAt: $now, expires: $now + 3600);
        $payload->exp = $now + 3600.5;

        $this->createValidator()->assertValid(payload: $payload);
    }
}
