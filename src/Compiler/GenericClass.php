<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Node\GenericParameter;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;

class GenericClass
{
    private string $fqn;
    private string $filePath;
    private string $name;
    private string $namespace;
    private string $content = '';
    private array  $ast;

    public function __construct(string $fqn, string $filePath)
    {
        $this->fqn      = $fqn;
        $this->filePath = $filePath;
        $this->content  = file_get_contents($filePath);
        $this->parse();
    }

    private function parse()
    {
        $this->ast = Parser::parse($this->content);

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

    public function generateConcreteClassAst(array $genericTypes): array
    {
        $ast   = $this->ast;
        $nodes = Parser::filter($ast, [Class_::class]);

        /** @var Class_ $classNode */
        $classNode = current($nodes);

        $classNode->name->name = $this->generateConcreteClassName($genericTypes);

        /** @var GenericParameter[] $genericParameters */
        $genericParameters = (array)$classNode->getAttribute('generics');

        $genericParameterNames = [];
        foreach ($genericParameters as $genericParameter) {
            $genericParameterNames[] = (string)$genericParameter->name->getAttribute('originalName');
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

            $propertyNode->type->parts[count($propertyNode->type->parts) - 1] = $genericsMap[$propertyType];
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                $paramType = $param->type->getAttribute('originalName');

                if (!array_key_exists($paramType, $genericsMap)) {
                    continue;
                }

                $param->type->parts[count($param->type->parts) - 1] = $genericsMap[$paramType];
            }

            if ($classMethodNode->returnType === null) {
                continue;
            }

            $returnType = $classMethodNode->returnType->getAttribute('originalName');
            if (!array_key_exists($returnType, $genericsMap)) {
                continue;
            }

            $classMethodNode->returnType->parts[count($classMethodNode->returnType->parts) - 1] = $genericsMap[$returnType];
        }

        return $ast;
    }
}
