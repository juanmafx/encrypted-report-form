<?php

declare(strict_types=1);

namespace Fang\Security;

final readonly class KeyRing
{
    private const string CONTEXT = 'fangkeys';
    private const int DATA_SUBKEY = 1;
    private const int RATE_LIMIT_SUBKEY = 2;

    public function __construct(#[\SensitiveParameter] private string $masterKey)
    {
    }

    public function dataKey(): string
    {
        return sodium_crypto_kdf_derive_from_key(32, self::DATA_SUBKEY, self::CONTEXT, $this->masterKey);
    }

    public function rateLimitKey(): string
    {
        return sodium_crypto_kdf_derive_from_key(32, self::RATE_LIMIT_SUBKEY, self::CONTEXT, $this->masterKey);
    }
}
