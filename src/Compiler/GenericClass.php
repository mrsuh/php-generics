<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node;
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

    public function __construct(ClassFinderInterface $classFinder, ConcreteClassCache $concreteClassCache, GenericClassCache $genericClassCache, string $content)
    {
        $this->classFinder        = $classFinder;
        $this->concreteClassCache = $concreteClassCache;
        $this->genericClassCache  = $genericClassCache;
        $this->ast                = Parser::resolveNames(Parser::parse($content));

        /** @var Namespace_ $namespaceNode */
        $namespaceNode   = Parser::filterOne($this->ast, Namespace_::class);
        $this->namespace = $namespaceNode->name->toString();

        $classNodes = Parser::filter($this->ast, [Class_::class, Interface_::class, Trait_::class]);

        /** @var Class_ $classNode */
        $classNode  = current($classNodes);
        $this->name = $classNode->name->toString();

        /** @var GenericParameter[] $genericParameters */
        $this->parameters = (array)Parser::getGenericParameters($classNode);
    }

    public function generateConcreteClassName(array $genericsMap): string
    {
        $types = [];
        foreach (array_values($genericsMap) as $genericsType) {
            $types[] = ucfirst(str_replace('\\', '', $genericsType));
        }

        return $this->name . 'For' . implode('And', $types);
    }

    public function generateConcreteClassFqn(array $genericsMap): string
    {
        return $this->namespace . '\\' . $this->generateConcreteClassName($genericsMap);
    }

    public function getGenericsTypeMap(array $genericTypes): array
    {
        if (count($genericTypes) > count($this->parameters)) {
            throw new \TypeError('Invalid types count');
        }

        $genericsMap = [];
        foreach ($this->parameters as $index => $genericParameter) {
            $genericParameterName = Parser::getNodeName($genericParameter->name, $this->classFinder);

            if (isset($genericTypes[$index])) {
                $type = $genericTypes[$index];
            } else {
                $default = $genericParameter->default;
                if ($default === null) {
                    echo 'There is no default value for index ' . $index . PHP_EOL; //@todo
                    exit;
                }
                $type = Parser::getNodeName($default, $this->classFinder);
            }

            if (empty($type)) {
                echo 'Invalid type' . PHP_EOL; //@todo
                exit;
            }

            $genericsMap[$genericParameterName] = $type;
        }

        return $genericsMap;
    }

    public function generateConcreteClass(array $concreteGenericsMap, Result $result): ConcreteClass
    {
        $ast        = Parser::cloneAst($this->ast);
        $classNodes = Parser::filter($ast, [Class_::class, Interface_::class, Trait_::class]);

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
                Parser::setTypes($propertyNode->type, $concreteGenericsMap, $this->classFinder);
            }
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    Parser::setTypes($param->type, $concreteGenericsMap, $this->classFinder);
                }
            }

            if ($classMethodNode->returnType === null) {
                continue;
            }

            if ($classMethodNode->returnType !== null) {
                Parser::setTypes($classMethodNode->returnType, $concreteGenericsMap, $this->classFinder);
            }
        }

        Parser::setNodeName($classNode->name, $this->generateConcreteClassName($concreteGenericsMap));

        return new ConcreteClass(
            $this->generateConcreteClassName($concreteGenericsMap),
            $this->generateConcreteClassFqn($concreteGenericsMap),
            $ast
        );
    }

    private static function needToHandle(Node $node): bool
    {
        return is_array(Parser::getGenericParameters($node));
    }

    private function handleClass(Node &$node, array $genericsMap, Result $result): void
    {
        if (!self::needToHandle($node)) {
            return;
        }

        $genericClassFqn = Parser::getNodeName($node, $this->classFinder);
        if (!$this->genericClassCache->has($genericClassFqn)) {
            $implementsNodeClassContent = $this->classFinder->getFileContentByClassFqn($genericClassFqn);
            $this->genericClassCache->set($genericClassFqn, new self($this->classFinder, $this->concreteClassCache, $this->genericClassCache, $implementsNodeClassContent));
        }

        $genericClass = $this->genericClassCache->get($genericClassFqn);

        $genericsTypes    = $this->getGenericTypesByNodeAndMap($node, $genericsMap);
        $concreteClassMap = $genericClass->getGenericsTypeMap($genericsTypes);
        $concreteClassFqn = $genericClass->generateConcreteClassFqn($concreteClassMap);

        if (!$this->concreteClassCache->has($concreteClassFqn)) {
            $concreteClass = $genericClass->generateConcreteClass($concreteClassMap, $result);
            $result->addConcreteClass($concreteClass);
            $this->concreteClassCache->set($concreteClassFqn, $concreteClass);
        }

        $concreteClass = $this->concreteClassCache->get($concreteClassFqn);

        Parser::setNodeName($node, $concreteClass->fqn);
    }

    private function getGenericTypesByNodeAndMap(Node $node, array $map): array
    {
        /** @var GenericParameter[] $genericParameters */
        $parameters = (array)Parser::getGenericParameters($node);

        if (count($parameters) > count($map)) {
            throw new \TypeError('Invalid types count');
        }

        $types = [];
        foreach ($parameters as $genericParameter) {
            $genericParameterName = Parser::getNodeName($genericParameter->name, $this->classFinder);

            if (isset($map[$genericParameterName])) {
                $type = $map[$genericParameterName];
            } else {
                $type = $genericParameterName;
            }

            $types[] = $type;
        }

        return $types;
    }
}
