<?php

namespace Mrsuh\PhpGenerics\Compiler\TypeErased;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use Mrsuh\PhpGenerics\Compiler\ClassParser;
use Mrsuh\PhpGenerics\Compiler\CompilerResult;
use Mrsuh\PhpGenerics\Compiler\ConcreteClass;
use Mrsuh\PhpGenerics\Compiler\GenericParametersMap;
use Mrsuh\PhpGenerics\Compiler\Parser;
use PhpParser\Node;

class GenericClass
{
    private ClassFinderInterface $classFinder;

    /** @var Node[] */
    private array $ast;

    /**
     * @param Node[] $ast
     */
    public function __construct(ClassFinderInterface $classFinder, array $ast)
    {
        $this->classFinder = $classFinder;
        $this->ast         = $ast;
    }

    public function generateConcreteClass(CompilerResult $result): void
    {
        $ast = Parser::cloneAst($this->ast);

        $parser = new ClassParser($ast);

        $parameters = $parser->getParameters();
        if (count($parameters) === 0) {
            $concreteGenericsMap = new GenericParametersMap($this->classFinder);
        } else {
            $emptyArguments      = array_fill(0, count($parameters), true);
            $concreteGenericsMap = GenericParametersMap::fromParametersAndArguments($this->classFinder, $parameters, $emptyArguments);
        }

        foreach ($parser->getPropertyNodes() as $propertyNode) {
            if ($propertyNode->type !== null) {
                self::setNodeTypes($propertyNode->type, $concreteGenericsMap, $this->classFinder);
            }
        }

        foreach ($parser->getClassMethodNodes() as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    self::setNodeTypes($param->type, $concreteGenericsMap, $this->classFinder);
                }
            }

            if ($classMethodNode->returnType !== null) {
                self::setNodeTypes($classMethodNode->returnType, $concreteGenericsMap, $this->classFinder);
            }
        }

        foreach ($parser->getInstanceOfExprNodes() as $instanceofExprNode) {
            self::setNodeTypes($instanceofExprNode->class, $concreteGenericsMap, $this->classFinder);
        }

        foreach ($parser->getNewExprNodes() as $newExprNode) {
            self::setNodeTypes($newExprNode->class, $concreteGenericsMap, $this->classFinder);
        }

        foreach ($parser->getClassConstFetchNodes() as $classConstFetchStmtNode) {
            self::setNodeTypes($classConstFetchStmtNode->class, $concreteGenericsMap, $this->classFinder);
        }

        $namespace = $parser->getNamespace();
        $name      = $parser->getClassName();
        $result->addConcreteClass(new ConcreteClass(
            $name,
            $namespace . '\\' . $name,
            $namespace . '\\' . $name,
            $ast
        ));
    }

    public static function setNodeTypes(Node &$node, GenericParametersMap $genericParametersMap, $classFinder)
    {
        $types = [];
        foreach (Parser::getNodeTypes($node) as $nodeType) {
            if (Parser::isGenericClass($nodeType)) {
                $types[] = $nodeType;
                continue;
            }

            $currentType = Parser::getNodeName($nodeType, $classFinder);
            if (!$genericParametersMap->has($currentType)) {
                $types[] = $nodeType;
            }
        }

        if (count($types) === 0) {
            $node = null;
        }

        if (count($types) === 1) {
            $node = current($types);
        }

        if (count($types) > 1) {
            $node = new Node\UnionType($types);
        }
    }
}
