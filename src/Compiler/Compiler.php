<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Cache\GenericClassCache;
use Symfony\Component\Finder\Finder;

class Compiler
{
    private ClassFinderInterface $classFinder;

    public function __construct(ClassFinderInterface $classFinder)
    {
        $this->classFinder = $classFinder;
    }

    public function compile(string $directory): Result
    {
        $concreteClassCache = new ConcreteClassCache();
        $genericClassCache  = new GenericClassCache();

        $sourceFiles = (new Finder())
            ->in($directory)
            ->name('*.php')
            ->sortByName()
            ->files();

        $result = new Result();
        foreach ($sourceFiles as $sourceFile) {
            $content = file_get_contents($sourceFile->getRealPath());

            if (empty($content)) {
                throw new \RuntimeException('Can\'t read file ' . $sourceFile->getRealPath());
            }

            $ast = Parser::resolveNames(Parser::parse($content));

            if (!Parser::hasGenericClassUsages($ast)) {
                continue;
            }

            $usageGenericClass = new GenericClass($this->classFinder, $concreteClassCache, $genericClassCache, $ast);

            $usageConcreteClass = $usageGenericClass->generateConcreteClass([], $result);
            $result->addConcreteClass($usageConcreteClass);
        }

        return $result;
    }
}
