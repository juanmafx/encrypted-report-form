<?php

declare(strict_types=1);

namespace Fang\Http;

final readonly class Response
{
    public function __construct(
        public int $status,
        public string $body = '',
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, $body, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function redirect(string $location): self
    {
        return new self(303, '', ['Location' => $location]);
    }

    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->body, [...$this->headers, ...$headers]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
