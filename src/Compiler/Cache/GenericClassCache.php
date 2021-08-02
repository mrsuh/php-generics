<?php

namespace Mrsuh\PhpGenerics\Compiler\Cache;

use Mrsuh\PhpGenerics\Compiler\GenericClass;

class GenericClassCache
{
    private array $data = [];

    public function set(string $key, GenericClass $genericClass): void
    {
        $this->data[$key] = $genericClass;
    }

    public function get(string $key): ?GenericClass
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return null;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}
