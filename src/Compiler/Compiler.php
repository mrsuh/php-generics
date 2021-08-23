<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Cache\GenericClassCache;
use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use Mrsuh\PhpGenerics\Compiler\Generic\GenericClass;
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
                throw new \RuntimeException(sprintf('File "%s" is not readable', $sourceFilePath));
            }

            $content = file_get_contents($sourceFilePath);
            if ($content === false) {
                throw new \RuntimeException(sprintf('Can\t read file "%s"', $sourceFilePath));
            }

            if (empty($content)) {
                throw new \RuntimeException(sprintf('File "%s" has empty content', $sourceFilePath));
            }

            try {
                $ast = Parser::resolveNames(Parser::parse($content));
            } catch (\Exception $exception) {
                throw new \RuntimeException(sprintf('Can\'t parse file "%s"', $sourceFilePath), $exception->getCode(), $exception);
            }

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
