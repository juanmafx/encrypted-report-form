<?php

declare(strict_types=1);

namespace Fang\Http;

final class SecurityHeaders
{
    public static function for(bool $secure): array
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), geolocation=(), microphone=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
        ];
        if ($secure) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return $headers;
    }
}
