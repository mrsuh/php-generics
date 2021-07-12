<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

class Engine
{
    private ClassFinder $classFinder;

    public function __construct(ClassFinder $classFinder)
    {
        $this->classFinder = $classFinder;
    }

    public function needToHandle(string $classFileContent): bool
    {
        $nodes = Parser::parse($classFileContent);

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            $generics = $newExprNode->class->getAttribute('generics');
            if (is_array($generics) && count($generics) > 0) {
                return true;
            }
        }

        return false;
    }

    public function handle(string $classFileContent): Result
    {
        $nodes = Parser::resolveNames(Parser::parse($classFileContent));

        $result = new Result();

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            /** @var GenericParameter[] $genericParameters */
            $genericParameters = $newExprNode->class->getAttribute('generics');
            if (!is_array($genericParameters)) {
                continue;
            }

            $genericTypes = [];
            foreach ($genericParameters as $genericParameter) {
                $genericTypes[] = (string)$genericParameter->name->getAttribute('originalName');
            }

            $genericClassFqn         = $newExprNode->class->toString();
            $genericClassFileContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            if ($genericClassFileContent === '') {
                echo 'Empty file err' . PHP_EOL; //@todo
                continue;
            }

            $genericClass = new GenericClass($genericClassFileContent);

            $concreteClass = $genericClass->generateConcreteClass($genericTypes);
            $result->addConcreteClass($concreteClass);

            Parser::setNodeName($newExprNode->class, $concreteClass->fqn);
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            /** @var GenericParameter[] $genericParameters */
            $genericParameters = $classConstFetchStmtNode->class->getAttribute('generics');
            if (!is_array($genericParameters)) {
                continue;
            }

            $genericTypes = [];
            foreach ($genericParameters as $genericParameter) {
                $genericTypes[] = (string)$genericParameter->name->getAttribute('originalName');
            }

            $genericClassFqn         = $classConstFetchStmtNode->class->toString();
            $genericClassFileContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            if ($genericClassFileContent === '') {
                echo 'Empty file err' . PHP_EOL; //@todo
                continue;
            }

            $genericClass = new GenericClass($genericClassFileContent);

            $concreteClass = $genericClass->generateConcreteClass($genericTypes);
            $result->addConcreteClass($concreteClass);

            Parser::setNodeName($classConstFetchStmtNode->class, $concreteClass->fqn);
        }

        /** @var Namespace_ $namespaceNode */
        $namespaceNode = Parser::filterOne($nodes, Namespace_::class);
        $namespace     = $namespaceNode->name->toString();

        /** @var Class_ $classNode */
        $classNode = Parser::filterOne($nodes, Class_::class);
        $className = $classNode->name->toString();

        $result->addConcreteClass(new ConcreteClass(
            $className,
            $namespace . '\\' . $className,
            $nodes
        ));

        return $result;
    }
}