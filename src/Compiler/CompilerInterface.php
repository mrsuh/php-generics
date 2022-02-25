<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;

interface CompilerInterface
{
    const MONOMORPHIC = 'monomorphic';
    const TYPE_ERASED = 'type-erased';

    public function __construct(ClassFinderInterface $classFinder);

    public function compile(string $directory): CompilerResult;
}
