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
    private ConcreteClassCache   $concreteClassCache;
    private GenericClassCache    $genericClassCache;

    public function __construct(ClassFinderInterface $classFinder)
    {
        $this->concreteClassCache = new ConcreteClassCache();
        $this->genericClassCache  = new GenericClassCache();
        $this->classFinder        = $classFinder;
    }

    public function needToHandle(string $classFileContent): bool
    {
        $nodes = Parser::parse($classFileContent);

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            $generics = Parser::getGenericParameters($newExprNode->class);
            if (is_array($generics)) {
                return true;
            }
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            $generics = Parser::getGenericParameters($classConstFetchStmtNode->class);
            if (is_array($generics)) {
                return true;
            }
        }

        /** @var Class_ $classNode */
        $classNode = Parser::filterOne($nodes, Class_::class);
        if ($classNode !== null && !is_array(Parser::getGenericParameters($classNode))) {
            /** @var Node\Stmt\TraitUse[] $traitUseNodes */
            $traitUseNodes = Parser::filter([$classNode], [Node\Stmt\TraitUse::class]);
            foreach ($traitUseNodes as $traitUseNode) {
                foreach ($traitUseNode->traits as $traitNode) {
                    $generics = Parser::getGenericParameters($traitNode);
                    if (is_array($generics)) {
                        return true;
                    }
                }
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
            $classNode = $newExprNode->class;
            $this->handleNode($classNode, $result);
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            $classNode = $classConstFetchStmtNode->class;
            $this->handleNode($classNode, $result);
        }

        /** @var Node\Stmt\TraitUse[] $traitUseNodes */
        $traitUseNodes = Parser::filter($nodes, [Node\Stmt\TraitUse::class]);
        foreach ($traitUseNodes as $traitUseNode) {
            foreach ($traitUseNode->traits as $traitNode) {
                $this->handleNode($traitNode, $result);
            }
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
     * @param Node\Name $node
     * @param Result    $result
     */
    private function handleNode(Node $node, Result &$result): void
    {
        /** @var GenericParameter[] $genericParameters */
        $genericParameters = Parser::getGenericParameters($node);
        if (!is_array($genericParameters)) {
            return;
        }

        $genericClassFqn = $node->toString();
        if (!$this->genericClassCache->has($genericClassFqn)) {
            $genericClassFileContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            $this->genericClassCache->set($genericClassFqn, new GenericClass($this->classFinder, $this->concreteClassCache, $this->genericClassCache, $genericClassFileContent));
        }

        $genericClass = $this->genericClassCache->get($genericClassFqn);

        $genericTypes = [];
        foreach ($genericParameters as $genericParameter) {
            $genericTypes[] = Parser::getNodeName($genericParameter->name, $this->classFinder);
        }

        $genericTypesMap = $genericClass->getGenericsTypeMap($genericTypes);

        $concreteClassFqn = $genericClass->generateConcreteClassFqn($genericTypesMap);

        if (!$this->concreteClassCache->has($concreteClassFqn)) {
            $concreteClass = $genericClass->generateConcreteClass($genericTypesMap, $result);
            $result->addConcreteClass($concreteClass);
            $this->concreteClassCache->set($concreteClassFqn, $concreteClass);
        }

        Parser::setNodeName($node, $concreteClassFqn);
    }
}
