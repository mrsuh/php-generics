<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Cache\GenericClassCache;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;

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

        /** @var Class_ $classNode */
        $classNode = Parser::filterOne($nodes, Class_::class);
        if ($classNode !== null) {

            if (is_array(Parser::getGenericParameters($classNode))) {
                return false;
            }

            if ($classNode->extends !== null) {
                if (is_array(Parser::getGenericParameters($classNode->extends))) {
                    return true;
                }
            }

            foreach ($classNode->implements as $implementNode) {
                if (is_array(Parser::getGenericParameters($implementNode))) {
                    return true;
                }
            }
        }

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
        if ($classNode !== null) {
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

        $classNodes = Parser::filter($nodes, [Class_::class, Interface_::class, Trait_::class]);

        /** @var Class_ $classNode */
        $classNode = current($classNodes);

        $extendsNodes = [];
        if ($classNode instanceof Class_ && $classNode->extends !== null) {
            $extendsNodes = [$classNode->extends];
        }

        if ($classNode instanceof Interface_) {
            $extendsNodes = $classNode->extends;
        }

        foreach ($extendsNodes as &$extendsNode) {
            $this->handleNode($extendsNode, $result);
        }

        if ($classNode instanceof Class_) {
            foreach ($classNode->implements as &$implementsNode) {
                $this->handleNode($implementsNode, $result);
            }
        }

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            $this->handleNode($newExprNode->class, $result);
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            $this->handleNode($classConstFetchStmtNode->class, $result);
        }

        /** @var Node\Stmt\TraitUse[] $traitUseNodes */
        $traitUseNodes = Parser::filter($nodes, [Node\Stmt\TraitUse::class]);
        foreach ($traitUseNodes as $traitUseNode) {
            foreach ($traitUseNode->traits as $traitNode) {
                $this->handleNode($traitNode, $result);
            }
        }

        /** @var Property[] $propertyNodes */
        $propertyNodes = Parser::filter([$classNode], [Property::class]);
        foreach ($propertyNodes as $propertyNode) {
            if ($propertyNode->type !== null) {
                $this->handleNode($propertyNode->type, $result);
            }
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    $this->handleNode($param->type, $result);
                }
            }

            if ($classMethodNode->returnType === null) {
                continue;
            }

            $this->handleNode($classMethodNode->returnType, $result);
        }

        /** @var Namespace_ $namespaceNode */
        $namespaceNode = Parser::filterOne($nodes, Namespace_::class);
        $namespace     = $namespaceNode->name->toString();

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
