<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class Parser
{
    public static function parse(string $code): array
    {
        $lexer  = new Emulative();
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer);

        return $parser->parse($code);
    }

    public static function resolveNames(array $nodes): array
    {
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => true
        ]);

        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor($nameResolver);

        return $nodeTraverser->traverse($nodes);
    }

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

    public static function filterOne(array $nodes, array $classes): ?Node
    {
        $nodes = self::filter($nodes, $classes);

        if (count($nodes) > 1) {
            throw new \Exception('Nodes more than one');
        }

        if (count($nodes) === 0) {
            return null;
        }

        return current($nodes);
    }

    public static function build(array $nodes): string
    {
        $prettyPrinter = new Standard();

        return $prettyPrinter->prettyPrintFile($nodes) . PHP_EOL;
    }

    public static function getRelativeFilePath(string $fqn, ClassFinderInterface $classLoader): string
    {
        $psr4Prefixes = $classLoader->getPrefixesPsr4();
        foreach (array_keys($psr4Prefixes) as $namespace) {
            $fqn = str_replace($namespace, '', $fqn);
        }

        return str_replace('\\', DIRECTORY_SEPARATOR, $fqn) . '.php';
    }

    public static function getNodeName(Node $node, ClassFinderInterface $classFinder): string
    {
        switch (true) {
            case $node instanceof Node\Name:
                $fqn = $node->toString();
                if ($classFinder->isFileExistsByClassFqn($fqn)) {
                    return $fqn;
                }

                if ($node->hasAttribute('originalName')) {
                    return (string)$node->getAttribute('originalName');
                }

                return $fqn;
            case $node instanceof Node\Identifier:
                return (string)$node->name;
            default:
                throw new \TypeError(sprintf('Invalid nodeName class %s', get_class($node)));
        }
    }

    public static function setNodeName(Node &$node, string $type): void
    {
        switch (true) {
            case $node instanceof Node\Name:
                $parts = explode('\\', $type);
                if (self::isBuiltinType($type) || count($parts) === 1) {
                    $node = new Node\Identifier($type);
                } else {
                    $node->parts = $parts;
                }
                break;
            case $node instanceof Node\Identifier:
                $node->name = $type;
                break;
            default:
                throw new \TypeError(sprintf('Invalid node name type %s', get_class($node)));
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

    public static function setNodeType(Node &$node, GenericTypesMap $genericTypesMap, ClassFinderInterface $classFinder): void
    {
        $currentType = self::getNodeName($node, $classFinder);
        if ($genericTypesMap->has($currentType)) {
            self::setNodeName($node, $genericTypesMap->get($currentType));
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
                throw new \TypeError('Invalid type');
        }
    }

    public static function cloneAst(array $ast): array
    {
        $nodeTraverser = new NodeTraverser();
        $nodeTraverser->addVisitor(new CloningVisitor());

        return $nodeTraverser->traverse($ast);
    }

    public static function getGenericParameters(Node $node): ?array
    {
        return $node->getAttribute('generics');
    }
}

