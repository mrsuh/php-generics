<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;

class Engine
{
    private ClassFinderInterface $classFinder;

    /** @var GenericClass[] */
    private array $genericClasses = [];

    private ConcreteClassCache $concreteClassCache;

    public function __construct(ClassFinderInterface $classFinder)
    {
        $this->concreteClassCache = new ConcreteClassCache();
        $this->classFinder        = $classFinder;
    }

    public function needToHandle(string $classFileContent): bool
    {
        $nodes = Parser::parse($classFileContent);

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            $generics = $newExprNode->class->getAttribute('generics');
            if (is_array($generics)) {
                return true;
            }
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            $generics = $classConstFetchStmtNode->class->getAttribute('generics');
            if (is_array($generics)) {
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
        foreach ($newExprNodes as &$newExprNode) {
            $this->handleNode($newExprNode, $result);
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as &$classConstFetchStmtNode) {
            $this->handleNode($classConstFetchStmtNode, $result);
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

    /**
     * @param New_|ClassConstFetch $node
     * @param Result               $result
     */
    private function handleNode(Node &$node, Result &$result): void
    {
        /** @var GenericParameter[] $genericParameters */
        $genericParameters = $node->class->getAttribute('generics');
        if (!is_array($genericParameters)) {
            return;
        }

        $genericTypes = [];
        foreach ($genericParameters as $genericParameter) {
            $genericTypes[] = Parser::getNodeName($genericParameter->name, $this->classFinder);
        }

        $genericClassFqn = $node->class->toString();
        if (!array_key_exists($genericClassFqn, $this->genericClasses)) {
            $genericClassFileContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            if ($genericClassFileContent === '') {
                echo 'Empty file err' . PHP_EOL; //@todo

                return;
            }

            $this->genericClasses[$genericClassFqn] = new GenericClass($this->classFinder, $this->concreteClassCache, $genericClassFileContent);
        }

        $genericClass = $this->genericClasses[$genericClassFqn];

        $genericTypesMap = $genericClass->getGenericsTypeMap($genericTypes);

        $concreteClassFqn = $genericClass->generateConcreteClassFqn($genericTypes);
        if ($this->concreteClassCache->get($concreteClassFqn) === null) {
            $concreteClass = $genericClass->generateConcreteClass($genericTypesMap, $result);
            $result->addConcreteClass($concreteClass);
            $this->concreteClassCache->set($concreteClassFqn, $concreteClass);
        }

        Parser::setNodeName($node->class, $concreteClassFqn);
    }
}
