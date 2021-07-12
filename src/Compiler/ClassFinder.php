<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Composer\Autoload\ClassLoader;

class ClassFinder
{
    private ClassLoader $classLoader;

    public function __construct(array $autoloads, $autoloadGenerator, $filesystem, $basePath, $vendorPath)
    {
        $this->classLoader = new ClassLoader();
        foreach ($autoloads['psr-4'] as $namespace => $paths) {
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getAbsolutePath($filesystem, $basePath, $vendorPath, $path);
            }

            $this->classLoader->setPsr4($namespace, $exportedPaths);
        }

        foreach ($autoloads['psr-0'] as $namespace => $paths) {
            $exportedPaths = [];
            foreach ($paths as $path) {
                $exportedPaths[] = $autoloadGenerator->getAbsolutePath($filesystem, $basePath, $vendorPath, $path);
            }

            $this->classLoader->set($namespace, $exportedPaths);
        }
    }

    public function getFileContentByClassFqn(string $fqn): string
    {
        $filePath = $this->classLoader->findFile($fqn);
        if (!$filePath) {
            return '';
        }

        return file_get_contents($filePath);
    }

    public function getPrefixesPsr4(): array
    {
        return $this->classLoader->getPrefixesPsr4();
    }
}
