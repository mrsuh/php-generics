<?php

namespace Mrsuh\PhpGenerics\Compiler\Monomorphic\Cache;

use Mrsuh\PhpGenerics\Compiler\ConcreteClass;

class ConcreteClassCache
{
    private array $data = [];

    public function set(string $key, ConcreteClass $concreteClass): void
    {
        $this->data[$key] = $concreteClass;
    }

    public function get(string $key): ?ConcreteClass
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
