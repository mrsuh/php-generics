<?php

namespace Mrsuh\PhpGenerics\Compiler;

use Composer\Autoload\ClassLoader;
use Mrsuh\PhpGenerics\Exception\ClassNotFoundException;
use Mrsuh\PhpGenerics\Exception\FileNotReadableException;

class ClassFinder implements ClassFinderInterface
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

    public function isFileExistsByClassFqn(string $fqn): bool
    {
        return !empty($this->classLoader->findFile($fqn));
    }

    /**
     * @throws ClassNotFoundException
     * @throws FileNotReadableException
     */
    public function getFileContentByClassFqn(string $fqn): string
    {
        $filePath = $this->classLoader->findFile($fqn);
        if (!$filePath) {
            throw new ClassNotFoundException('Can\'t find class file %s', $fqn);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new FileNotReadableException('Can\'t read class %s from file %s', $fqn, $filePath);
        }

        return $content;
    }

    public function getPrefixesPsr4(): array
    {
        return $this->classLoader->getPrefixesPsr4();
    }
}
