<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node;

class HandledClass
{
    public string $fqn;
    /** @var Node[] */
    public array $ast;

    public function __construct(string $fqn, array $ast)
    {
        $this->fqn = $fqn;
        $this->ast = $ast;
    }
}
