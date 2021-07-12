<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\PrettyPrinter\Standard;

class Printer
{
    private Standard $prettyPrinter;

    public function __construct()
    {
        $this->prettyPrinter = new Standard();
    }

    public function printFile(array $ast): string
    {
        return $this->prettyPrinter->prettyPrintFile($ast);
    }
}
