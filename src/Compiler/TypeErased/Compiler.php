<?php

namespace Mrsuh\PhpGenerics\Compiler\TypeErased;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use Mrsuh\PhpGenerics\Compiler\CompilerInterface;
use Mrsuh\PhpGenerics\Compiler\CompilerResult;
use Mrsuh\PhpGenerics\Compiler\FileIterator;
use Mrsuh\PhpGenerics\Compiler\Parser;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

class Compiler implements CompilerInterface
{
    private ClassFinderInterface $classFinder;

    public function __construct(ClassFinderInterface $classFinder)
    {
        $this->classFinder = $classFinder;
    }

    public function compile(string $directory): CompilerResult
    {
        $result = new CompilerResult();
        foreach (FileIterator::iterateAsAst($directory) as $ast) {

            /** @var Node\Stmt\ClassLike $classNode */
            $classNode = Parser::filterOne($ast, [Class_::class, Interface_::class, Trait_::class]);
            if ($classNode === null) {
                continue;
            }

            if (!Parser::isGenericClass($classNode) && !Parser::hasGenericClassUsages($ast)) {
                continue;
            }

            $usageGenericClass = new GenericClass($this->classFinder, $ast);
            $usageGenericClass->generateConcreteClass($result);
        }

        return $result;
    }
}
