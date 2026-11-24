<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Security\TotpService;
use Fang\Tests\Support\FixedClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    #[DataProvider('rfc6238Vectors')]
    public function testMatchesRfc6238Vectors(int $timestamp, string $code): void
    {
        $service = new TotpService(new FixedClock($timestamp));

        self::assertSame(intdiv($timestamp, 30), $service->matchingStep(self::RFC_SECRET, $code));
    }

    public static function rfc6238Vectors(): array
    {
        return [
            'time 59' => [59, '287082'],
            'time 1111111109' => [1111111109, '081804'],
            'time 1234567890' => [1234567890, '005924'],
            'time 2000000000' => [2000000000, '279037'],
        ];
    }

    public function testAcceptsOneStepOfDrift(): void
    {
        $service = new TotpService(new FixedClock(1111111109 + 30));

        self::assertSame(intdiv(1111111109, 30), $service->matchingStep(self::RFC_SECRET, '081804'));
    }

    public function testRejectsCodesOutsideTheDriftWindow(): void
    {
        $service = new TotpService(new FixedClock(1111111109 + 120));

        self::assertNull($service->matchingStep(self::RFC_SECRET, '081804'));
    }

    #[DataProvider('malformedCodes')]
    public function testRejectsMalformedCodes(string $code): void
    {
        self::assertNull((new TotpService(new FixedClock()))->matchingStep(self::RFC_SECRET, $code));
    }

    public static function malformedCodes(): array
    {
        return [[''], ['12345'], ['1234567'], ['abcdef'], ["123456\n"]];
    }

    public function testProvisioningUriIdentifiesTheIssuer(): void
    {
        $service = new TotpService(new FixedClock());
        $uri = $service->provisioningUri($service->generateSecret(), 'site-admin');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('issuer=Fang', $uri);
    }
}
