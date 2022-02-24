<?php

namespace Mrsuh\PhpGenerics\Compiler\Monomorphic;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use Mrsuh\PhpGenerics\Compiler\CompilerInterface;
use Mrsuh\PhpGenerics\Compiler\CompilerResult;
use Mrsuh\PhpGenerics\Compiler\FileIterator;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Cache\GenericClassCache;
use Mrsuh\PhpGenerics\Compiler\Parser;

class Compiler implements CompilerInterface
{
    private ClassFinderInterface $classFinder;
    private FileIterator         $fileIterator;

    public function __construct(ClassFinderInterface $classFinder)
    {
        $this->classFinder  = $classFinder;
        $this->fileIterator = new FileIterator();
    }

    public function compile(string $directory): CompilerResult
    {
        $concreteClassCache = new ConcreteClassCache();
        $genericClassCache  = new GenericClassCache();
        $result             = new CompilerResult();

        foreach ($this->fileIterator->iterate($directory) as $ast) {
            if (!Parser::hasGenericClassUsages($ast)) {
                continue;
            }

            $usageGenericClass  = new GenericClass($this->classFinder, $concreteClassCache, $genericClassCache, $ast);
            $usageConcreteClass = $usageGenericClass->generateConcreteClass([], $result);
            $result->addConcreteClass($usageConcreteClass);
        }

        return $result;
    }
}
