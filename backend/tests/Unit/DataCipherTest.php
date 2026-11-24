<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Security\DataCipher;
use Fang\Security\DecryptionFailed;
use PHPUnit\Framework\TestCase;

final class DataCipherTest extends TestCase
{
    private DataCipher $cipher;

    protected function setUp(): void
    {
        $this->cipher = new DataCipher(random_bytes(32));
    }

    public function testRoundTripPreservesPlaintext(): void
    {
        $encoded = $this->cipher->encrypt('private value é', 'ctx');

        self::assertSame('private value é', $this->cipher->decrypt($encoded, 'ctx'));
        self::assertStringNotContainsString('private', $encoded);
    }

    public function testEachEncryptionUsesAFreshNonce(): void
    {
        self::assertNotSame($this->cipher->encrypt('same', 'ctx'), $this->cipher->encrypt('same', 'ctx'));
    }

    public function testCiphertextCannotMoveToAnotherContext(): void
    {
        $encoded = $this->cipher->encrypt('value', 'report-a|email');

        $this->expectException(DecryptionFailed::class);
        $this->cipher->decrypt($encoded, 'report-b|email');
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $encoded = $this->cipher->encrypt('value', 'ctx');
        $position = strlen($encoded) - 3;
        $encoded[$position] = $encoded[$position] === 'A' ? 'B' : 'A';

        $this->expectException(DecryptionFailed::class);
        $this->cipher->decrypt($encoded, 'ctx');
    }

    public function testWrongKeyIsRejected(): void
    {
        $encoded = $this->cipher->encrypt('value', 'ctx');

        $this->expectException(DecryptionFailed::class);
        (new DataCipher(random_bytes(32)))->decrypt($encoded, 'ctx');
    }

    public function testMalformedInputIsRejected(): void
    {
        $this->expectException(DecryptionFailed::class);
        $this->cipher->decrypt('v1.%%%', 'ctx');
    }

    public function testUnknownVersionIsRejected(): void
    {
        $this->expectException(DecryptionFailed::class);
        $this->cipher->decrypt('v0.abc', 'ctx');
    }
}
