<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Cache\GenericClassCache;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
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

            $sourceFilePath = $sourceFile->getRealPath();

            if (!is_readable($sourceFilePath)) {
                throw new \RuntimeException(sprintf('Can\'t read file "%s"', $sourceFilePath));
            }

            $content = file_get_contents($sourceFilePath);
            if (empty($content)) {
                throw new \RuntimeException(sprintf('File "%s" has empty content', $sourceFilePath));
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
