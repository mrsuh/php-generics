<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Cache\GenericClassCache;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
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

    public function hasGenericClassUsages(string $classFileContent): bool
    {
        $nodes = Parser::parse($classFileContent);

        /** @var Class_ $classNode */
        $classNode = Parser::filterOne($nodes, [Class_::class]);
        if ($classNode !== null) {

            if (Parser::isGenericClass($classNode)) {
                return false;
            }

            if ($classNode->extends !== null) {
                if (Parser::isGenericClass($classNode->extends)) {
                    return true;
                }
            }

            foreach ($classNode->implements as $implementNode) {
                if (Parser::isGenericClass($implementNode)) {
                    return true;
                }
            }

            /** @var Node\Stmt\TraitUse[] $traitUseNodes */
            $traitUseNodes = Parser::filter([$classNode], [Node\Stmt\TraitUse::class]);
            foreach ($traitUseNodes as $traitUseNode) {
                foreach ($traitUseNode->traits as $traitNode) {
                    if (Parser::isGenericClass($traitNode)) {
                        return true;
                    }
                }
            }
        }

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            if (Parser::isGenericClass($newExprNode->class)) {
                return true;
            }
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            if (Parser::isGenericClass($classConstFetchStmtNode->class)) {
                return true;
            }
        }

        return false;
    }

    public function handle(string $classFileContent): Result
    {
        $result = new Result();

        $nodes = Parser::resolveNames(Parser::parse($classFileContent));

        /** @var Node\Stmt\ClassLike $classNode */
        $classNode = Parser::filterOne($nodes, [Class_::class, Interface_::class, Trait_::class]);

        $extendsNodes = [];
        if ($classNode instanceof Class_ && $classNode->extends !== null) {
            $extendsNodes = [$classNode->extends];
        }

        if ($classNode instanceof Interface_) {
            $extendsNodes = $classNode->extends;
        }

        foreach ($extendsNodes as $extendsNode) {
            $this->handleClass($extendsNode, $result);
        }

        if ($classNode instanceof Class_) {
            foreach ($classNode->implements as $implementsNode) {
                $this->handleClass($implementsNode, $result);
            }
        }

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            $this->handleClass($newExprNode->class, $result);
        }

        /** @var Instanceof_[] $newExprNodes */
        $instanceofExprNodes = Parser::filter($nodes, [Instanceof_::class]);
        foreach ($instanceofExprNodes as $instanceofExprNode) {
            $this->handleClass($instanceofExprNode->class, $result);
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($nodes, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            $this->handleClass($classConstFetchStmtNode->class, $result);
        }

        /** @var Node\Stmt\TraitUse[] $traitUseNodes */
        $traitUseNodes = Parser::filter($nodes, [Node\Stmt\TraitUse::class]);
        foreach ($traitUseNodes as $traitUseNode) {
            foreach ($traitUseNode->traits as $traitNode) {
                $this->handleClass($traitNode, $result);
            }
        }

        /** @var Property[] $propertyNodes */
        $propertyNodes = Parser::filter([$classNode], [Property::class]);
        foreach ($propertyNodes as $propertyNode) {
            if ($propertyNode->type !== null) {
                $this->handleClass($propertyNode->type, $result);
            }
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    $this->handleClass($param->type, $result);
                }
            }

            if ($classMethodNode->returnType === null) {
                continue;
            }

            $this->handleClass($classMethodNode->returnType, $result);
        }

        /** @var Namespace_ $namespaceNode */
        $namespaceNode = Parser::filterOne($nodes, [Namespace_::class]);
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
    private function handleClass(Node $node, Result $result): void
    {
        if (!Parser::isGenericClass($node)) {
            return;
        }

        $genericClassFqn = Parser::getNodeName($node, $this->classFinder);
        if (!$this->genericClassCache->has($genericClassFqn)) {
            $genericClassFileContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            $this->genericClassCache->set($genericClassFqn, new GenericClass($this->classFinder, $this->concreteClassCache, $this->genericClassCache, $genericClassFileContent));
        }

        $genericClass = $this->genericClassCache->get($genericClassFqn);

        $genericTypes = [];
        foreach (Parser::getGenericParameters($node) as $genericParameter) {
            $genericTypes[] = Parser::getNodeName($genericParameter->name, $this->classFinder);
        }

        $concreteClassCacheKey = $genericClass->getConcreteClassCacheKey($genericTypes);

        if (!$this->concreteClassCache->has($concreteClassCacheKey)) {
            $concreteClass = $genericClass->generateConcreteClass($genericTypes, $result);
            $result->addConcreteClass($concreteClass);
            $this->concreteClassCache->set($concreteClassCacheKey, $concreteClass);
        }

        $concreteClass = $this->concreteClassCache->get($concreteClassCacheKey);

        Parser::setNodeName($node, $concreteClass->fqn);
    }
}
