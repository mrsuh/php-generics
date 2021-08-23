<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class Printer
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    /**
     * @param Node[] $ast
     * @return string
     */
    public function printFile(array $ast): string
    {
        return $this->prettyPrinter->prettyPrintFile($ast);
    }
}
