<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Composer\Autoload\ClassLoader;
use Composer\Util\Filesystem;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\GenericParameter;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

class Compiler
{
    private ClassLoader $loader;
    private Filesystem  $filesystem;
    private string      $cacheDir;

    /** @var GenericClass[] */
    private array $loaded = [];

    public function __construct(ClassLoader $loader, Filesystem $filesystem, string $cacheDir)
    {
        $this->loader     = $loader;
        $this->filesystem = $filesystem;
        $this->cacheDir   = $cacheDir;
    }

    public function needToHandle(string $classFilePath): bool
    {
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

    public function handle(string $classFilePath): void
    {
        $classFileContent = file_get_contents($classFilePath);

        $nodes = Parser::resolveNames(Parser::parse($classFileContent));

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

            $genericClass = new GenericClass($genericClassFqn, $genericClassFilePath);

            $concreteClassName = $genericClass->generateConcreteClassName($genericTypes);
            $concreteClassFqn  = $genericClass->generateConcreteClassFqn($genericTypes);
            $concreteClassAst  = $genericClass->generateConcreteClassAst($genericTypes);

            $concreteClassFilePath = $this->cacheDir . DIRECTORY_SEPARATOR . Parser::getRelativeDir($concreteClassAst) . DIRECTORY_SEPARATOR . $concreteClassName . '.php';

            $this->filesystem->ensureDirectoryExists(dirname($concreteClassFilePath));
            file_put_contents($concreteClassFilePath, Parser::build($concreteClassAst));

            $newExprNode->class->parts[count($newExprNode->class->parts) - 1] = $concreteClassName;

            /** @var Use_ $useNode */
            $useNode         = Parser::filterOne($nodes, Use_::class);
            $useNode->uses[] = new UseUse(new Name($concreteClassFqn));
        }

        $newFilePath = $this->cacheDir . DIRECTORY_SEPARATOR . Parser::getRelativeDir($nodes) . DIRECTORY_SEPARATOR . $className . '.php';

        $this->filesystem->ensureDirectoryExists(dirname($newFilePath));
        file_put_contents($newFilePath, Parser::build($nodes));
    }
}
