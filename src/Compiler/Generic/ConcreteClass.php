<?php

namespace Mrsuh\PhpGenerics\Compiler\Generic;

use PhpParser\Node;

class ConcreteClass
{
    public string $name;
    public string $fqn;
    public string $genericFqn;
    /** @var Node[] */
    public array $ast;

    public function __construct(string $name, string $fqn, string $genericFqn, array $ast)
    {
        $this->name       = $name;
        $this->fqn        = $fqn;
        $this->genericFqn = $genericFqn;
        $this->ast        = $ast;
    }
}
