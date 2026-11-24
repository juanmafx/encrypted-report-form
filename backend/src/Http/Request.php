<?php

declare(strict_types=1);

namespace Fang\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $post = [],
        public string $clientIp = '0.0.0.0',
        public int $contentLength = 0,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $length = $_SERVER['CONTENT_LENGTH'] ?? 0;

        return new self(
            $method,
            is_string($path) ? $path : '/',
            $_POST,
            $clientIp,
            is_numeric($length) ? (int) $length : 0,
        );
    }

    public function rateLimitSubject(): string
    {
        if (filter_var($this->clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $this->clientIp;
        }
        $packed = (string) inet_pton($this->clientIp);
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xFF\xFF")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }

    public function postString(string $key): string
    {
        $value = $this->post[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
