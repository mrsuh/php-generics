<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node\GenericParameter;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;

class GenericClass
{
    private string $name;
    private string $namespace;
    private array  $ast;

    public function __construct(string $content)
    {
        $this->ast = Parser::resolveNames(Parser::parse($content));

        /** @var Namespace_ $namespaceNode */
        $namespaceNode   = Parser::filterOne($this->ast, Namespace_::class);
        $this->namespace = $namespaceNode->name->toString();

        /** @var Class_ $classNode */
        $classNode  = Parser::filterOne($this->ast, Class_::class);
        $this->name = $classNode->name->toString();
    }

    public function generateConcreteClassName(array $genericTypes): string
    {
        $types = [];
        foreach ($genericTypes as $genericsType) {
            $types[] = ucfirst(str_replace('\\', '', $genericsType));
        }

        return $this->name . 'For' . implode('And', $types);
    }

    public function generateConcreteClassFqn(array $genericTypes): string
    {
        return $this->namespace . '\\' . $this->generateConcreteClassName($genericTypes);
    }

    public function generateConcreteClass(array $genericTypes): ConcreteClass
    {
        $ast   = $this->ast;
        $nodes = Parser::filter($ast, [Class_::class]);

        /** @var Class_ $classNode */
        $classNode = current($nodes);

        Parser::setNodeName($classNode->name, $this->generateConcreteClassName($genericTypes));

        /** @var GenericParameter[] $genericParameters */
        $genericParameters = (array)$classNode->getAttribute('generics');

        $genericParameterNames = [];
        foreach ($genericParameters as $genericParameter) {
            $genericParameterName = (string)$genericParameter->name->getAttribute('originalName');
            if (empty($genericParameterName)) {
                echo 'empty $genericParameterName' . PHP_EOL;
                break;
            }
            $genericParameterNames[] = $genericParameterName;
        }

        if (count($genericParameterNames) !== count($genericTypes)) {
            throw new \TypeError('Invalid types count');
        }

        $genericsMap = [];
        foreach ($genericParameterNames as $index => $genericParameterName) {
            $genericsMap[$genericParameterName] = $genericTypes[$index];
        }

        /** @var Property[] $propertyNodes */
        $propertyNodes = Parser::filter([$classNode], [Property::class]);
        foreach ($propertyNodes as $propertyNode) {
            $propertyType = $propertyNode->type->getAttribute('originalName');
            if (!array_key_exists($propertyType, $genericsMap)) {
                continue;
            }

            Parser::setNodeName($propertyNode->type, $genericsMap[$propertyType]);
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                $paramType = $param->type->getAttribute('originalName');

                if (!array_key_exists($paramType, $genericsMap)) {
                    continue;
                }

                Parser::setNodeName($param->type, $genericsMap[$paramType]);
            }

            if ($classMethodNode->returnType === null) {
                continue;
            }

            $returnType     = '';
            $returnTypeNode = $classMethodNode->returnType;
            switch (true) {
                case $returnTypeNode instanceof Identifier:
                case $returnTypeNode instanceof Name:
                    $returnType = (string)$returnTypeNode->getAttribute('originalName');
                    break;
                case $returnTypeNode instanceof NullableType:
                    $returnType = (string)$returnTypeNode->type->getAttribute('originalName');
                    break;
                case $returnTypeNode instanceof UnionType:
                    //@todo
                    break;
            }

            if ($returnType === '' || !array_key_exists($returnType, $genericsMap)) {
                continue;
            }

            Parser::setNodeName($classMethodNode->returnType, $genericsMap[$returnType]);
        }

        return new ConcreteClass(
            $this->generateConcreteClassName($genericTypes),
            $this->generateConcreteClassFqn($genericTypes),
            $ast
        );
    }
}
