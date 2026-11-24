<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    #[DataProvider('clientAddresses')]
    public function testRateLimitSubject(string $clientIp, string $expected): void
    {
        self::assertSame($expected, (new Request('POST', '/', [], $clientIp))->rateLimitSubject());
    }

    public static function clientAddresses(): array
    {
        return [
            'ipv4 is kept' => ['203.0.113.10', '203.0.113.10'],
            'ipv6 is reduced to its /64' => ['2001:db8:1:1:aaaa:bbbb:cccc:dddd', '2001:db8:1:1::/64'],
            'ipv6 spelling does not matter' => ['2001:0DB8:0001:0001::1', '2001:db8:1:1::/64'],
            'ipv4-mapped ipv6 maps to ipv4' => ['::ffff:203.0.113.10', '203.0.113.10'],
            'non-address is kept' => ['unknown', 'unknown'],
        ];
    }
}
