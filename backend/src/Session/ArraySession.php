<?php

declare(strict_types=1);

namespace Fang\Session;

final class ArraySession implements SessionStore
{
    private array $values = [];
    public int $regenerations = 0;

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function regenerate(): void
    {
        ++$this->regenerations;
    }

    public function destroy(): void
    {
        $this->values = [];
    }
}
