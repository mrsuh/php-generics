<?php

namespace Mrsuh\PhpGenerics\Compiler;

class ConcreteClass
{
    public string $name;
    public string $fqn;
    /** @var Node[] */
    public array $ast;

    public function __construct(string $name, string $fqn, array $ast)
    {
        $this->name = $name;
        $this->fqn  = $fqn;
        $this->ast  = $ast;
    }
}
