<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Cache\GenericClassCache;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;

class GenericClass
{
    private ClassFinderInterface $classFinder;
    private ConcreteClassCache   $concreteClassCache;
    private GenericClassCache    $genericClassCache;

    /** @var GenericParameter[] */
    private array  $parameters;
    private string $name;
    private string $namespace;
    private array  $ast;

    public function __construct(
        ClassFinderInterface $classFinder,
        ConcreteClassCache $concreteClassCache,
        GenericClassCache $genericClassCache,
        string $content
    )
    {
        $this->classFinder        = $classFinder;
        $this->concreteClassCache = $concreteClassCache;
        $this->genericClassCache  = $genericClassCache;
        $this->ast                = Parser::resolveNames(Parser::parse($content));

        /** @var Namespace_ $namespaceNode */
        $namespaceNode   = Parser::filterOne($this->ast, [Namespace_::class]);
        $this->namespace = $namespaceNode->name->toString();

        /** @var Node\Stmt\ClassLike $classNode */
        $classNode = Parser::filterOne($this->ast, [Class_::class, Interface_::class, Trait_::class]);

        $this->name = $classNode->name->toString();

        /** @var GenericParameter[] $genericParameters */
        $this->parameters = (array)Parser::getGenericParameters($classNode);
    }

    public function getConcreteClassCacheKey(array $genericTypes): string
    {
        return $this->namespace . '\\' . $this->generateConcreteClassName($genericTypes);
    }

    private function generateConcreteClassName(array $genericsMap): string
    {
        if (empty($genericsMap)) {
            return $this->name;
        }

        $types = [];
        foreach (array_values($genericsMap) as $genericsType) {
            $types[] = ucfirst(str_replace('\\', '', $genericsType));
        }

        return $this->name . 'For' . implode('And', $types);
    }

    private function generateConcreteClassFqn(array $genericsMap): string
    {
        return $this->namespace . '\\' . $this->generateConcreteClassName($genericsMap);
    }

    public function generateConcreteClass(array $genericsTypes, Result $result): ConcreteClass
    {
        $concreteGenericsMap = GenericTypesMap::fromParametersAndArguments($this->classFinder, $this->parameters, $genericsTypes);

        $ast = Parser::cloneAst($this->ast);

        /** @var Node\Stmt\ClassLike $classNode */
        $classNode = Parser::filterOne($ast, [Class_::class, Interface_::class, Trait_::class]);

        $extendsNodes = [];
        if ($classNode instanceof Class_ && $classNode->extends !== null) {
            $extendsNodes = [$classNode->extends];
        }

        if ($classNode instanceof Interface_) {
            $extendsNodes = $classNode->extends;
        }

        foreach ($extendsNodes as &$extendsNode) {
            $this->handleClass($extendsNode, $concreteGenericsMap, $result);
        }

        if ($classNode instanceof Class_) {
            foreach ($classNode->implements as &$implementsNode) {
                $this->handleClass($implementsNode, $concreteGenericsMap, $result);
            }
        }

        /** @var Node\Stmt\TraitUse[] $traitUseNodes */
        $traitUseNodes = Parser::filter([$classNode], [Node\Stmt\TraitUse::class]);
        foreach ($traitUseNodes as $traitUseNode) {
            foreach ($traitUseNode->traits as &$traitNode) {
                $this->handleClass($traitNode, $concreteGenericsMap, $result);
            }
        }

        /** @var Property[] $propertyNodes */
        $propertyNodes = Parser::filter([$classNode], [Property::class]);
        foreach ($propertyNodes as $propertyNode) {
            if ($propertyNode->type !== null) {
                foreach (Parser::getNodeTypes($propertyNode->type) as &$nodeType) {
                    if (self::needToHandle($nodeType)) {
                        $this->handleClass($nodeType, $concreteGenericsMap, $result);
                    } else {
                        Parser::setNodeType($nodeType, $concreteGenericsMap, $this->classFinder);
                    }
                }
                unset($nodeType);
            }
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    foreach (Parser::getNodeTypes($param->type) as &$nodeType) {
                        if (self::needToHandle($nodeType)) {
                            $this->handleClass($nodeType, $concreteGenericsMap, $result);
                        } else {
                            Parser::setNodeType($nodeType, $concreteGenericsMap, $this->classFinder);
                        }
                    }
                    unset($nodeType);
                }
            }

            if ($classMethodNode->returnType === null) {
                continue;
            }

            foreach (Parser::getNodeTypes($classMethodNode->returnType) as &$nodeType) {
                if (self::needToHandle($nodeType)) {
                    $this->handleClass($nodeType, $concreteGenericsMap, $result);
                } else {
                    Parser::setNodeType($nodeType, $concreteGenericsMap, $this->classFinder);
                }
                unset($nodeType);
            }
        }

        /** @var Instanceof_[] $newExprNodes */
        $instanceofExprNodes = Parser::filter([$classNode], [Instanceof_::class]);
        foreach ($instanceofExprNodes as $instanceofExprNode) {
            if (self::needToHandle($instanceofExprNode->class)) {
                $this->handleClass($instanceofExprNode->class, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($instanceofExprNode->class, $concreteGenericsMap, $this->classFinder);
            }
        }

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter([$classNode], [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            if (self::needToHandle($newExprNode->class)) {
                $this->handleClass($newExprNode->class, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($newExprNode->class, $concreteGenericsMap, $this->classFinder);
            }
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter([$classNode], [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            if (self::needToHandle($classConstFetchStmtNode->class)) {
                $this->handleClass($classConstFetchStmtNode->class, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($classConstFetchStmtNode->class, $concreteGenericsMap, $this->classFinder);
            }
        }

        Parser::setNodeName($classNode->name, $this->generateConcreteClassName($concreteGenericsMap->getConcreteTypes()));

        return new ConcreteClass(
            $this->generateConcreteClassName($concreteGenericsMap->getConcreteTypes()),
            $this->generateConcreteClassFqn($concreteGenericsMap->getConcreteTypes()),
            $ast
        );
    }

    private static function needToHandle(Node $node): bool
    {
        return is_array(Parser::getGenericParameters($node));
    }

    private function handleClass(Node &$node, GenericTypesMap $genericTypesMap, Result $result): void
    {
        if (!self::needToHandle($node)) {
            return;
        }

        $genericClassFqn = Parser::getNodeName($node, $this->classFinder);
        if (!$this->genericClassCache->has($genericClassFqn)) {
            $genericClassFileContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            $this->genericClassCache->set($genericClassFqn, new self($this->classFinder, $this->concreteClassCache, $this->genericClassCache, $genericClassFileContent));
        }

        $genericClass = $this->genericClassCache->get($genericClassFqn);

        $genericsTypes = $genericTypesMap->generateFullArgumentsForNewGenericClass($this->classFinder, (array)Parser::getGenericParameters($node));

        $concreteClassCacheKey = $genericClass->getConcreteClassCacheKey($genericsTypes);
        if (!$this->concreteClassCache->has($concreteClassCacheKey)) {
            $concreteClass = $genericClass->generateConcreteClass($genericsTypes, $result);
            $result->addConcreteClass($concreteClass);
            $this->concreteClassCache->set($concreteClassCacheKey, $concreteClass);
        }

        $concreteClass = $this->concreteClassCache->get($concreteClassCacheKey);

        Parser::setNodeName($node, $concreteClass->fqn);
    }
}
