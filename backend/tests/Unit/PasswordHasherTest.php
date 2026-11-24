<?php

declare(strict_types=1);

namespace Fang\Tests\Unit;

use Fang\Security\PasswordHasher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    private const string PASSWORD = 'a sufficiently long passphrase';

    public function testUsesArgon2id(): void
    {
        self::assertStringStartsWith('$argon2id$', (new PasswordHasher(1024, 1))->hash(self::PASSWORD));
    }

    public function testDefaultParametersMeetTheDocumentedCost(): void
    {
        $info = password_get_info((new PasswordHasher())->hash(self::PASSWORD));

        self::assertSame(65536, $info['options']['memory_cost']);
        self::assertSame(3, $info['options']['time_cost']);
    }

    public function testSaltMakesHashesUnique(): void
    {
        $hasher = new PasswordHasher(1024, 1);

        self::assertNotSame($hasher->hash(self::PASSWORD), $hasher->hash(self::PASSWORD));
    }

    public function testVerifiesOnlyTheCorrectPassword(): void
    {
        $hasher = new PasswordHasher(1024, 1);
        $hash = $hasher->hash(self::PASSWORD);

        self::assertTrue($hasher->verify(self::PASSWORD, $hash));
        self::assertFalse($hasher->verify('a different passphrase', $hash));
    }

    public function testRejectsShortPasswords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PasswordHasher(1024, 1))->hash('short');
    }

    public function testOverlongPasswordFailsVerification(): void
    {
        $hasher = new PasswordHasher(1024, 1);

        self::assertFalse($hasher->verify(str_repeat('a', 1025), $hasher->hash(self::PASSWORD)));
    }

    public function testDetectsOutdatedParameters(): void
    {
        $hash = (new PasswordHasher(1024, 1))->hash(self::PASSWORD);

        self::assertTrue((new PasswordHasher(2048, 2))->needsRehash($hash));
        self::assertFalse((new PasswordHasher(1024, 1))->needsRehash($hash));
    }

    public function testDecoyCostsOneVerificationAtConfiguredParameters(): void
    {
        $hasher = new PasswordHasher();
        $start = hrtime(true);
        $decoy = $hasher->decoyHash();
        $buildNanoseconds = hrtime(true) - $start;
        $start = hrtime(true);
        $matched = password_verify(self::PASSWORD, $decoy);
        $verifyNanoseconds = hrtime(true) - $start;
        $info = password_get_info($decoy);

        self::assertFalse($matched);
        self::assertSame('argon2id', $info['algoName']);
        self::assertSame(65536, $info['options']['memory_cost']);
        self::assertSame(3, $info['options']['time_cost']);
        self::assertLessThan($verifyNanoseconds / 4, $buildNanoseconds);
    }
}
