<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Mrsuh\PhpGenerics\Compiler\ClassFinder\ClassFinderInterface;
use Mrsuh\PhpGenerics\Compiler\Generic\GenericParametersMap;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

class Parser
{
    public static function parse(string $code): array
    {
        $lexer  = new Emulative();
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer);

        return $parser->parse($code);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public static function resolveNames(array $nodes): array
    {
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => true
        ]);

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nameResolver);

        return $nodeTraverser->traverse($nodes);
    }

    /**
     * @param Node[]   $nodes
     * @param string[] $classes
     * @return Node[]
     */
    public static function filter(array $nodes, array $classes): array
    {
        return (new NodeFinder())->find($nodes, function (Node $node) use ($classes): bool {
            foreach ($classes as $class) {
                if ($node instanceof $class) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param Node[]   $nodes
     * @param string[] $classes
     * @return Node|null
     */
    public static function filterOne(array $nodes, array $classes): ?Node
    {
        $nodes = self::filter($nodes, $classes);

        if (count($nodes) > 1) {
            throw new \RuntimeException(sprintf(
                'AST has more than one node for classes "%s"',
                implode(', ', $classes)
            ));
        }

        if (count($nodes) === 0) {
            return null;
        }

        return current($nodes);
    }

    public static function getNodeName(Node $node, ClassFinderInterface $classFinder): string
    {
        switch (true) {
            case $node instanceof Node\Name:
                $fqn = $node->toString();
                if ($classFinder->isSourceFileExistsByClassFqn($fqn)) {
                    return $fqn;
                }

                if ($node->hasAttribute('originalName')) {
                    return (string)$node->getAttribute('originalName');
                }

                return $fqn;
            case $node instanceof Node\Identifier:
                return (string)$node->name;
            default:
                throw new \TypeError(sprintf('Invalid node name class "%s" for getNodeName()', get_class($node)));
        }
    }

    public static function setNodeName(Node &$node, string $type): void
    {
        switch (true) {
            case $node instanceof Node\Name:
                if (self::isBuiltinType($type)) {
                    $node = new Node\Identifier($type);
                } else {
                    $node->parts = explode('\\', $type);
                }
                break;
            case $node instanceof Node\Identifier:
                $node->name = $type;
                break;
            default:
                throw new \TypeError(sprintf('Invalid node name class "%s" for setNodeName()', get_class($node)));
        }
    }

    public static function isBuiltinType(string $type): bool
    {
        $builtinTypes = [
            'bool'     => true,
            'int'      => true,
            'float'    => true,
            'string'   => true,
            'iterable' => true,
            'object'   => true,
            'mixed'    => true,
            'array'    => true,
        ];

        return isset($builtinTypes[strtolower($type)]);
    }

    public static function setNodeType(Node &$node, GenericParametersMap $genericParametersMap, ClassFinderInterface $classFinder): void
    {
        $currentType = self::getNodeName($node, $classFinder);
        if ($genericParametersMap->has($currentType)) {
            self::setNodeName($node, $genericParametersMap->get($currentType));
        }
    }

    public static function getNodeTypes(Node &$node): array
    {
        switch (true) {
            case $node instanceof Node\Name:
            case $node instanceof Node\Identifier:
                return [&$node];
            case $node instanceof Node\NullableType:
                return [&$node->type];
            case $node instanceof Node\UnionType:
                return $node->types;
            default:
                throw new \TypeError(sprintf('Invalid node type "%s" for getNodeTypes()', get_class($node)));
        }
    }

    /**
     * @param Node[] $ast
     * @return Node[]
     */
    public static function cloneAst(array $ast): array
    {
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new CloningVisitor());

        return $nodeTraverser->traverse($ast);
    }

    /**
     * @return Node\GenericParameter[]
     */
    public static function getGenericParameters(Node $node): array
    {
        return (array)$node->getAttribute('generics');
    }

    public static function hasGenericParameters(Node $node): bool
    {
        return is_array($node->getAttribute('generics'));
    }

    public static function isGenericClass(Node $node): bool
    {
        return Parser::hasGenericParameters($node);
    }

    /**
     * @param Node[] $ast
     */
    public static function hasGenericClassUsages(array $ast): bool
    {
        /** @var Node\Stmt\ClassLike $classNode */
        $classNode = Parser::filterOne($ast, [Class_::class, Interface_::class, Trait_::class]);
        if ($classNode === null) {
            return false;
        }

        if (Parser::isGenericClass($classNode)) {
            return false;
        }

        if ($classNode instanceof Class_ || $classNode instanceof Interface_) {
            if ($classNode->extends !== null && Parser::isGenericClass($classNode->extends)) {
                return true;
            }
        }

        if ($classNode instanceof Class_) {
            foreach ($classNode->implements as $implementNode) {
                if (Parser::isGenericClass($implementNode)) {
                    return true;
                }
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

        /** @var Property[] $propertyNodes */
        $propertyNodes = Parser::filter([$classNode], [Property::class]);
        foreach ($propertyNodes as $propertyNode) {
            if ($propertyNode->type !== null) {
                foreach (Parser::getNodeTypes($propertyNode->type) as $nodeType) {
                    if (Parser::isGenericClass($nodeType)) {
                        return true;
                    }
                }
            }
        }

        /** @var ClassMethod[] $classMethodNodes */
        $classMethodNodes = Parser::filter([$classNode], [ClassMethod::class]);
        foreach ($classMethodNodes as $classMethodNode) {
            foreach ($classMethodNode->params as $param) {
                if ($param->type !== null) {
                    foreach (Parser::getNodeTypes($param->type) as $nodeType) {
                        if (Parser::isGenericClass($nodeType)) {
                            return true;
                        }
                    }
                }
            }

            if ($classMethodNode->returnType !== null) {
                foreach (Parser::getNodeTypes($classMethodNode->returnType) as $nodeType) {
                    if (Parser::isGenericClass($nodeType)) {
                        return true;
                    }
                }
            }
        }

        /** @var Instanceof_[] $newExprNodes */
        $instanceofExprNodes = Parser::filter([$classNode], [Instanceof_::class]);
        foreach ($instanceofExprNodes as $instanceofExprNode) {
            if (Parser::isGenericClass($instanceofExprNode->class)) {
                return true;
            }
        }

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($ast, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            if (Parser::isGenericClass($newExprNode->class)) {
                return true;
            }
        }

        /** @var ClassConstFetch[] $classConstFetchStmtNodes */
        $classConstFetchStmtNodes = Parser::filter($ast, [ClassConstFetch::class]);
        foreach ($classConstFetchStmtNodes as $classConstFetchStmtNode) {
            if (Parser::isGenericClass($classConstFetchStmtNode->class)) {
                return true;
            }
        }

        return false;
    }
}

