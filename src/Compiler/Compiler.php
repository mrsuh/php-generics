<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Composer\Autoload\ClassLoader;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

class Compiler
{
    private ClassLoader $loader;
    private string      $cacheDir;

    /** @var GenericClass[] */
    private array $loaded = [];

    public function __construct(ClassLoader $loader, string $cacheDir)
    {
        $this->loader   = $loader;
        $this->cacheDir = $cacheDir;
    }

    public function needToHandle(string $classFqn): bool
    {
        $classFilePath = $this->loader->findFile($classFqn);
        if (!$classFilePath) {
            echo $classFqn . PHP_EOL;
            echo 'ERR 1' . PHP_EOL;

            return false;
        }

        $classFileContent = file_get_contents($classFilePath);

        $nodes = Parser::parse($classFileContent);

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            $generics = $newExprNode->class->getAttribute('generics');
            if (is_array($generics) && count($generics) > 0) {
                return true;
            }
        }

        return false;
    }

    public function handle(string $classFqn): void
    {
        echo 'Load ' . $classFqn . PHP_EOL;

        $classFilePath = $this->loader->findFile($classFqn);
        if (!$classFilePath) {
            echo 'ERR 2' . PHP_EOL;
            exit(1);
        }

        $classFileContent = file_get_contents($classFilePath);

        $nodes = Parser::parse($classFileContent);

        /** @var Class_ $classNode */
        $classNode = Parser::filterOne($nodes, Class_::class);
        $className = $classNode->name->toString();

        /** @var New_[] $newExprNodes */
        $newExprNodes = Parser::filter($nodes, [New_::class]);
        foreach ($newExprNodes as $newExprNode) {
            /** @var GenericParameter[] $genericParameters */
            $genericParameters = $newExprNode->class->getAttribute('generics');
            if (!is_array($genericParameters)) {
                continue;
            }

            $genericTypes = [];
            foreach ($genericParameters as $genericParameter) {
                $genericTypes[] = (string)$genericParameter->name->getAttribute('originalName');
            }

            $genericClassFqn      = $newExprNode->class->toString();
            $genericClassFilePath = $this->loader->findFile($genericClassFqn);

            $genericClass = new GenericClass($genericClassFilePath, $genericClassFqn);

            $concreteClassName     = $genericClass->generateConcreteClassName($genericTypes);
            $concreteClassFqn      = $genericClass->generateConcreteClassFqn($genericTypes);
            $concreteClassContent  = $genericClass->generateConcreteClassContent($genericTypes);
            $concreteClassFilePath = $this->cacheDir . DIRECTORY_SEPARATOR . $concreteClassName . '.php';

            file_put_contents($concreteClassFilePath, $concreteClassContent);

            include $concreteClassFilePath;

            $newExprNode->class->parts[count($newExprNode->class->parts) - 1] = $concreteClassName;

            /** @var Use_ $useNode */
            $useNode         = Parser::filterOne($nodes, Use_::class);
            $useNode->uses[] = new UseUse(new Name($concreteClassFqn));
        }

        $newFilePath = $this->cacheDir . DIRECTORY_SEPARATOR . $className . '.php';

        file_put_contents($newFilePath, Parser::build($nodes));

        include $newFilePath;
    }
}
