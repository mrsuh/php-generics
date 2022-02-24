<?php

namespace Mrsuh\PhpGenerics\Compiler\Monomorphic;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use Mrsuh\PhpGenerics\Compiler\ClassParser;
use Mrsuh\PhpGenerics\Compiler\CompilerResult;
use Mrsuh\PhpGenerics\Compiler\ConcreteClass;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Cache\ConcreteClassCache;
use Mrsuh\PhpGenerics\Compiler\Monomorphic\Cache\GenericClassCache;
use Mrsuh\PhpGenerics\Compiler\Parser;
use PhpParser\Node;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
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
    /** @var Node[] */
    private array $ast;

    /**
     * @param Node[] $ast
     */
    public function __construct(
        ClassFinderInterface $classFinder,
        ConcreteClassCache $concreteClassCache,
        GenericClassCache $genericClassCache,
        array $ast
    )
    {
        $this->classFinder        = $classFinder;
        $this->concreteClassCache = $concreteClassCache;
        $this->genericClassCache  = $genericClassCache;
        $this->ast                = $ast;

        $parser = new ClassParser($ast);

        $this->namespace  = $parser->getNamespace();
        $this->name       = $parser->getClassName();
        $this->parameters = $parser->getParameters();
    }

    public function getConcreteClassCacheKey(array $arguments): string
    {
        return $this->namespace . '\\' . $this->generateConcreteClassName($arguments);
    }

    private function generateConcreteClassName(array $arguments): string
    {
        if (empty($arguments)) {
            return $this->name;
        }

        $parts = [];
        foreach ($arguments as $argument) {
            $parts[] = ucfirst(str_replace('\\', '', $argument));
        }

        return $this->name . 'For' . implode('And', $parts);
    }

    private function generateConcreteClassFqn(array $genericTypes): string
    {
        return $this->namespace . '\\' . $this->generateConcreteClassName($genericTypes);
    }

    public function generateConcreteClass(array $arguments, CompilerResult $result): ConcreteClass
    {
        if (count($this->parameters) === 0 && count($arguments) === 0) {
            $concreteGenericsMap = new GenericParametersMap($this->classFinder);
        } else {
            $concreteGenericsMap = GenericParametersMap::fromParametersAndArguments($this->classFinder, $this->parameters, $arguments);
        }

        $ast = Parser::cloneAst($this->ast);

        $parser = new ClassParser($ast);

        /** @var Node\Stmt\ClassLike $classNode */
        $classNode = Parser::filterOne($ast, [Class_::class, Interface_::class, Trait_::class]);

        foreach ($parser->getExtendNodes() as $extendsNode) {
            $this->handleClass($extendsNode, $concreteGenericsMap, $result);
        }

        foreach ($parser->getImplementNodes() as $implementsNode) {
            $this->handleClass($implementsNode, $concreteGenericsMap, $result);
        }

        foreach ($parser->getTraitNodes() as &$traitNode) {
            if (Parser::isGenericClass($traitNode)) {
                $this->handleClass($traitNode, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($traitNode, $concreteGenericsMap, $this->classFinder);
            }
        }

        foreach ($parser->getPropertyNodes() as $propertyNode) {
            if ($propertyNode->type !== null) {
                foreach (Parser::getNodeTypes($propertyNode->type) as &$nodeType) {
                    if (Parser::isGenericClass($nodeType)) {
                        $this->handleClass($nodeType, $concreteGenericsMap, $result);
                    } else {
                        Parser::setNodeType($nodeType, $concreteGenericsMap, $this->classFinder);
                    }
                }
                unset($nodeType);
            }
        }

        foreach ($parser->getClassMethodNodes() as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    foreach (Parser::getNodeTypes($param->type) as &$nodeType) {
                        if (Parser::isGenericClass($nodeType)) {
                            $this->handleClass($nodeType, $concreteGenericsMap, $result);
                        } else {
                            Parser::setNodeType($nodeType, $concreteGenericsMap, $this->classFinder);
                        }
                    }
                    unset($nodeType);
                }
            }

            if ($classMethodNode->returnType !== null) {
                foreach (Parser::getNodeTypes($classMethodNode->returnType) as &$nodeType) {
                    if (Parser::isGenericClass($nodeType)) {
                        $this->handleClass($nodeType, $concreteGenericsMap, $result);
                    } else {
                        Parser::setNodeType($nodeType, $concreteGenericsMap, $this->classFinder);
                    }
                    unset($nodeType);
                }
            }
        }

        foreach ($parser->getInstanceOfExprNodes() as $instanceofExprNode) {
            if (Parser::isGenericClass($instanceofExprNode->class)) {
                $this->handleClass($instanceofExprNode->class, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($instanceofExprNode->class, $concreteGenericsMap, $this->classFinder);
            }
        }

        foreach ($parser->getNewExprNodes() as $newExprNode) {
            if (Parser::isGenericClass($newExprNode->class)) {
                $this->handleClass($newExprNode->class, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($newExprNode->class, $concreteGenericsMap, $this->classFinder);
            }
        }

        foreach ($parser->getClassConstFetchNodes() as $classConstFetchStmtNode) {
            if (Parser::isGenericClass($classConstFetchStmtNode->class)) {
                $this->handleClass($classConstFetchStmtNode->class, $concreteGenericsMap, $result);
            } else {
                Parser::setNodeType($classConstFetchStmtNode->class, $concreteGenericsMap, $this->classFinder);
            }
        }

        Parser::setNodeName($classNode->name, $this->generateConcreteClassName($concreteGenericsMap->getConcreteArguments()));

        return new ConcreteClass(
            $this->generateConcreteClassName($concreteGenericsMap->getConcreteArguments()),
            $this->generateConcreteClassFqn($concreteGenericsMap->getConcreteArguments()),
            $this->namespace . '\\' . $this->name,
            $ast
        );
    }

    private function handleClass(Node &$node, GenericParametersMap $genericParametersMap, CompilerResult $result): void
    {
        if (!Parser::isGenericClass($node)) {
            return;
        }

        $genericClassFqn = Parser::getNodeName($node, $this->classFinder);
        if (!$this->genericClassCache->has($genericClassFqn)) {
            $genericClassFileContent = $this->classFinder->getFileContent($genericClassFqn);
            try {
                $genericClassAst = Parser::resolveNames(Parser::parse($genericClassFileContent));
            } catch (\Exception $exception) {
                throw new \RuntimeException(sprintf('Can\'t parse class "%s"', $genericClassFqn), $exception->getCode(), $exception);
            }
            $this->genericClassCache->set($genericClassFqn, new self($this->classFinder, $this->concreteClassCache, $this->genericClassCache, $genericClassAst));
        }

        $genericClass = $this->genericClassCache->get($genericClassFqn);

        $arguments = $genericParametersMap->generateFullArgumentsForNewGenericClass((array)Parser::getGenericParameters($node));

        $concreteClassCacheKey = $genericClass->getConcreteClassCacheKey($arguments);
        if (!$this->concreteClassCache->has($concreteClassCacheKey)) {
            $concreteClass = $genericClass->generateConcreteClass($arguments, $result);
            $result->addConcreteClass($concreteClass);
            $this->concreteClassCache->set($concreteClassCacheKey, $concreteClass);
        }

        $concreteClass = $this->concreteClassCache->get($concreteClassCacheKey);

        Parser::setNodeName($node, $concreteClass->fqn);
    }
}
