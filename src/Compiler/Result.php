<?php

namespace Mrsuh\PhpGenerics\Compiler;

class Result
{
    /** @var ConcreteClass[] */
    private array $concreteClasses;

    public function addConcreteClass(ConcreteClass $concreteClass): void
    {
        $this->concreteClasses[] = $concreteClass;
    }

    /**
     * @return ConcreteClass[]
     */
    public function getConcreteClasses(): array
    {
        return $this->concreteClasses;
    }
}
