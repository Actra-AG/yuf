<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\spam;

use actra\yuf\clock\FixedClock;
use actra\yuf\form\spam\SpamCheck;
use actra\yuf\form\spam\SpamCheckResultEnum;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpamCheckTest extends TestCase
{
    private const string SECRET = '0123456789abcdef0123456789abcdef';
    private const int RENDERED_AT = 1_800_000_000;

    /**
     * @param list<string> $rejectedEmailAddresses
     */
    private static function createCheck(
        int $now,
        array $rejectedEmailAddresses = [],
        string $secret = SpamCheckTest::SECRET,
    ): SpamCheck {
        return new SpamCheck(
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . $now)),
            secret: $secret,
            rejectedEmailAddresses: $rejectedEmailAddresses,
        );
    }

    private static function signedRenderTime(string $formName = 'contact', string $secret = SpamCheckTest::SECRET): string
    {
        return SpamCheckTest::createCheck(now: SpamCheckTest::RENDERED_AT, secret: $secret)
            ->signRenderTime(formName: $formName);
    }

    public function testSignedRenderTimeIsTheTimestampWithItsSignature(): void
    {
        $this->assertMatchesRegularExpression('/^1800000000\.[0-9a-f]{64}$/', SpamCheckTest::signedRenderTime());
    }

    /**
     * @return array<string, array{int, SpamCheckResultEnum}>
     */
    public static function secondsProvider(): array
    {
        return [
            'immediately' => [0, SpamCheckResultEnum::TOO_FAST],
            '2 seconds' => [2, SpamCheckResultEnum::TOO_FAST],
            '3 seconds' => [3, SpamCheckResultEnum::OK],
            '3600 seconds' => [3600, SpamCheckResultEnum::OK],
            '3601 seconds' => [3601, SpamCheckResultEnum::EXPIRED],
            'from the future' => [-10, SpamCheckResultEnum::TOO_FAST],
        ];
    }

    #[DataProvider('secondsProvider')]
    public function testTimeBetweenRenderingAndSubmitting(int $seconds, SpamCheckResultEnum $expected): void
    {
        $result = SpamCheckTest::createCheck(now: SpamCheckTest::RENDERED_AT + $seconds)->check(
            formName: 'contact',
            honeypot: '',
            signedRenderTime: SpamCheckTest::signedRenderTime(),
            emailAddress: 'customer@example.com',
        );

        $this->assertSame($expected, $result);
    }

    public function testConfiguredTimeRange(): void
    {
        $check = new SpamCheck(
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '@' . (SpamCheckTest::RENDERED_AT + 10))),
            secret: SpamCheckTest::SECRET,
            rejectedEmailAddresses: [],
            minSeconds: 11,
            maxSeconds: 20,
        );

        $this->assertSame(
            SpamCheckResultEnum::TOO_FAST,
            $check->check(
                formName: 'contact',
                honeypot: '',
                signedRenderTime: SpamCheckTest::signedRenderTime(),
                emailAddress: null,
            ),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidRenderTimeProvider(): array
    {
        $signed = SpamCheckTest::signedRenderTime();
        $timestamp = substr(string: $signed, offset: 0, length: 10);
        $signature = substr(string: $signed, offset: 11);

        return [
            'missing' => [''],
            'unsigned' => [$timestamp],
            'forged timestamp' => [(string) ((int) $timestamp - 60) . '.' . $signature],
            'forged signature' => [$timestamp . '.' . str_repeat(string: '0', times: 64)],
            'signature of another form' => [SpamCheckTest::signedRenderTime(formName: 'order')],
            'signature with another secret' => [
                SpamCheckTest::signedRenderTime(secret: 'another secret of at least 32 bytes'),
            ],
            'uppercase signature' => [$timestamp . '.' . strtoupper(string: $signature)],
            'trailing newline' => [$signed . "\n"],
            'negative timestamp' => ['-' . $signed],
            'too long timestamp' => ['1' . str_repeat(string: '0', times: 19) . '.' . $signature],
            'text' => ['abc'],
        ];
    }

    #[DataProvider('invalidRenderTimeProvider')]
    public function testMissingOrManipulatedRenderTimeIsExpired(string $signedRenderTime): void
    {
        $result = SpamCheckTest::createCheck(now: SpamCheckTest::RENDERED_AT + 10)->check(
            formName: 'contact',
            honeypot: '',
            signedRenderTime: $signedRenderTime,
            emailAddress: null,
        );

        $this->assertSame(SpamCheckResultEnum::EXPIRED, $result);
    }

    public function testFilledHoneypot(): void
    {
        $result = SpamCheckTest::createCheck(now: SpamCheckTest::RENDERED_AT + 10)->check(
            formName: 'contact',
            honeypot: 'https://spam.example',
            signedRenderTime: SpamCheckTest::signedRenderTime(),
            emailAddress: 'customer@example.com',
        );

        $this->assertSame(SpamCheckResultEnum::HONEYPOT_FILLED, $result);
    }

    /**
     * @return array<string, array{string, SpamCheckResultEnum}>
     */
    public static function emailAddressProvider(): array
    {
        return [
            'same' => ['info@example.com', SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS],
            'other case' => ['Info@Example.COM', SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS],
            'with spaces' => [' info@example.com ', SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS],
            'second address' => ['office@example.com', SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS],
            'other address' => ['customer@example.com', SpamCheckResultEnum::OK],
            'empty' => ['', SpamCheckResultEnum::OK],
        ];
    }

    #[DataProvider('emailAddressProvider')]
    public function testRejectedEmailAddresses(string $emailAddress, SpamCheckResultEnum $expected): void
    {
        $check = SpamCheckTest::createCheck(
            now: SpamCheckTest::RENDERED_AT + 10,
            rejectedEmailAddresses: ['INFO@example.com', 'office@example.com'],
        );

        $result = $check->check(
            formName: 'contact',
            honeypot: '',
            signedRenderTime: SpamCheckTest::signedRenderTime(),
            emailAddress: $emailAddress,
        );

        $this->assertSame($expected, $result);
    }

    public function testRejectedEmailAddressIsCheckedFirst(): void
    {
        $result = SpamCheckTest::createCheck(now: SpamCheckTest::RENDERED_AT, rejectedEmailAddresses: ['a@example.com'])
            ->check(formName: 'contact', honeypot: 'x', signedRenderTime: '', emailAddress: 'a@example.com');

        $this->assertSame(SpamCheckResultEnum::REJECTED_EMAIL_ADDRESS, $result);
    }

    public function testHoneypotIsCheckedBeforeTheRenderTime(): void
    {
        $result = SpamCheckTest::createCheck(now: SpamCheckTest::RENDERED_AT)
            ->check(formName: 'contact', honeypot: 'x', signedRenderTime: '', emailAddress: null);

        $this->assertSame(SpamCheckResultEnum::HONEYPOT_FILLED, $result);
    }

    /**
     * @return array<string, array{string, int, int}>
     */
    public static function invalidSettingsProvider(): array
    {
        return [
            'short secret' => [str_repeat(string: 'x', times: 31), 3, 3600],
            'negative minimum' => [SpamCheckTest::SECRET, -1, 3600],
            'maximum equal to minimum' => [SpamCheckTest::SECRET, 3, 3],
            'maximum below minimum' => [SpamCheckTest::SECRET, 10, 5],
        ];
    }

    #[DataProvider('invalidSettingsProvider')]
    public function testInvalidSettingsThrow(string $secret, int $minSeconds, int $maxSeconds): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpamCheck(
            clock: new FixedClock(now: new DateTimeImmutable()),
            secret: $secret,
            rejectedEmailAddresses: [],
            minSeconds: $minSeconds,
            maxSeconds: $maxSeconds,
        );
    }
}
