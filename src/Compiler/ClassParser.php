<?php

namespace Mrsuh\PhpGenerics\Compiler;

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

class ClassParser
{
    private Node $namespaceNode;
    private Node $classNode;

    /**
     * @param Node[] $ast
     */
    public function __construct(array $ast)
    {
        $this->namespaceNode = Parser::filterOne($ast, [Namespace_::class]);
        $this->classNode     = Parser::filterOne($ast, [Class_::class, Interface_::class, Trait_::class]);
    }

    /**
     * @return GenericParameter[]
     */
    public function getParameters(): array
    {
        return Parser::getGenericParameters($this->classNode);
    }

    public function getClassName(): string
    {
        return $this->classNode->name->toString();
    }

    public function getClassNameNode(): Node
    {
        return $this->classNode->name;
    }

    public function getNamespace(): string
    {
        return $this->namespaceNode->name->toString();
    }

    /**
     * @return Node\Name[]
     */
    public function getExtendNodes(): array
    {
        if ($this->classNode instanceof Class_ && $this->classNode->extends !== null) {
            return [&$this->classNode->extends];
        }

        if ($this->classNode instanceof Interface_) {
            return $this->classNode->extends;
        }

        return [];
    }

    /**
     * @return Node\Name[]
     */
    public function getImplementNodes(): array
    {
        if ($this->classNode instanceof Class_) {
            return $this->classNode->implements;
        }

        return [];
    }

    /**
     * @return Node\Name[]
     */
    public function getTraitNodes(): array
    {
        $traits = [];
        /** @var Node\Stmt\TraitUse[] $traitUseNodes */
        $traitUseNodes = Parser::filter([$this->classNode], [Node\Stmt\TraitUse::class]);
        foreach ($traitUseNodes as $traitUseNode) {
            foreach ($traitUseNode->traits as $traitNode) {
                $traits[] = $traitNode;
            }
        }

        return $traits;
    }

    /**
     * @return Property[]
     */
    public function getPropertyNodes(): array
    {
        return Parser::filter([$this->classNode], [Property::class]);
    }

    /**
     * @return ClassMethod[]
     */
    public function getClassMethodNodes(): array
    {
        return Parser::filter([$this->classNode], [ClassMethod::class]);
    }

    /**
     * @return Instanceof_[]
     */
    public function getInstanceOfExprNodes(): array
    {
        return Parser::filter([$this->classNode], [Instanceof_::class]);
    }

    /**
     * @return New_[]
     */
    public function getNewExprNodes(): array
    {
        return Parser::filter([$this->classNode], [New_::class]);
    }

    /**
     * @return ClassConstFetch[]
     */
    public function getClassConstFetchNodes(): array
    {
        return Parser::filter([$this->classNode], [ClassConstFetch::class]);
    }
}
