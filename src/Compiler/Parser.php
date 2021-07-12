<?php

namespace Mrsuh\PhpGenerics\Compiler;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
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

    public static function filterOne(array $nodes, $class): ?Node
    {
        $nodes = (new NodeFinder())->find($nodes, function (Node $node) use ($class): bool {
            if ($node instanceof $class) {
                return true;
            }

            return false;
        });

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

    public static function getRelativeDir(string $fqn, ClassFinder $classLoader): string
    {
        $psr4Prefixes = $classLoader->getPrefixesPsr4();
        foreach (array_keys($psr4Prefixes) as $namespace) {
            $fqn = str_replace($namespace, '', $fqn);
        }

        return str_replace('\\', DIRECTORY_SEPARATOR, $fqn);
    }
}

